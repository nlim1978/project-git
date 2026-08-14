<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAuditTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'audit_id'    => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'user_id'     => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'document_id' => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'module_name' => ['type' => 'NVARCHAR', 'constraint' => 100],
            'action_name' => ['type' => 'NVARCHAR', 'constraint' => 100],
            'description' => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            // SQL Server 2014 stores JSON as text; ISJSON is SQL Server 2016+.
            'old_value'   => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'new_value'   => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'browser'     => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'occurred_at' => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('audit_id', 'PK_audit_logs');
        $this->forge->addKey(['user_id', 'occurred_at'], false, false, 'IX_audit_logs_user');
        $this->forge->addKey(['document_id', 'occurred_at'], false, false, 'IX_audit_logs_document');
        $this->forge->addKey(['module_name', 'action_name', 'occurred_at'], false, false, 'IX_audit_logs_module');
        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_audit_logs_user');
        $this->forge->addForeignKey('document_id', 'documents', 'document_id', 'NO ACTION', 'NO ACTION', 'FK_audit_logs_document');
        $this->forge->createTable('audit_logs', true);

        $this->createBusinessRuleTriggers();
    }

    public function down(): void
    {
        $this->db->query('DROP TRIGGER [dbo].[trg_audit_logs_append_only]');
        $this->db->query('DROP TRIGGER [dbo].[trg_routing_history_append_only]');
        $this->db->query('DROP TRIGGER [dbo].[trg_routing_history_insert]');
        $this->db->query('DROP TRIGGER [dbo].[trg_validate_document_assignment]');
        $this->forge->dropTable('audit_logs');
    }

    private function createBusinessRuleTriggers(): void
    {
        $this->db->query(<<<'SQL'
CREATE TRIGGER [dbo].[trg_validate_document_assignment]
ON [dbo].[documents]
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        WHERE i.initial_responsible_user_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM dbo.user_sections AS us
              WHERE us.user_id = i.initial_responsible_user_id
                AND us.section_id = i.initial_section_id
          )
    )
    BEGIN
        RAISERROR('Initial responsible user must belong to the initial section.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        WHERE i.current_responsible_user_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM dbo.user_sections AS us
              WHERE us.user_id = i.current_responsible_user_id
                AND us.section_id = i.current_section_id
          )
    )
    BEGIN
        RAISERROR('Current responsible user must belong to the current section.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;
END;
SQL);

        $this->db->query(<<<'SQL'
CREATE TRIGGER [dbo].[trg_routing_history_insert]
ON [dbo].[routing_history]
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT i.document_id FROM inserted AS i
        GROUP BY i.document_id HAVING COUNT(*) > 1
    )
    BEGIN
        RAISERROR('Only one routing event per document may be inserted at a time.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        WHERE i.destination_user_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM dbo.user_sections AS us
              WHERE us.user_id = i.destination_user_id
                AND us.section_id = i.destination_section_id
          )
    )
    BEGIN
        RAISERROR('Destination responsible user must belong to the destination section.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        INNER JOIN dbo.documents AS d ON d.document_id = i.document_id
        INNER JOIN dbo.document_statuses AS ds ON ds.status_id = d.status_id
        WHERE ds.is_terminal = 1
    )
    BEGIN
        RAISERROR('Completed/terminal documents cannot be routed.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        LEFT JOIN dbo.routing_actions AS ra
            ON ra.action_id = i.action_id AND ra.active = 1
        WHERE i.action_id IS NOT NULL AND ra.action_id IS NULL
    )
    BEGIN
        RAISERROR('Routing action is missing or inactive.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        INNER JOIN dbo.routing_actions AS ra ON ra.action_id = i.action_id
        WHERE i.action_id IS NOT NULL
          AND ra.resulting_status_id <> i.resulting_status_id
    )
    BEGIN
        RAISERROR('Routing resulting status does not match the selected action.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    IF EXISTS (
        SELECT 1
        FROM inserted AS i
        INNER JOIN dbo.routing_actions AS ra ON ra.action_id = i.action_id
        WHERE i.action_id IS NOT NULL
          AND ra.requires_remarks = 1
          AND NULLIF(LTRIM(RTRIM(i.remarks)), N'') IS NULL
    )
    BEGIN
        RAISERROR('Remarks are required for the selected routing action.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END;

    UPDATE d
       SET d.current_section_id = i.destination_section_id,
           d.current_responsible_user_id = i.destination_user_id,
           d.status_id = i.resulting_status_id,
           d.updated_at = i.routed_at
    FROM dbo.documents AS d
    INNER JOIN inserted AS i ON i.document_id = d.document_id;
END;
SQL);

        $this->db->query(<<<'SQL'
CREATE TRIGGER [dbo].[trg_routing_history_append_only]
ON [dbo].[routing_history]
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    RAISERROR('routing_history records are append-only and cannot be updated or deleted.', 16, 1);
END;
SQL);

        $this->db->query(<<<'SQL'
CREATE TRIGGER [dbo].[trg_audit_logs_append_only]
ON [dbo].[audit_logs]
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    RAISERROR('audit_logs records are append-only and cannot be updated or deleted.', 16, 1);
END;
SQL);
    }
}
