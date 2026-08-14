<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAccessControlTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'user_id'             => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'employee_id'         => ['type' => 'NVARCHAR', 'constraint' => 20],
            'username'            => ['type' => 'NVARCHAR', 'constraint' => 50],
            'password_hash'       => ['type' => 'NVARCHAR', 'constraint' => 255],
            'first_name'          => ['type' => 'NVARCHAR', 'constraint' => 100],
            'middle_name'         => ['type' => 'NVARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'           => ['type' => 'NVARCHAR', 'constraint' => 100],
            'email'               => ['type' => 'NVARCHAR', 'constraint' => 150],
            'contact_number'      => ['type' => 'NVARCHAR', 'constraint' => 20, 'null' => true],
            'account_status'      => ['type' => 'NVARCHAR', 'constraint' => 20, 'default' => 'Active'],
            'last_login'          => ['type' => 'DATETIME2', 'null' => true],
            'password_changed_at' => ['type' => 'DATETIME2', 'null' => true],
            'telegram_chat_id'    => ['type' => 'NVARCHAR', 'constraint' => 100, 'null' => true],
            'telegram_username'   => ['type' => 'NVARCHAR', 'constraint' => 100, 'null' => true],
            'telegram_notification_enabled' => ['type' => 'BIT', 'default' => 1],
            'created_by'          => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'updated_by'          => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'created_at'          => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'          => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('user_id', 'PK_users');
        $this->forge->addUniqueKey('employee_id', 'UQ_users_employee_id');
        $this->forge->addUniqueKey('username', 'UQ_users_username');
        $this->forge->addUniqueKey('email', 'UQ_users_email');
        $this->forge->addKey('account_status', false, false, 'IX_users_status');
        $this->forge->addForeignKey('created_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_users_created_by');
        $this->forge->addForeignKey('updated_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_users_updated_by');
        $this->forge->createTable('users', true);

        // Complete the only circular organization relationship after users exists.
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE [name] = N'FK_sections_head_user' AND [parent_object_id] = OBJECT_ID(N'dbo.sections')) "
            . 'ALTER TABLE [dbo].[sections] ADD CONSTRAINT [FK_sections_head_user] '
            . 'FOREIGN KEY ([head_user_id]) REFERENCES [dbo].[users]([user_id])'
        );

        $this->forge->addField([
            'role_id'     => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'role_name'   => ['type' => 'NVARCHAR', 'constraint' => 100],
            'description' => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'role_type'   => ['type' => 'NVARCHAR', 'constraint' => 20, 'default' => 'Custom'],
            'active'      => ['type' => 'BIT', 'default' => 1],
            'created_at'  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('role_id', 'PK_roles');
        $this->forge->addUniqueKey('role_name', 'UQ_roles_name');
        $this->forge->createTable('roles', true);
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_roles_role_type' AND [parent_object_id] = OBJECT_ID(N'dbo.roles')) "
            . "ALTER TABLE [dbo].[roles] ADD CONSTRAINT [CK_roles_role_type] CHECK ([role_type] IN ('System','Custom'))"
        );

        $this->forge->addField([
            'permission_id' => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'module_name'   => ['type' => 'NVARCHAR', 'constraint' => 100],
            'page_name'     => ['type' => 'NVARCHAR', 'constraint' => 100, 'default' => ''],
            'action_name'   => ['type' => 'NVARCHAR', 'constraint' => 50],
            'description'   => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('permission_id', 'PK_permissions');
        $this->forge->addUniqueKey(['module_name', 'page_name', 'action_name'], 'UQ_permissions_scope');
        $this->forge->createTable('permissions', true);

        $this->forge->addField([
            'user_id'     => ['type' => 'UNIQUEIDENTIFIER'],
            'role_id'     => ['type' => 'UNIQUEIDENTIFIER'],
            'assigned_at' => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey(['user_id', 'role_id'], 'PK_user_roles');
        $this->forge->addKey('role_id', false, false, 'IX_user_roles_role');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'NO ACTION', 'CASCADE', 'FK_user_roles_user');
        $this->forge->addForeignKey('role_id', 'roles', 'role_id', 'NO ACTION', 'CASCADE', 'FK_user_roles_role');
        $this->forge->createTable('user_roles', true);

        $this->forge->addField([
            'user_id'       => ['type' => 'UNIQUEIDENTIFIER'],
            'section_id'    => ['type' => 'UNIQUEIDENTIFIER'],
            'is_primary'    => ['type' => 'BIT', 'default' => 0],
            'assigned_at'   => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey(['user_id', 'section_id'], 'PK_user_sections');
        $this->forge->addKey('section_id', false, false, 'IX_user_sections_section');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'NO ACTION', 'CASCADE', 'FK_user_sections_user');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', 'NO ACTION', 'CASCADE', 'FK_user_sections_section');
        $this->forge->createTable('user_sections', true);
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE [name] = N'UX_user_sections_one_primary' AND [object_id] = OBJECT_ID(N'dbo.user_sections')) "
            . 'CREATE UNIQUE INDEX [UX_user_sections_one_primary] ON [dbo].[user_sections]([user_id]) WHERE [is_primary] = 1'
        );

        $this->forge->addField([
            'role_id'       => ['type' => 'UNIQUEIDENTIFIER'],
            'permission_id' => ['type' => 'UNIQUEIDENTIFIER'],
            'allowed'       => ['type' => 'BIT', 'default' => 1],
        ]);
        $this->forge->addPrimaryKey(['role_id', 'permission_id'], 'PK_role_permissions');
        $this->forge->addKey('permission_id', false, false, 'IX_role_permissions_permission');
        $this->forge->addForeignKey('role_id', 'roles', 'role_id', 'NO ACTION', 'CASCADE', 'FK_role_permissions_role');
        $this->forge->addForeignKey('permission_id', 'permissions', 'permission_id', 'NO ACTION', 'CASCADE', 'FK_role_permissions_permission');
        $this->forge->createTable('role_permissions', true);

        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_users_account_status' AND [parent_object_id] = OBJECT_ID(N'dbo.users')) "
            . "ALTER TABLE [dbo].[users] ADD CONSTRAINT [CK_users_account_status] "
            . "CHECK ([account_status] IN ('Active','Inactive'))"
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('role_permissions');
        $this->forge->dropTable('user_sections');
        $this->forge->dropTable('user_roles');
        $this->forge->dropTable('permissions');
        $this->forge->dropTable('roles');
        $this->db->query('ALTER TABLE [dbo].[sections] DROP CONSTRAINT [FK_sections_head_user]');
        $this->forge->dropTable('users');
    }
}
