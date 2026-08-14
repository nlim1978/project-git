<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateDocumentReferenceTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'document_type_id' => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'type_code'        => ['type' => 'NVARCHAR', 'constraint' => 20],
            'type_name'        => ['type' => 'NVARCHAR', 'constraint' => 100],
            'prefix'           => ['type' => 'NVARCHAR', 'constraint' => 20],
            'description'      => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'active'           => ['type' => 'BIT', 'default' => 1],
            'created_at'       => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'       => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('document_type_id', 'PK_document_types');
        $this->forge->addUniqueKey('type_code', 'UQ_document_types_code');
        $this->forge->addUniqueKey('type_name', 'UQ_document_types_name');
        $this->forge->createTable('document_types', true);

        $this->forge->addField([
            'status_id'   => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'status_code' => ['type' => 'NVARCHAR', 'constraint' => 30],
            'status_name' => ['type' => 'NVARCHAR', 'constraint' => 50],
            'is_terminal' => ['type' => 'BIT', 'default' => 0],
            'active'      => ['type' => 'BIT', 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('status_id', 'PK_document_statuses');
        $this->forge->addUniqueKey('status_code', 'UQ_document_statuses_code');
        $this->forge->addUniqueKey('status_name', 'UQ_document_statuses_name');
        $this->forge->createTable('document_statuses', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('document_statuses');
        $this->forge->dropTable('document_types');
    }
}
