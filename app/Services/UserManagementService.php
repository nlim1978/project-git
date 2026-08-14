<?php

namespace App\Services;

use App\Models\UserModel;
use App\Policies\SystemRole;
use RuntimeException;
use Throwable;

class UserManagementService extends BaseService
{
    /** @return array<int, array<string, mixed>> */
    public function roles(string $actorId): array
    {
        $builder = $this->db->table('roles')->where('active', 1);
        if (! (new OrganizationScopeService($this->db))->isSuperAdmin($actorId)) {
            $builder->whereNotIn('role_name', SystemRole::GLOBAL_ADMINISTRATORS);
        }
        return $builder->orderBy('role_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(string $actorId): array
    {
        $builder = $this->db->table('sections s')
            ->select('s.section_id, s.section_code, s.section_name, d.department_name, o.office_name')
            ->join('departments d', 'd.department_id = s.department_id')
            ->join('offices o', 'o.office_id = d.office_id')
            ->where('s.active', 1)->where('d.active', 1)->where('o.active', 1);
        $scope = new OrganizationScopeService($this->db);
        $dataScope = $scope->documentDataScope($actorId);
        if ($dataScope->officeId() !== null) {
            $builder->where('o.office_id', $dataScope->officeId());
        }
        if ($dataScope->sectionIds() === []) {
            return [];
        }
        if ($dataScope->sectionIds() !== null) {
            $builder->whereIn('s.section_id', $dataScope->sectionIds());
        }
        return $builder->orderBy('o.office_name')->orderBy('d.department_name')->orderBy('s.section_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(string $actorId): array
    {
        $scope = new OrganizationScopeService($this->db);
        $dataScope = $scope->documentDataScope($actorId);
        $builder = $this->db->table('users u')->distinct()
            ->select('u.user_id, u.employee_id, u.username, u.first_name, u.last_name, u.email, u.account_status')
            ->join('user_sections us', 'us.user_id = u.user_id', 'left')
            ->join('sections s', 's.section_id = us.section_id', 'left')
            ->join('departments d', 'd.department_id = s.department_id', 'left');
        if ($dataScope->officeId() !== null) {
            $globalRoleNames = implode(',', array_map(fn (string $role): string => $this->db->escape($role), SystemRole::GLOBAL_ADMINISTRATORS));
            $builder->where('d.office_id', $dataScope->officeId())
                ->where("NOT EXISTS (SELECT 1 FROM dbo.user_roles sur INNER JOIN dbo.roles sr ON sr.role_id = sur.role_id WHERE sur.user_id = u.user_id AND sr.active = 1 AND sr.role_name IN ({$globalRoleNames}))", null, false);
        }
        if ($dataScope->sectionIds() === []) {
            return [];
        }
        if ($dataScope->sectionIds() !== null) {
            $builder->whereIn('us.section_id', $dataScope->sectionIds());
        }
        $users = $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();

        foreach ($users as &$user) {
            $user['roles'] = implode(', ', (new AuthorizationService($this->db))->roleNames((string) $user['user_id']));
        }

        return $users;
    }

    /** @return array<string, mixed>|null */
    public function getUser(string $userId): ?array
    {
        $user = $this->db->table('users')->where('user_id', $userId)->get()->getRowArray();
        if ($user === null) {
            return null;
        }

        $user['role_ids'] = array_column($this->db->table('user_roles')->select('role_id')->where('user_id', $userId)->get()->getResultArray(), 'role_id');
        $sections = $this->db->table('user_sections')->select('section_id, is_primary')->where('user_id', $userId)->get()->getResultArray();
        $user['section_ids'] = array_column($sections, 'section_id');
        $primary = array_values(array_filter($sections, static fn (array $row): bool => (int) $row['is_primary'] === 1));
        $user['primary_section_id'] = $primary[0]['section_id'] ?? null;
        $user['role_names'] = array_map('strval', array_column(
            $this->db->table('user_roles ur')->select('r.role_name')->join('roles r', 'r.role_id = ur.role_id')
                ->where('ur.user_id', $userId)->orderBy('r.role_name')->get()->getResultArray(),
            'role_name'
        ));
        $user['section_details'] = $this->db->table('user_sections us')
            ->select('s.section_name, s.section_code, d.department_name, o.office_name, us.is_primary')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->join('offices o', 'o.office_id = d.office_id')
            ->where('us.user_id', $userId)->orderBy('us.is_primary', 'DESC')->orderBy('s.section_name')->get()->getResultArray();

        return $user;
    }

    /** @param array<string, mixed> $input */
    public function createUser(array $input, string $actorId): string
    {
        $this->assertAssignmentsInScope($input, $actorId);
        $this->assertUniqueIdentity($input);
        $this->db->transBegin();

        try {
            $model = new UserModel();
            if (! $model->insertRecord($this->userData($input, $actorId, true))) {
                throw new RuntimeException('User was not created.');
            }
            $user = $this->db->table('users')->select('user_id')->where('username', trim((string) $input['username']))->get()->getRowArray();
            if ($user === null) {
                throw new RuntimeException('User was not created.');
            }

            $userId = (string) $user['user_id'];
            $this->syncAssignments($userId, $input);
            $this->db->transCommit();
            return $userId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input */
    public function updateUser(string $userId, array $input, string $actorId): void
    {
        if (! (new OrganizationScopeService($this->db))->canAccessUser($actorId, $userId)) {
            throw new RuntimeException('This user is outside your office scope.');
        }
        $this->assertAssignmentsInScope($input, $actorId);
        if ($this->getUser($userId) === null) {
            throw new RuntimeException('User not found.');
        }
        if ($userId === $actorId && ($input['account_status'] ?? 'Active') === 'Inactive') {
            throw new RuntimeException('You cannot deactivate your own signed-in account.');
        }

        $this->assertUniqueIdentity($input, $userId);
        $this->db->transBegin();
        try {
            (new UserModel())->update($userId, $this->userData($input, $actorId, false));
            $this->syncAssignments($userId, $input);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array{ip:string,browser:string} $meta */
    public function setAccountStatus(string $userId, string $status, string $actorId, array $meta): void
    {
        if (! (new OrganizationScopeService($this->db))->canAccessUser($actorId, $userId)) {
            throw new RuntimeException('This user is outside your office scope.');
        }
        $user = $this->getUser($userId);
        if ($user === null) {
            throw new RuntimeException('User not found.');
        }
        if (! in_array($status, ['Active', 'Inactive'], true)) {
            throw new RuntimeException('Invalid account status.');
        }
        if ($userId === $actorId && $status === 'Inactive') {
            throw new RuntimeException('You cannot deactivate your own signed-in account.');
        }
        if ($user['account_status'] === $status) {
            return;
        }

        $this->db->transBegin();
        try {
            if (! (new UserModel())->update($userId, [
                'account_status' => $status,
                'updated_by' => $actorId,
                'updated_at' => date('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('User status could not be changed.');
            }
            $action = $status === 'Active' ? 'ACTIVATE' : 'DEACTIVATE';
            if (! $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => null,
                'module_name' => 'User Management',
                'action_name' => $action,
                'description' => ($status === 'Active' ? 'Reactivated user ' : 'Deactivated user ') . $user['username'],
                'old_value' => json_encode(['user_id' => $userId, 'account_status' => $user['account_status']], JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode(['user_id' => $userId, 'account_status' => $status], JSON_UNESCAPED_SLASHES),
                'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
                'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
            ])) {
                throw new RuntimeException('The user status audit record could not be saved.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function userData(array $input, string $actorId, bool $creating): array
    {
        $data = [
            'employee_id' => trim((string) $input['employee_id']),
            'username' => trim((string) $input['username']),
            'first_name' => trim((string) $input['first_name']),
            'middle_name' => trim((string) ($input['middle_name'] ?? '')) ?: null,
            'last_name' => trim((string) $input['last_name']),
            'email' => trim((string) $input['email']),
            'contact_number' => trim((string) ($input['contact_number'] ?? '')) ?: null,
            'telegram_chat_id' => trim((string) ($input['telegram_chat_id'] ?? '')) ?: null,
            'telegram_username' => $this->telegramUsername($input['telegram_username'] ?? null),
            'telegram_notification_enabled' => (string) ($input['telegram_notification_enabled'] ?? '0') === '1' ? 1 : 0,
            'account_status' => ($input['account_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active',
            'updated_by' => $actorId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($creating) {
            $data['created_by'] = $actorId;
        }

        if ($data['telegram_notification_enabled'] === 1 && $data['telegram_chat_id'] === null) {
            throw new RuntimeException('Telegram Chat ID is required when Telegram notifications are enabled for a user.');
        }

        $password = (string) ($input['password'] ?? '');
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $data['password_changed_at'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    private function telegramUsername(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : '@' . mb_substr(ltrim($value, '@'), 0, 99);
    }

    /** @param array<string, mixed> $input */
    private function syncAssignments(string $userId, array $input): void
    {
        $roleIds = array_values(array_unique(array_filter((array) ($input['role_ids'] ?? []))));
        $sectionIds = array_values(array_unique(array_filter((array) ($input['section_ids'] ?? []))));
        $primary = (string) ($input['primary_section_id'] ?? '');

        if ($roleIds === []) {
            throw new RuntimeException('Assign at least one role.');
        }
        if ($sectionIds === [] || $primary === '' || ! in_array($primary, $sectionIds, true)) {
            throw new RuntimeException('Assign at least one section and select its primary section.');
        }

        $this->db->table('user_roles')->where('user_id', $userId)->delete();
        foreach ($roleIds as $roleId) {
            $this->db->table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        }

        $this->db->table('user_sections')->where('user_id', $userId)->delete();
        foreach ($sectionIds as $sectionId) {
            $this->db->table('user_sections')->insert([
                'user_id' => $userId,
                'section_id' => $sectionId,
                'is_primary' => $sectionId === $primary ? 1 : 0,
            ]);
        }
    }

    /** @param array<string, mixed> $input */
    private function assertAssignmentsInScope(array $input, string $actorId): void
    {
        $scope = new OrganizationScopeService($this->db);
        if ($scope->isSuperAdmin($actorId)) {
            return;
        }
        foreach ((array) ($input['section_ids'] ?? []) as $sectionId) {
            if (! $scope->canManageSection($actorId, (string) $sectionId)) {
                throw new RuntimeException('A selected section is outside your permitted section scope.');
            }
        }
        $requestedRoles = array_values(array_filter((array) ($input['role_ids'] ?? [])));
        if ($requestedRoles !== []) {
            $globalRoleCount = $this->db->table('roles')->whereIn('role_id', $requestedRoles)
                ->whereIn('role_name', SystemRole::GLOBAL_ADMINISTRATORS)->countAllResults();
            if ($globalRoleCount > 0) {
                throw new RuntimeException('Only Super Admin may assign the global Administrator role.');
            }
        }
    }

    /** @param array<string, mixed> $input */
    private function assertUniqueIdentity(array $input, ?string $exceptUserId = null): void
    {
        foreach (['employee_id', 'username', 'email'] as $field) {
            $builder = $this->db->table('users')->where($field, trim((string) $input[$field]));
            if ($exceptUserId !== null) {
                $builder->where('user_id !=', $exceptUserId);
            }
            if ($builder->countAllResults() > 0) {
                throw new RuntimeException(ucwords(str_replace('_', ' ', $field)) . ' is already in use.');
            }
        }
    }
}
