<?php

namespace App\Services;

class AuthorizationService extends BaseService
{
    public function hasPermission(string $userId, string $module, string $page, string $action): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->join('role_permissions rp', 'rp.role_id = r.role_id')
            ->join('permissions p', 'p.permission_id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('r.active', 1)
            ->where('rp.allowed', 1)
            ->where('p.module_name', $module)
            ->where('p.page_name', $page)
            ->where('p.action_name', strtoupper($action))
            ->countAllResults() > 0;
    }

    /** @return list<string> */
    public function roleNames(string $userId): array
    {
        $rows = $this->db->table('user_roles ur')
            ->select('r.role_name')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->where('r.active', 1)
            ->orderBy('r.role_name')
            ->get()->getResultArray();

        return array_column($rows, 'role_name');
    }

    /** @return array<string, bool|string> */
    public function navigationState(string $userId): array
    {
        $rows = $this->db->table('user_roles ur')
            ->select('p.module_name, p.page_name, p.action_name')
            ->distinct()
            ->join('roles r', 'r.role_id = ur.role_id')
            ->join('role_permissions rp', 'rp.role_id = r.role_id')
            ->join('permissions p', 'p.permission_id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('r.active', 1)
            ->where('rp.allowed', 1)
            ->get()->getResultArray();

        $grants = [];
        foreach ($rows as $row) {
            $grants[$row['module_name'] . '|' . $row['page_name'] . '|' . $row['action_name']] = true;
        }
        $can = static fn (string $module, string $page, string $action = 'VIEW'): bool
            => isset($grants[$module . '|' . $page . '|' . $action]);

        $state = [
            'receiving' => $can('Receiving', 'Receiving'),
            'inbox' => $can('General Inbox', 'Inbox'),
            'monitoring' => $can('Monitoring', 'Monitoring'),
            'archive' => $can('Document Archive', 'Archive'),
            'reports' => $can('Reports', 'Reports'),
            'users' => $can('User Management', 'Users'),
            'roles' => $can('Roles & Permissions', 'Roles'),
            'organizationOffices' => $can('Organization', 'Offices', 'MANAGE'),
            'organizationDepartments' => $can('Organization', 'Departments', 'MANAGE'),
            'organizationSections' => $can('Organization', 'Sections', 'MANAGE'),
            'documentTypes' => $can('Document Types', 'Document Types'),
            'documentTypesManage' => $can('Document Types', 'Document Types', 'MANAGE'),
            'actions' => $can('Routing Actions', 'Routing Actions'),
            'actionsManage' => $can('Routing Actions', 'Routing Actions', 'MANAGE'),
            'email' => $can('Email Configuration', 'Email Settings', 'MANAGE'),
            'telegram' => $can('Telegram Configuration', 'Telegram Settings', 'MANAGE'),
            'audit' => $can('Audit Log', 'Audit Log'),
        ];
        $state['organization'] = $state['organizationOffices'] || $state['organizationDepartments'] || $state['organizationSections'];
        $state['administration'] = $state['users'] || $state['roles'] || $state['organization'] || $state['documentTypes'] || $state['actions'] || $state['email'] || $state['telegram'] || $state['audit'];
        $roles = $this->roleNames($userId);
        $state['roleLabel'] = $roles === [] ? 'Authorized User' : implode(' · ', $roles);

        return $state;
    }
}
