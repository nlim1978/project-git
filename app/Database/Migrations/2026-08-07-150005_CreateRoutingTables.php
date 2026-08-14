<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateRoutingTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'action_id'           => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'action_name'         => ['type' => 'NVARCHAR', 'constraint' => 100],
            'description'         => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'resulting_status_id' => ['type' => 'UNIQUEIDENTIFIER'],
            'requires_remarks'    => ['type' => 'BIT', 'default' => 0],
            'active'              => ['type' => 'BIT', 'default' => 1],
            'created_at'          => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'updated_at'          => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
        ]);
        $this->forge->addPrimaryKey('action_id', 'PK_routing_actions');
        $this->forge->addUniqueKey('action_name', 'UQ_routing_actions_name');
        $this->forge->addKey('resulting_status_id', false, false, 'IX_routing_actions_status');
        $this->forge->addForeignKey('resulting_status_id', 'document_statuses', 'status_id', 'NO ACTION', 'NO ACTION', 'FK_routing_actions_status');
        $this->forge->createTable('routing_actions', true);

        $this->forge->addField([
            'action_id' => ['type' => 'UNIQUEIDENTIFIER'],
            'role_id'   => ['type' => 'UNIQUEIDENTIFIER'],
        ]);
        $this->forge->addPrimaryKey(['action_id', 'role_id'], 'PK_routing_action_roles');
        $this->forge->addKey('role_id', false, false, 'IX_routing_action_roles_role');
        $this->forge->addForeignKey('action_id', 'routing_actions', 'action_id', 'NO ACTION', 'CASCADE', 'FK_routing_action_roles_action');
        $this->forge->addForeignKey('role_id', 'roles', 'role_id', 'NO ACTION', 'CASCADE', 'FK_routing_action_roles_role');
        $this->forge->createTable('routing_action_roles', true);

        $this->forge->addField([
            'routing_id'             => ['type' => 'UNIQUEIDENTIFIER', 'default' => new RawSql('NEWID()')],
            'document_id'            => ['type' => 'UNIQUEIDENTIFIER'],
            'from_section_id'        => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'from_user_id'           => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'destination_section_id' => ['type' => 'UNIQUEIDENTIFIER'],
            'destination_user_id'    => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'action_id'              => ['type' => 'UNIQUEIDENTIFIER', 'null' => true],
            'resulting_status_id'    => ['type' => 'UNIQUEIDENTIFIER'],
            'remarks'                => ['type' => 'NVARCHAR', 'constraint' => 'MAX', 'null' => true],
            'routed_by'              => ['type' => 'UNIQUEIDENTIFIER'],
            'routed_at'              => ['type' => 'DATETIME2', 'default' => new RawSql('SYSUTCDATETIME()')],
            'is_reassigned'          => ['type' => 'BIT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('routing_id', 'PK_routing_history');
        $this->forge->addKey('document_id', false, false, 'IX_routing_history_document');
        $this->forge->addKey('destination_section_id', false, false, 'IX_routing_history_destination_section');
        $this->forge->addKey('destination_user_id', false, false, 'IX_routing_history_destination_user');
        $this->forge->addKey('routed_at', false, false, 'IX_routing_history_routed_at');
        $this->forge->addForeignKey('document_id', 'documents', 'document_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_document');
        $this->forge->addForeignKey('from_section_id', 'sections', 'section_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_from_section');
        $this->forge->addForeignKey('from_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_from_user');
        $this->forge->addForeignKey('destination_section_id', 'sections', 'section_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_destination_section');
        $this->forge->addForeignKey('destination_user_id', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_destination_user');
        $this->forge->addForeignKey('action_id', 'routing_actions', 'action_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_action');
        $this->forge->addForeignKey('resulting_status_id', 'document_statuses', 'status_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_status');
        $this->forge->addForeignKey('routed_by', 'users', 'user_id', 'NO ACTION', 'NO ACTION', 'FK_routing_history_routed_by');
        $this->forge->createTable('routing_history', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('routing_history');
        $this->forge->dropTable('routing_action_roles');
        $this->forge->dropTable('routing_actions');
    }
}
