<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateNotificationTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'notification_id'   => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'document_id'       => ['type' => 'UNIQUEIDENTIFIER'],
            'routing_id'        => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'recipient_user_id' => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'recipient_address' => ['type' => 'NVARCHAR', 'constraint' => 255, 'null' => true],
            'notification_channel' => ['type' => 'NVARCHAR', 'constraint' => 20],
            'notification_type' => ['type' => 'NVARCHAR', 'constraint' => 50],
            'status'            => ['type' => 'NVARCHAR', 'constraint' => 20, 'default' => 'Pending'],
            'attempt_count'     => ['type' => 'INT', 'default' => 0],
            'sent_at'           => ['type' => 'DATETIME2', 'null' => true],
            'error_message'     => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'created_at'        => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('notification_id', 'PK_notification_logs');
        $this->forge->addKey('document_id', false, false, 'IX_notification_logs_document');
        $this->forge->addKey('routing_id', false, false, 'IX_notification_logs_routing');
        $this->forge->addKey(['status', 'notification_channel'], false, false, 'IX_notification_logs_status_channel');
        $this->forge->addForeignKey('document_id', 'documents', 'document_id', 'NO ACTION', 'NO ACTION', 'FK_notification_logs_document');
        $this->forge->addForeignKey('routing_id', 'routing_history', 'routing_id', 'NO ACTION', 'NO ACTION', 'FK_notification_logs_routing');
        $this->forge->addForeignKey('recipient_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_notification_logs_recipient');
        $this->forge->createTable('notification_logs', true);

        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_notification_logs_channel' AND [parent_object_id] = OBJECT_ID(N'dbo.notification_logs')) "
            . "ALTER TABLE [dbo].[notification_logs] ADD CONSTRAINT [CK_notification_logs_channel] CHECK ([notification_channel] IN ('Email','Telegram'))"
        );
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_notification_logs_status' AND [parent_object_id] = OBJECT_ID(N'dbo.notification_logs')) "
            . "ALTER TABLE [dbo].[notification_logs] ADD CONSTRAINT [CK_notification_logs_status] CHECK ([status] IN ('Pending','Sent','Failed'))"
        );
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_notification_logs_attempt_count' AND [parent_object_id] = OBJECT_ID(N'dbo.notification_logs')) "
            . 'ALTER TABLE [dbo].[notification_logs] ADD CONSTRAINT [CK_notification_logs_attempt_count] CHECK ([attempt_count] >= 0)'
        );
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_notification_logs_recipient' AND [parent_object_id] = OBJECT_ID(N'dbo.notification_logs')) "
            . 'ALTER TABLE [dbo].[notification_logs] ADD CONSTRAINT [CK_notification_logs_recipient] CHECK ([recipient_user_id] IS NOT NULL OR [recipient_address] IS NOT NULL)'
        );

        $this->forge->addField([
            'email_setting_id'   => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'smtp_server'        => ['type' => 'NVARCHAR', 'constraint' => 255],
            'smtp_port'          => ['type' => 'INT'],
            'encryption_type'    => ['type' => 'NVARCHAR', 'constraint' => 20],
            'smtp_username'      => ['type' => 'NVARCHAR', 'constraint' => 255, 'null' => true],
            'encrypted_password' => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'sender_email'       => ['type' => 'NVARCHAR', 'constraint' => 254],
            'sender_name'        => ['type' => 'NVARCHAR', 'constraint' => 255],
            'subject_template'   => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            'body_template'      => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            'retry_attempts'     => ['type' => 'INT', 'default' => 3],
            'enabled'            => ['type' => 'BIT', 'default' => 1],
            'updated_by'         => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('email_setting_id', 'PK_email_settings');
        $this->forge->addForeignKey('updated_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_email_settings_updated_by');
        $this->forge->createTable('email_settings', true);
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_email_settings_port' AND [parent_object_id] = OBJECT_ID(N'dbo.email_settings')) "
            . 'ALTER TABLE [dbo].[email_settings] ADD CONSTRAINT [CK_email_settings_port] CHECK ([smtp_port] BETWEEN 1 AND 65535)'
        );
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_email_settings_encryption' AND [parent_object_id] = OBJECT_ID(N'dbo.email_settings')) "
            . "ALTER TABLE [dbo].[email_settings] ADD CONSTRAINT [CK_email_settings_encryption] CHECK ([encryption_type] IN ('None','SSL/TLS','STARTTLS'))"
        );
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_email_settings_retry' AND [parent_object_id] = OBJECT_ID(N'dbo.email_settings')) "
            . 'ALTER TABLE [dbo].[email_settings] ADD CONSTRAINT [CK_email_settings_retry] CHECK ([retry_attempts] BETWEEN 0 AND 10)'
        );

        $this->forge->addField([
            'telegram_setting_id'      => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'encrypted_bot_token'      => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            'bot_username'             => ['type' => 'NVARCHAR', 'constraint' => 100],
            'enabled'                  => ['type' => 'BIT', 'default' => 1],
            'notify_initial_assignment'=> ['type' => 'BIT', 'default' => 1],
            'notify_routing'           => ['type' => 'BIT', 'default' => 1],
            'notify_reassignment'      => ['type' => 'BIT', 'default' => 1],
            'retry_attempts'           => ['type' => 'INT', 'default' => 3],
            'updated_by'               => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'updated_at'               => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('telegram_setting_id', 'PK_telegram_settings');
        $this->forge->addForeignKey('updated_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_telegram_settings_updated_by');
        $this->forge->createTable('telegram_settings', true);
        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_telegram_settings_retry' AND [parent_object_id] = OBJECT_ID(N'dbo.telegram_settings')) "
            . 'ALTER TABLE [dbo].[telegram_settings] ADD CONSTRAINT [CK_telegram_settings_retry] CHECK ([retry_attempts] BETWEEN 0 AND 10)'
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('telegram_settings');
        $this->forge->dropTable('email_settings');
        $this->forge->dropTable('notification_logs');
    }
}
