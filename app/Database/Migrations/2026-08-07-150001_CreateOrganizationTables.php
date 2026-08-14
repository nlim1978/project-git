<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateOrganizationTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'office_id'   => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'office_code' => ['type' => 'NVARCHAR', 'constraint' => 20],
            'office_name' => ['type' => 'NVARCHAR', 'constraint' => 150],
            'active'      => ['type' => 'BIT', 'default' => 1],
            'created_at'  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'  => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('office_id', 'PK_offices');
        $this->forge->addUniqueKey('office_code', 'UQ_offices_code');
        $this->forge->createTable('offices', true);

        $this->forge->addField([
            'department_id'   => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'office_id'       => ['type' => 'UNIQUEIDENTIFIER'],
            'department_code' => ['type' => 'NVARCHAR', 'constraint' => 20],
            'department_name' => ['type' => 'NVARCHAR', 'constraint' => 150],
            'active'          => ['type' => 'BIT', 'default' => 1],
            'created_at'      => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'      => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('department_id', 'PK_departments');
        $this->forge->addUniqueKey('department_code', 'UQ_departments_code');
        $this->forge->addKey('office_id', false, false, 'IX_departments_office');
        $this->forge->addForeignKey('office_id', 'offices', 'office_id', 'NO ACTION', 'NO ACTION', 'FK_departments_office');
        $this->forge->createTable('departments', true);

        $this->forge->addField([
            'section_id'     => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'department_id'  => ['type' => 'UNIQUEIDENTIFIER'],
            'head_user_id'   => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'section_code'   => ['type' => 'NVARCHAR', 'constraint' => 20],
            'section_name'   => ['type' => 'NVARCHAR', 'constraint' => 150],
            'active'         => ['type' => 'BIT', 'default' => 1],
            'created_at'     => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'     => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('section_id', 'PK_sections');
        $this->forge->addUniqueKey('section_code', 'UQ_sections_code');
        $this->forge->addKey('department_id', false, false, 'IX_sections_department');
        $this->forge->addKey('head_user_id', false, false, 'IX_sections_head_user');
        $this->forge->addForeignKey('department_id', 'departments', 'department_id', 'NO ACTION', 'NO ACTION', 'FK_sections_department');
        $this->forge->createTable('sections', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('sections');
        $this->forge->dropTable('departments');
        $this->forge->dropTable('offices');
    }
}
