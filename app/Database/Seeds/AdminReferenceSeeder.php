<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class AdminReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->transStart();

        $this->seedOrganization();
        $this->seedRolesAndPermissions();
        $adminId = $this->seedAdministrator();
        $this->seedDocumentReferences();
        $this->seedRoutingActions();
        $this->seedSafeConfiguration($adminId);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new RuntimeException('Admin/reference seeding transaction failed.');
        }
    }

    private function seedOrganization(): void
    {
        foreach ([
            ['office_code' => 'OD', 'office_name' => 'Office of the Director'],
            ['office_code' => 'OAS', 'office_name' => 'Office of Administrative Services'],
            ['office_code' => 'OFS', 'office_name' => 'Office of Financial Services'],
        ] as $row) {
            $this->upsert('offices', ['office_code' => $row['office_code']], [
                'office_name' => $row['office_name'],
                'active'      => 1,
            ]);
        }

        foreach ([
            ['OD', 'OPS', 'Operations Department'],
            ['OAS', 'ADMIN', 'Administrative Department'],
            ['OFS', 'FIN', 'Finance Department'],
            ['OD', 'CORP', 'Corporate Services Department'],
        ] as [$officeCode, $code, $name]) {
            $this->upsert('departments', ['department_code' => $code], [
                'office_id'       => $this->id('offices', 'office_code', $officeCode, 'office_id'),
                'department_name' => $name,
                'active'          => 1,
            ]);
        }

        foreach ([
            ['ADMIN', 'REC', 'Records Section'],
            ['ADMIN', 'HR', 'Human Resources Section'],
            ['ADMIN', 'ADM', 'Administrative Section'],
            ['FIN', 'ACC', 'Accounting Section'],
            ['FIN', 'BUD', 'Budget Section'],
            ['CORP', 'LEG', 'Legal Section'],
            ['CORP', 'PLAN', 'Planning Section'],
            ['OPS', 'PROC', 'Procurement Section'],
            ['OPS', 'IT', 'Information Technology Section'],
        ] as [$departmentCode, $code, $name]) {
            $this->upsert('sections', ['section_code' => $code], [
                'department_id' => $this->id('departments', 'department_code', $departmentCode, 'department_id'),
                'section_name'  => $name,
                'active'        => 1,
            ]);
        }
    }

    private function seedRolesAndPermissions(): void
    {
        $roles = [
            'Administrator'       => 'Full system administration access',
            'Receiving Personnel' => 'Registers and initially assigns received documents',
            'Section Head'        => 'Manages and routes documents for assigned sections',
            'Personnel'           => 'Processes documents assigned to the user or section',
            'Monitoring Officer'  => 'Monitors document movement and reporting',
        ];

        foreach ($roles as $name => $description) {
            $this->upsert('roles', ['role_name' => $name], [
                'description' => $description,
                'role_type'   => 'System',
                'active'      => 1,
            ]);
        }

        $permissions = [
            ['Authentication', 'Account', 'VIEW'],
            ['Receiving', 'Receiving', 'VIEW'],
            ['Receiving', 'Receiving', 'CREATE'],
            ['Receiving', 'Receiving', 'UPDATE'],
            ['General Inbox', 'Inbox', 'VIEW'],
            ['Document Details', 'Document Details', 'VIEW'],
            ['Document Routing', 'Routing', 'VIEW'],
            ['Document Routing', 'Routing', 'ROUTE'],
            ['Monitoring', 'Monitoring', 'VIEW'],
            ['Document Archive', 'Archive', 'VIEW'],
            ['Reports', 'Reports', 'VIEW'],
            ['Reports', 'Reports', 'EXPORT'],
            ['User Management', 'Users', 'VIEW'],
            ['User Management', 'Users', 'CREATE'],
            ['User Management', 'Users', 'UPDATE'],
            ['User Management', 'Users', 'DELETE'],
            ['Roles & Permissions', 'Roles', 'VIEW'],
            ['Roles & Permissions', 'Roles', 'MANAGE'],
            ['Organization', 'Offices', 'MANAGE'],
            ['Organization', 'Departments', 'MANAGE'],
            ['Organization', 'Sections', 'MANAGE'],
            ['Document Types', 'Document Types', 'VIEW'],
            ['Document Types', 'Document Types', 'MANAGE'],
            ['Routing Actions', 'Routing Actions', 'VIEW'],
            ['Routing Actions', 'Routing Actions', 'MANAGE'],
            ['Email Configuration', 'Email Settings', 'MANAGE'],
            ['Telegram Configuration', 'Telegram Settings', 'MANAGE'],
            ['Audit Log', 'Audit Log', 'VIEW'],
        ];

        foreach ($permissions as [$module, $page, $action]) {
            $this->upsert('permissions', [
                'module_name' => $module,
                'page_name'   => $page,
                'action_name' => $action,
            ], []);
        }

        $permissionRows = $this->db->table('permissions')->get()->getResultArray();

        foreach ($roles as $roleName => $_) {
            $roleId = $this->id('roles', 'role_name', $roleName, 'role_id');

            foreach ($permissionRows as $permission) {
                if ($roleName === 'Administrator' || $this->roleAllows($roleName, $permission)) {
                    $this->upsert('role_permissions', [
                        'role_id'       => $roleId,
                        'permission_id' => $permission['permission_id'],
                    ], ['allowed' => 1]);
                }
            }
        }
    }

    private function seedAdministrator(): string
    {
        $existing = $this->db->table('users')->where('username', 'admin')->get()->getRowArray();

        if ($existing === null) {
            $this->db->table('users')->insert([
                'employee_id'                   => 'SYS-ADMIN',
                'username'                      => 'admin',
                'password_hash'                 => password_hash('ChangeMe123!', PASSWORD_DEFAULT),
                'first_name'                    => 'System',
                'middle_name'                   => null,
                'last_name'                     => 'Administrator',
                'email'                         => 'admin@example.local',
                'contact_number'                => null,
                'account_status'                => 'Active',
                'password_changed_at'           => date('Y-m-d H:i:s'),
                'telegram_notification_enabled' => 0,
            ]);
        }

        $adminId = $this->id('users', 'username', 'admin', 'user_id');
        $adminRoleId = $this->id('roles', 'role_name', 'Administrator', 'role_id');
        $itSectionId = $this->id('sections', 'section_code', 'IT', 'section_id');

        $this->upsert('user_roles', ['user_id' => $adminId, 'role_id' => $adminRoleId], []);

        $hasPrimary = $this->db->table('user_sections')
            ->where('user_id', $adminId)
            ->where('is_primary', 1)
            ->countAllResults() > 0;

        $this->upsert('user_sections', ['user_id' => $adminId, 'section_id' => $itSectionId], [
            'is_primary' => $hasPrimary ? 0 : 1,
        ]);

        return $adminId;
    }

    private function seedDocumentReferences(): void
    {
        foreach ([
            ['RECEIVED', 'Received', 0],
            ['IN_PROGRESS', 'In Progress', 0],
            ['RETURNED', 'Returned', 0],
            ['COMPLETED', 'Completed', 1],
        ] as [$code, $name, $terminal]) {
            $this->upsert('document_statuses', ['status_code' => $code], [
                'status_name' => $name,
                'is_terminal' => $terminal,
                'active'      => 1,
            ]);
        }

        foreach ([
            ['MEM', 'Memorandum', 'MEM', 'Internal or external memorandum'],
            ['LTR', 'Letter', 'LTR', 'Official incoming or outgoing letter'],
            ['PR', 'Purchase Request', 'PR', 'Purchase request document'],
            ['INV', 'Invoice', 'INV', 'Supplier or billing invoice'],
            ['CON', 'Contract', 'CON', 'Contract or agreement'],
            ['RPT', 'Report', 'RPT', 'Operational or administrative report'],
        ] as [$code, $name, $prefix, $description]) {
            $this->upsert('document_types', ['type_code' => $code], [
                'type_name'   => $name,
                'prefix'      => $prefix,
                'description' => $description,
                'active'      => 1,
            ]);
        }
    }

    private function seedRoutingActions(): void
    {
        foreach ([
            ['Reviewed', 'Document has been reviewed by the assigned user.', 'IN_PROGRESS', 0, 1],
            ['Processed', 'Required processing activity has been completed.', 'IN_PROGRESS', 0, 1],
            ['Endorsed', 'Document is endorsed to another section or responsible person.', 'IN_PROGRESS', 1, 1],
            ['Filed', 'Document has been filed and no further routing is required.', 'COMPLETED', 0, 1],
            ['Released', 'Document has been released to the intended recipient.', 'COMPLETED', 1, 1],
            ['Returned', 'Document is returned to the previous section or sender.', 'RETURNED', 1, 1],
            ['For Compliance', 'Document requires compliance or additional supporting information.', 'IN_PROGRESS', 1, 1],
            ['For Information', 'Document is routed for information only.', 'IN_PROGRESS', 0, 0],
        ] as [$name, $description, $statusCode, $requiresRemarks, $active]) {
            $this->upsert('routing_actions', ['action_name' => $name], [
                'description'         => $description,
                'resulting_status_id' => $this->id('document_statuses', 'status_code', $statusCode, 'status_id'),
                'requires_remarks'    => $requiresRemarks,
                'active'              => $active,
            ]);
        }

        // Keep the reference matrix aligned with the original prototype. The
        // Administrator remains a full operational override in application logic.
        $systemRoleIds = [];
        foreach (['Administrator', 'Receiving Personnel', 'Section Head', 'Personnel', 'Monitoring Officer'] as $roleName) {
            $systemRoleIds[] = $this->id('roles', 'role_name', $roleName, 'role_id');
        }
        $this->db->table('routing_action_roles')->whereIn('role_id', $systemRoleIds)->delete();

        $matrix = [
            'Reviewed'        => ['Section Head', 'Personnel'],
            'Processed'       => ['Section Head', 'Personnel'],
            'Endorsed'        => ['Section Head', 'Personnel'],
            'Filed'           => ['Section Head', 'Personnel'],
            'Released'        => ['Receiving Personnel', 'Section Head'],
            'Returned'        => ['Section Head', 'Personnel'],
            'For Compliance'  => ['Section Head', 'Personnel'],
            'For Information' => ['Section Head', 'Personnel', 'Monitoring Officer'],
        ];

        foreach ($matrix as $actionName => $roleNames) {
            foreach ($roleNames as $roleName) {
                $this->upsert('routing_action_roles', [
                    'action_id' => $this->id('routing_actions', 'action_name', $actionName, 'action_id'),
                    'role_id'   => $this->id('roles', 'role_name', $roleName, 'role_id'),
                ], []);
            }
        }
    }

    private function seedSafeConfiguration(string $adminId): void
    {
        if ($this->db->table('email_settings')->countAllResults() === 0) {
            $this->db->table('email_settings')->insert([
                'smtp_server'        => 'smtp.example.local',
                'smtp_port'          => 587,
                'encryption_type'    => 'STARTTLS',
                'smtp_username'      => null,
                'encrypted_password' => null,
                'sender_email'       => 'noreply@example.local',
                'sender_name'        => 'Document Tracking System',
                'subject_template'   => 'Document Update: {{document_control_number}}',
                'body_template'      => 'There is an update for document {{document_control_number}}.',
                'retry_attempts'     => 3,
                'enabled'            => 0,
                'updated_by'         => $adminId,
            ]);
        }

        if ($this->db->table('telegram_settings')->countAllResults() === 0) {
            $this->db->table('telegram_settings')->insert([
                'encrypted_bot_token'       => 'DISABLED_NOT_CONFIGURED',
                'bot_username'              => 'not_configured',
                'enabled'                   => 0,
                'notify_initial_assignment' => 1,
                'notify_routing'            => 1,
                'notify_reassignment'       => 1,
                'retry_attempts'             => 3,
                'updated_by'                 => $adminId,
            ]);
        }
    }

    /** @param array<string, mixed> $permission */
    private function roleAllows(string $roleName, array $permission): bool
    {
        $module = $permission['module_name'];
        $action = $permission['action_name'];

        return match ($roleName) {
            'Receiving Personnel' => ($module === 'Receiving' && in_array($action, ['VIEW', 'CREATE', 'UPDATE'], true))
                || (in_array($module, ['General Inbox', 'Document Details'], true) && $action === 'VIEW')
                || ($module === 'Document Routing' && in_array($action, ['VIEW', 'ROUTE'], true)),
            'Section Head' => (in_array($module, ['General Inbox', 'Document Details', 'Monitoring', 'Document Archive'], true) && $action === 'VIEW')
                || ($module === 'Document Routing' && in_array($action, ['VIEW', 'ROUTE'], true))
                || ($module === 'Reports' && in_array($action, ['VIEW', 'EXPORT'], true)),
            'Personnel' => (in_array($module, ['General Inbox', 'Document Details'], true) && $action === 'VIEW')
                || ($module === 'Document Routing' && in_array($action, ['VIEW', 'ROUTE'], true)),
            'Monitoring Officer' => (in_array($module, ['Document Details', 'Monitoring', 'Document Archive'], true) && $action === 'VIEW')
                || ($module === 'Reports' && in_array($action, ['VIEW', 'EXPORT'], true)),
            default => false,
        };
    }

    /** @param array<string, mixed> $where @param array<string, mixed> $data */
    private function upsert(string $table, array $where, array $data): void
    {
        $builder = $this->db->table($table);
        foreach ($where as $column => $value) {
            $builder->where($column, $value);
        }

        if ($builder->countAllResults() > 0) {
            if ($data !== []) {
                $builder = $this->db->table($table);
                foreach ($where as $column => $value) {
                    $builder->where($column, $value);
                }
                $builder->update($data);
            }
            return;
        }

        $this->db->table($table)->insert(array_merge($where, $data));
    }

    private function id(string $table, string $lookupColumn, string $lookupValue, string $idColumn): string
    {
        $row = $this->db->table($table)
            ->select($idColumn)
            ->where($lookupColumn, $lookupValue)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw new RuntimeException("Missing required reference: {$table}.{$lookupColumn}={$lookupValue}");
        }

        return (string) $row[$idColumn];
    }
}
