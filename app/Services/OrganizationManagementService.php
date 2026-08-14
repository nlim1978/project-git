<?php

namespace App\Services;

use App\Policies\SystemRole;
use RuntimeException;
use Throwable;

class OrganizationManagementService extends BaseService
{
    public function isSuperAdmin(string $actorId): bool
    {
        return (new OrganizationScopeService($this->db))->isSuperAdmin($actorId);
    }
    /** @return array<int, array<string, mixed>> */
    public function offices(string $actorId, string $search = '', string $status = ''): array
    {
        $builder = $this->db->table('offices o')
            ->select('o.office_id, o.office_code, o.office_name, o.active, o.created_at')
            ->select('(SELECT COUNT(*) FROM departments d WHERE d.office_id = o.office_id) AS department_count', false);
        $this->applySearchAndStatus($builder, $search, $status, 'o.office_code', 'o.office_name', 'o.active');
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->isSuperAdmin($actorId)) {
            $builder->where('o.office_id', $scope->requireOfficeId($actorId));
        }
        return $builder->orderBy('o.office_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function departments(string $actorId, string $search = '', string $status = '', string $officeId = ''): array
    {
        $builder = $this->db->table('departments d')
            ->select('d.department_id, d.department_code, d.department_name, d.office_id, d.active, d.created_at, o.office_code, o.office_name')
            ->select('(SELECT COUNT(*) FROM sections s WHERE s.department_id = d.department_id) AS section_count', false)
            ->join('offices o', 'o.office_id = d.office_id');
        $this->applySearchAndStatus($builder, $search, $status, 'd.department_code', 'd.department_name', 'd.active');
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->isSuperAdmin($actorId)) {
            $builder->where('d.office_id', $scope->requireOfficeId($actorId));
        }
        if ($officeId !== '') {
            $builder->where('d.office_id', $officeId);
        }
        return $builder->orderBy('o.office_name')->orderBy('d.department_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(string $actorId, string $search = '', string $status = '', string $departmentId = ''): array
    {
        $builder = $this->db->table('sections s')
            ->select("s.section_id, s.section_code, s.section_name, s.department_id, s.head_user_id, s.active, s.created_at, d.department_code, d.department_name, o.office_name, COALESCE(u.first_name + ' ' + u.last_name, '') AS head_name", false)
            ->select('(SELECT COUNT(*) FROM user_sections us WHERE us.section_id = s.section_id) AS user_count', false)
            ->join('departments d', 'd.department_id = s.department_id')
            ->join('offices o', 'o.office_id = d.office_id')
            ->join('users u', 'u.user_id = s.head_user_id', 'left');
        $this->applySearchAndStatus($builder, $search, $status, 's.section_code', 's.section_name', 's.active');
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->isSuperAdmin($actorId)) {
            $builder->where('d.office_id', $scope->requireOfficeId($actorId));
        }
        if ($departmentId !== '') {
            $builder->where('s.department_id', $departmentId);
        }
        return $builder->orderBy('o.office_name')->orderBy('d.department_name')->orderBy('s.section_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function officeOptions(string $actorId): array
    {
        $builder = $this->db->table('offices')->select('office_id, office_code, office_name, active');
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->isSuperAdmin($actorId)) {
            $builder->where('office_id', $scope->requireOfficeId($actorId));
        }
        return $builder->orderBy('office_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function departmentOptions(string $actorId): array
    {
        $builder = $this->db->table('departments d')->select('d.department_id, d.department_code, d.department_name, d.office_id, d.active, o.office_name, o.active AS office_active')
            ->join('offices o', 'o.office_id = d.office_id');
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->isSuperAdmin($actorId)) {
            $builder->where('d.office_id', $scope->requireOfficeId($actorId));
        }
        return $builder->orderBy('o.office_name')->orderBy('d.department_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function userOptions(string $actorId): array
    {
        $scope = new OrganizationScopeService($this->db);
        $builder = $this->db->table('users u')->distinct()->select('u.user_id, u.employee_id, u.first_name, u.last_name, u.account_status');
        if (! $scope->isSuperAdmin($actorId)) {
            $globalRoleNames = implode(',', array_map(fn (string $role): string => $this->db->escape($role), SystemRole::GLOBAL_ADMINISTRATORS));
            $builder->join('user_sections us', 'us.user_id = u.user_id')
                ->join('sections s', 's.section_id = us.section_id')
                ->join('departments d', 'd.department_id = s.department_id')
                ->where('d.office_id', $scope->requireOfficeId($actorId))
                ->where("NOT EXISTS (SELECT 1 FROM dbo.user_roles sur INNER JOIN dbo.roles sr ON sr.role_id = sur.role_id WHERE sur.user_id = u.user_id AND sr.active = 1 AND sr.role_name IN ({$globalRoleNames}))", null, false);
        }
        return $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();
    }

    public function canAccess(string $type, string $id, string $actorId): bool
    {
        $scope = new OrganizationScopeService($this->db);
        return match ($type) {
            'office' => $scope->canAccessOffice($actorId, $id),
            'department' => $scope->canAccessDepartment($actorId, $id),
            'section' => $scope->canAccessSection($actorId, $id),
            default => false,
        };
    }

    /** @return array<string, mixed>|null */
    public function find(string $type, string $id): ?array
    {
        [$table, $idField] = $this->identity($type);
        return $this->db->table($table)->where($idField, $id)->get()->getRowArray();
    }

    /** @return array<string, mixed>|null */
    public function detail(string $type, string $id): ?array
    {
        if ($type === 'office') {
            return $this->db->table('offices o')
                ->select('o.*')->select('(SELECT COUNT(*) FROM departments d WHERE d.office_id = o.office_id) AS department_count', false)
                ->where('o.office_id', $id)->get()->getRowArray();
        }
        if ($type === 'department') {
            return $this->db->table('departments d')
                ->select('d.*, o.office_code, o.office_name')
                ->select('(SELECT COUNT(*) FROM sections s WHERE s.department_id = d.department_id) AS section_count', false)
                ->join('offices o', 'o.office_id = d.office_id')
                ->where('d.department_id', $id)->get()->getRowArray();
        }
        if ($type === 'section') {
            return $this->db->table('sections s')
                ->select("s.*, d.department_code, d.department_name, o.office_code, o.office_name, COALESCE(u.first_name + ' ' + u.last_name, '') AS head_name", false)
                ->select('(SELECT COUNT(*) FROM user_sections us WHERE us.section_id = s.section_id) AS user_count', false)
                ->join('departments d', 'd.department_id = s.department_id')
                ->join('offices o', 'o.office_id = d.office_id')
                ->join('users u', 'u.user_id = s.head_user_id', 'left')
                ->where('s.section_id', $id)->get()->getRowArray();
        }
        throw new RuntimeException('Invalid organization record type.');
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function create(string $type, array $input, string $actorId, array $meta): string
    {
        $this->assertInputScope($type, $input, $actorId, true);
        [$table, $idField, $codeField, $nameField] = $this->identity($type);
        $this->assertUniqueCode($table, $idField, $codeField, trim((string) $input['code']));
        $data = $this->recordData($type, $input);

        $this->db->transBegin();
        try {
            if (! $this->db->table($table)->insert($data)) {
                throw new RuntimeException(ucfirst($type) . ' could not be created.');
            }
            $row = $this->db->table($table)->select($idField)->where($codeField, $data[$codeField])->get()->getRowArray();
            if ($row === null) {
                throw new RuntimeException(ucfirst($type) . ' could not be retrieved after creation.');
            }
            $id = (string) $row[$idField];
            $this->audit($actorId, 'CREATE', 'Created ' . $type . ' ' . $data[$nameField], null, ['type' => $type, 'id' => $id] + $data, $meta);
            $this->db->transCommit();
            return $id;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function update(string $type, string $id, array $input, string $actorId, array $meta): void
    {
        if (! $this->canAccess($type, $id, $actorId)) {
            throw new RuntimeException('This organization record is outside your office scope.');
        }
        $this->assertInputScope($type, $input, $actorId, false);
        [$table, $idField, $codeField, $nameField] = $this->identity($type);
        $old = $this->find($type, $id);
        if ($old === null) {
            throw new RuntimeException(ucfirst($type) . ' not found.');
        }
        $this->assertUniqueCode($table, $idField, $codeField, trim((string) $input['code']), $id);
        $data = $this->recordData($type, $input);
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            if (! $this->db->table($table)->where($idField, $id)->update($data)) {
                throw new RuntimeException(ucfirst($type) . ' could not be updated.');
            }
            $this->audit($actorId, 'UPDATE', 'Updated ' . $type . ' ' . $data[$nameField], $old, ['type' => $type, 'id' => $id] + $data, $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array{ip:string,browser:string} $meta */
    public function toggleStatus(string $type, string $id, string $actorId, array $meta): void
    {
        if (! $this->canAccess($type, $id, $actorId)) {
            throw new RuntimeException('This organization record is outside your office scope.');
        }
        [$table, $idField, , $nameField] = $this->identity($type);
        $row = $this->find($type, $id);
        if ($row === null) {
            throw new RuntimeException(ucfirst($type) . ' not found.');
        }
        $active = (int) $row['active'] === 1 ? 0 : 1;
        $active === 0 ? $this->assertCanDeactivate($type, $id) : $this->assertCanActivate($type, $row);

        $this->db->transBegin();
        try {
            if (! $this->db->table($table)->where($idField, $id)->update(['active' => $active, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new RuntimeException(ucfirst($type) . ' status could not be changed.');
            }
            $this->audit($actorId, $active ? 'ACTIVATE' : 'DEACTIVATE', ($active ? 'Activated ' : 'Deactivated ') . $type . ' ' . $row[$nameField], ['active' => (int) $row['active']], ['active' => $active], $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return array<int, string> */
    public function sectionUserIds(string $sectionId): array
    {
        return array_map('strval', array_column($this->db->table('user_sections')->select('user_id')->where('section_id', $sectionId)->get()->getResultArray(), 'user_id'));
    }

    /** @param array<int, mixed> $requested @param array{ip:string,browser:string} $meta */
    public function saveSectionAssignments(string $sectionId, array $requested, string $actorId, array $meta): void
    {
        $scope = new OrganizationScopeService($this->db);
        if (! $scope->canAccessSection($actorId, $sectionId)) {
            throw new RuntimeException('This section is outside your office scope.');
        }
        foreach ($requested as $userId) {
            if (! $scope->canAccessUser($actorId, (string) $userId)) {
                throw new RuntimeException('A selected user is outside your office scope.');
            }
        }
        $section = $this->find('section', $sectionId);
        if ($section === null) {
            throw new RuntimeException('Section not found.');
        }
        $requested = array_values(array_unique(array_filter(array_map('strval', $requested))));
        $valid = $requested === [] ? [] : array_map('strval', array_column(
            $this->db->table('users')->select('user_id')->where('account_status', 'Active')->whereIn('user_id', $requested)->get()->getResultArray(),
            'user_id'
        ));
        if (count($valid) !== count($requested)) {
            throw new RuntimeException('One or more selected users are invalid or inactive.');
        }
        $old = $this->sectionUserIds($sectionId);
        $remove = array_values(array_diff($old, $valid));
        if ($remove !== []) {
            $primaryCount = $this->db->table('user_sections')->where('section_id', $sectionId)->where('is_primary', 1)->whereIn('user_id', $remove)->countAllResults();
            if ($primaryCount > 0) {
                throw new RuntimeException('A primary section assignment cannot be removed here. Change that user\'s primary section in User Management first.');
            }
        }

        $this->db->transBegin();
        try {
            if ($remove !== []) {
                $this->db->table('user_sections')->where('section_id', $sectionId)->whereIn('user_id', $remove)->delete();
            }
            foreach (array_diff($valid, $old) as $userId) {
                $this->db->table('user_sections')->insert(['user_id' => $userId, 'section_id' => $sectionId, 'is_primary' => 0]);
            }
            $this->audit($actorId, 'ASSIGN', 'Updated users assigned to section ' . $section['section_name'], ['user_ids' => $old], ['user_ids' => $valid], $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function recordData(string $type, array $input): array
    {
        [, , $codeField, $nameField] = $this->identity($type);
        $data = [
            $codeField => strtoupper(trim((string) $input['code'])),
            $nameField => trim((string) $input['name']),
            'active' => (string) $input['active'] === '0' ? 0 : 1,
        ];
        if ($type === 'department') {
            $this->assertActiveParent('office', (string) $input['parent_id']);
            $data['office_id'] = (string) $input['parent_id'];
        } elseif ($type === 'section') {
            $this->assertActiveParent('department', (string) $input['parent_id']);
            $data['department_id'] = (string) $input['parent_id'];
            $data['head_user_id'] = trim((string) ($input['head_user_id'] ?? '')) ?: null;
            if ($data['head_user_id'] !== null && $this->db->table('users')->where('user_id', $data['head_user_id'])->where('account_status', 'Active')->countAllResults() === 0) {
                throw new RuntimeException('Section Head must be an active user.');
            }
        }
        return $data;
    }

    /** @param array<string, mixed> $input */
    private function assertInputScope(string $type, array $input, string $actorId, bool $creating): void
    {
        $scope = new OrganizationScopeService($this->db);
        if ($scope->isSuperAdmin($actorId)) {
            return;
        }
        if ($type === 'office') {
            if ($creating) {
                throw new RuntimeException('Only Super Admin may create an office.');
            }
            return;
        }
        $parentId = (string) ($input['parent_id'] ?? '');
        $allowed = $type === 'department'
            ? $scope->canAccessOffice($actorId, $parentId)
            : $scope->canAccessDepartment($actorId, $parentId);
        if (! $allowed) {
            throw new RuntimeException('The selected parent is outside your office scope.');
        }
    }

    private function assertActiveParent(string $type, string $id): void
    {
        $row = $this->find($type, $id);
        if ($row === null || (int) $row['active'] !== 1) {
            throw new RuntimeException(ucfirst($type) . ' must be active.');
        }
    }

    private function assertCanDeactivate(string $type, string $id): void
    {
        if ($type === 'office' && $this->db->table('departments')->where('office_id', $id)->where('active', 1)->countAllResults() > 0) {
            throw new RuntimeException('Deactivate or move this office\'s active departments first.');
        }
        if ($type === 'department' && $this->db->table('sections')->where('department_id', $id)->where('active', 1)->countAllResults() > 0) {
            throw new RuntimeException('Deactivate or move this department\'s active sections first.');
        }
        if ($type === 'section') {
            if ($this->db->table('user_sections')->where('section_id', $id)->countAllResults() > 0) {
                throw new RuntimeException('Remove this section\'s user assignments before deactivating it.');
            }
            if ($this->db->table('documents')->where('current_section_id', $id)->countAllResults() > 0) {
                throw new RuntimeException('This section still has current documents and cannot be deactivated.');
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function assertCanActivate(string $type, array $row): void
    {
        if ($type === 'department') {
            $this->assertActiveParent('office', (string) $row['office_id']);
        } elseif ($type === 'section') {
            $this->assertActiveParent('department', (string) $row['department_id']);
        }
    }

    private function assertUniqueCode(string $table, string $idField, string $codeField, string $code, ?string $exceptId = null): void
    {
        $builder = $this->db->table($table)->where($codeField, strtoupper($code));
        if ($exceptId !== null) {
            $builder->where($idField . ' !=', $exceptId);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Code is already in use.');
        }
    }

    /** @return array{string,string,string,string} */
    private function identity(string $type): array
    {
        return match ($type) {
            'office' => ['offices', 'office_id', 'office_code', 'office_name'],
            'department' => ['departments', 'department_id', 'department_code', 'department_name'],
            'section' => ['sections', 'section_id', 'section_code', 'section_name'],
            default => throw new RuntimeException('Invalid organization record type.'),
        };
    }

    private function applySearchAndStatus($builder, string $search, string $status, string $codeField, string $nameField, string $activeField): void
    {
        if ($search !== '') {
            $builder->groupStart()->like($codeField, $search)->orLike($nameField, $search)->groupEnd();
        }
        if ($status !== '') {
            $builder->where($activeField, $status === 'Active' ? 1 : 0);
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new @param array{ip:string,browser:string} $meta */
    private function audit(string $actorId, string $action, string $description, ?array $old, ?array $new, array $meta): void
    {
        if (! $this->db->table('audit_logs')->insert([
            'user_id' => $actorId, 'document_id' => null, 'module_name' => 'Organization', 'action_name' => $action,
            'description' => $description,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
            'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
        ])) {
            throw new RuntimeException('The organization audit record could not be saved.');
        }
    }
}
