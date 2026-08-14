<?php

namespace App\Services;

use App\Policies\SystemRole;
use RuntimeException;
use Throwable;

class RoleManagementService extends BaseService
{
    /** @return array<int, array<string, mixed>> */
    public function listRoles(string $search = '', string $status = ''): array
    {
        $builder = $this->db->table('roles r')
            ->select('r.role_id, r.role_name, r.description, r.role_type, r.active, r.created_at');
        if ($search !== '') {
            $builder->groupStart()->like('r.role_name', $search)->orLike('r.description', $search)->groupEnd();
        }
        if ($status !== '') {
            $builder->where('r.active', $status === 'Active' ? 1 : 0);
        }
        $roles = $builder->orderBy('r.role_type', 'DESC')->orderBy('r.role_name')->get()->getResultArray();

        foreach ($roles as &$role) {
            $role['user_count'] = $this->db->table('user_roles')->where('role_id', $role['role_id'])->countAllResults();
            $role['permission_count'] = $this->db->table('role_permissions')->where('role_id', $role['role_id'])->where('allowed', 1)->countAllResults();
        }
        unset($role);
        return $roles;
    }

    /** @return array<string, mixed>|null */
    public function getRole(string $roleId): ?array
    {
        $role = $this->db->table('roles')->where('role_id', $roleId)->get()->getRowArray();
        if ($role === null) {
            return null;
        }
        $role['permission_ids'] = array_column(
            $this->db->table('role_permissions')->select('permission_id')->where('role_id', $roleId)->where('allowed', 1)->get()->getResultArray(),
            'permission_id'
        );
        $role['user_count'] = $this->db->table('user_roles')->where('role_id', $roleId)->countAllResults();
        return $role;
    }

    /** @return array<int, array{module:string, permissions:array<int, array<string, mixed>>}> */
    public function permissionGroups(): array
    {
        $rows = $this->db->table('permissions')
            ->select('permission_id, module_name, page_name, action_name, description')
            ->orderBy('module_name')->orderBy('page_name')->orderBy('action_name')->get()->getResultArray();
        $groups = [];
        foreach ($rows as $permission) {
            $module = (string) $permission['module_name'];
            if (! isset($groups[$module])) {
                $groups[$module] = ['module' => $module, 'permissions' => []];
            }
            $groups[$module]['permissions'][] = $permission;
        }
        return array_values($groups);
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function createRole(array $input, string $actorId, array $meta): string
    {
        $roleName = trim((string) $input['role_name']);
        $this->assertUniqueName($roleName);
        $permissionIds = $this->validPermissionIds((array) ($input['permission_ids'] ?? []));

        $this->db->transBegin();
        try {
            if (! $this->db->table('roles')->insert([
                'role_name' => $roleName,
                'description' => trim((string) $input['description']),
                'role_type' => 'Custom',
                'active' => (string) $input['active'] === '1' ? 1 : 0,
            ])) {
                throw new RuntimeException('The role could not be created.');
            }
            $role = $this->db->table('roles')->select('role_id')->where('role_name', $roleName)->get()->getRowArray();
            if ($role === null) {
                throw new RuntimeException('The role could not be retrieved after creation.');
            }
            $roleId = (string) $role['role_id'];
            $this->syncPermissions($roleId, $permissionIds);
            $this->audit($actorId, 'CREATE', 'Created custom role ' . $roleName, null, [
                'role_id' => $roleId, 'role_name' => $roleName, 'permission_ids' => $permissionIds,
            ], $meta);
            $this->db->transCommit();
            return $roleId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function updateRole(string $roleId, array $input, string $actorId, array $meta): void
    {
        $original = $this->getRole($roleId);
        if ($original === null) {
            throw new RuntimeException('Role not found.');
        }

        $systemRole = $original['role_type'] === 'System';
        $administrator = $systemRole && $original['role_name'] === SystemRole::ADMINISTRATOR;
        $roleName = $systemRole ? (string) $original['role_name'] : trim((string) $input['role_name']);
        if (! $systemRole) {
            $this->assertUniqueName($roleName, $roleId);
        }
        $permissionIds = $administrator
            ? $this->allPermissionIds()
            : $this->validPermissionIds((array) ($input['permission_ids'] ?? []));
        if ($systemRole && $roleName === SystemRole::SECTION_HEAD) {
            $permissionIds = $this->withoutModulePermissions($permissionIds, 'Receiving');
        }
        $active = $administrator ? 1 : ((string) $input['active'] === '1' ? 1 : 0);

        $this->db->transBegin();
        try {
            if (! $this->db->table('roles')->where('role_id', $roleId)->update([
                'role_name' => $roleName,
                'description' => trim((string) $input['description']),
                'active' => $active,
                'updated_at' => date('Y-m-d H:i:s'),
            ])) {
                throw new RuntimeException('The role could not be updated.');
            }
            $this->syncPermissions($roleId, $permissionIds);
            $this->audit($actorId, 'UPDATE', 'Updated role ' . $roleName, [
                'role_name' => $original['role_name'], 'active' => (int) $original['active'], 'permission_ids' => $original['permission_ids'],
            ], [
                'role_name' => $roleName, 'active' => $active, 'permission_ids' => $permissionIds,
            ], $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array{ip:string,browser:string} $meta */
    public function deleteRole(string $roleId, string $actorId, array $meta): void
    {
        $role = $this->getRole($roleId);
        if ($role === null) {
            throw new RuntimeException('Role not found.');
        }
        if ($role['role_type'] === 'System') {
            throw new RuntimeException('System roles cannot be deleted.');
        }
        if ((int) $role['user_count'] > 0) {
            throw new RuntimeException('This role cannot be deleted while it is assigned to users.');
        }

        $this->db->transBegin();
        try {
            if (! $this->db->table('roles')->where('role_id', $roleId)->delete()) {
                throw new RuntimeException('The role could not be deleted.');
            }
            $this->audit($actorId, 'DELETE', 'Deleted custom role ' . $role['role_name'], [
                'role_id' => $roleId, 'role_name' => $role['role_name'], 'permission_ids' => $role['permission_ids'],
            ], null, $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function assertUniqueName(string $roleName, ?string $exceptRoleId = null): void
    {
        $builder = $this->db->table('roles')->where('role_name', $roleName);
        if ($exceptRoleId !== null) {
            $builder->where('role_id !=', $exceptRoleId);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Role name is already in use.');
        }
    }

    /** @param array<int, mixed> $requested @return array<int, string> */
    private function validPermissionIds(array $requested): array
    {
        $requested = array_values(array_unique(array_filter(array_map(static fn ($value): string => trim((string) $value), $requested))));
        if ($requested === []) {
            return [];
        }
        $rows = $this->db->table('permissions')->select('permission_id')->whereIn('permission_id', $requested)->get()->getResultArray();
        $valid = array_values(array_map('strval', array_column($rows, 'permission_id')));
        if (count($valid) !== count($requested)) {
            throw new RuntimeException('One or more selected permissions are invalid.');
        }
        return $valid;
    }

    /** @return array<int, string> */
    private function allPermissionIds(): array
    {
        return array_values(array_map('strval', array_column($this->db->table('permissions')->select('permission_id')->get()->getResultArray(), 'permission_id')));
    }

    /** @param array<int, string> $permissionIds @return array<int, string> */
    private function withoutModulePermissions(array $permissionIds, string $module): array
    {
        if ($permissionIds === []) {
            return [];
        }
        $restricted = array_map('strval', array_column(
            $this->db->table('permissions')->select('permission_id')->where('module_name', $module)->get()->getResultArray(),
            'permission_id'
        ));
        return array_values(array_diff($permissionIds, $restricted));
    }

    /** @param array<int, string> $permissionIds */
    private function syncPermissions(string $roleId, array $permissionIds): void
    {
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();
        foreach ($permissionIds as $permissionId) {
            $this->db->table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'allowed' => 1,
            ]);
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new @param array{ip:string,browser:string} $meta */
    private function audit(string $actorId, string $action, string $description, ?array $old, ?array $new, array $meta): void
    {
        if (! $this->db->table('audit_logs')->insert([
            'user_id' => $actorId,
            'document_id' => null,
            'module_name' => 'Roles & Permissions',
            'action_name' => $action,
            'description' => $description,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
            'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
        ])) {
            throw new RuntimeException('The role audit record could not be saved.');
        }
    }
}
