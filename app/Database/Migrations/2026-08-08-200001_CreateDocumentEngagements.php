<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateDocumentEngagements extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'engagement_id'       => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'document_id'         => ['type' => 'UNIQUEIDENTIFIER'],
            'section_id'          => ['type' => 'UNIQUEIDENTIFIER'],
            'responsible_user_id' => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'confirmed_by'        => ['type' => 'UNIQUEIDENTIFIER'],
            'confirmed_at'        => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'last_seen_at'        => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'lock_expires_at'     => ['type' => 'DATETIME2'],
            'ended_at'            => ['type' => 'DATETIME2', 'null' => true],
            'ended_reason'        => ['type' => 'NVARCHAR', 'constraint' => 80, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('engagement_id', 'PK_document_engagements');
        $this->forge->addKey('document_id', false, false, 'IX_document_engagements_document');
        $this->forge->addKey('confirmed_by', false, false, 'IX_document_engagements_user');
        $this->forge->addKey('lock_expires_at', false, false, 'IX_document_engagements_lock_expiry');
        $this->forge->addForeignKey('document_id', 'documents', 'document_id', 'NO ACTION', 'NO ACTION', 'FK_document_engagements_document');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', 'NO ACTION', 'NO ACTION', 'FK_document_engagements_section');
        $this->forge->addForeignKey('responsible_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_document_engagements_responsible');
        $this->forge->addForeignKey('confirmed_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_document_engagements_confirmed_by');
        $this->forge->createTable('document_engagements', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_engagements');
    }
}
