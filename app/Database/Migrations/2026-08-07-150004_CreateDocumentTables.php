<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateDocumentTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'document_id'                 => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'receiving_number'            => ['type' => 'NVARCHAR', 'constraint' => 30],
            'document_control_number'     => ['type' => 'NVARCHAR', 'constraint' => 30],
            'qr_token'                    => ['type' => 'NVARCHAR', 'constraint' => 255],
            'document_type_id'            => ['type' => 'UNIQUEIDENTIFIER'],
            'subject'                     => ['type' => 'NVARCHAR', 'constraint' => 255],
            'description'                 => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            'sender_name'                 => ['type' => 'NVARCHAR', 'constraint' => 255],
            'sender_organization'         => ['type' => 'NVARCHAR', 'constraint' => 255, 'null' => true],
            'sender_email'                => ['type' => 'NVARCHAR', 'constraint' => 254],
            'sender_contact_number'       => ['type' => 'NVARCHAR', 'constraint' => 20, 'null' => true],
            'date_received'               => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'receiving_personnel_id'      => ['type' => 'UNIQUEIDENTIFIER'],
            'initial_section_id'          => ['type' => 'UNIQUEIDENTIFIER'],
            'initial_responsible_user_id' => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'current_section_id'          => ['type' => 'UNIQUEIDENTIFIER'],
            'current_responsible_user_id' => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'status_id'                   => ['type' => 'UNIQUEIDENTIFIER'],
            'remarks'                     => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'created_by'                  => ['type' => 'UNIQUEIDENTIFIER'],
            'created_at'                  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'                  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('document_id', 'PK_documents');
        $this->forge->addUniqueKey('receiving_number', 'UQ_documents_receiving_number');
        $this->forge->addUniqueKey('document_control_number', 'UQ_documents_control_number');
        $this->forge->addUniqueKey('qr_token', 'UQ_documents_qr_token');
        $this->forge->addKey('document_type_id', false, false, 'IX_documents_type');
        $this->forge->addKey('status_id', false, false, 'IX_documents_status');
        $this->forge->addKey('current_section_id', false, false, 'IX_documents_current_section');
        $this->forge->addKey('current_responsible_user_id', false, false, 'IX_documents_current_responsible');
        $this->forge->addKey('date_received', false, false, 'IX_documents_date_received');
        $this->forge->addForeignKey('document_type_id', 'document_types', 'document_type_id', 'NO ACTION', 'NO ACTION', 'FK_documents_type');
        $this->forge->addForeignKey('receiving_personnel_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_documents_receiving_personnel');
        $this->forge->addForeignKey('initial_section_id', 'sections', 'section_id', 'NO ACTION', 'NO ACTION', 'FK_documents_initial_section');
        $this->forge->addForeignKey('initial_responsible_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_documents_initial_responsible');
        $this->forge->addForeignKey('current_section_id', 'sections', 'section_id', 'NO ACTION', 'NO ACTION', 'FK_documents_current_section');
        $this->forge->addForeignKey('current_responsible_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_documents_current_responsible');
        $this->forge->addForeignKey('status_id', 'document_statuses', 'status_id', 'NO ACTION', 'NO ACTION', 'FK_documents_status');
        $this->forge->addForeignKey('created_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_documents_created_by');
        $this->forge->createTable('documents', true);

        $this->forge->addField([
            'attachment_id'     => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'document_id'       => ['type' => 'UNIQUEIDENTIFIER'],
            'file_name'         => ['type' => 'NVARCHAR', 'constraint' => 255],
            'original_file_name'=> ['type' => 'NVARCHAR', 'constraint' => 255],
            'file_extension'    => ['type' => 'NVARCHAR', 'constraint' => 20, 'null' => true],
            'file_size_bytes'   => ['type' => 'BIGINT'],
            'file_path'         => ['type' => 'NVARCHAR', 'constraint' => 'MAX'],
            'mime_type'         => ['type' => 'NVARCHAR', 'constraint' => 150, 'null' => true],
            'converted_to_pdf'  => ['type' => 'BIT', 'default' => 0],
            'uploaded_by'       => ['type' => 'UNIQUEIDENTIFIER'],
            'uploaded_at'       => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('attachment_id', 'PK_attachments');
        $this->forge->addKey('document_id', false, false, 'IX_attachments_document');
        $this->forge->addForeignKey('document_id', 'documents', 'document_id', 'NO ACTION', 'NO ACTION', 'FK_attachments_document');
        $this->forge->addForeignKey('uploaded_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_attachments_uploaded_by');
        $this->forge->createTable('attachments', true);

        $this->db->query(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE [name] = N'CK_attachments_file_size' AND [parent_object_id] = OBJECT_ID(N'dbo.attachments')) "
            . 'ALTER TABLE [dbo].[attachments] ADD CONSTRAINT [CK_attachments_file_size] CHECK ([file_size_bytes] >= 0)'
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('attachments');
        $this->forge->dropTable('documents');
    }
}
