<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RestrictSectionHeadReceiving extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
DELETE rp
FROM dbo.role_permissions AS rp
INNER JOIN dbo.roles AS r ON r.role_id = rp.role_id
INNER JOIN dbo.permissions AS p ON p.permission_id = rp.permission_id
WHERE r.role_name = N'Section Head'
  AND r.role_type = N'System'
  AND p.module_name = N'Receiving';
SQL);
    }

    public function down(): void
    {
        // Intentionally not restored. Receiving access is not part of the
        // Section Head system-role boundary and may have been granted manually.
    }
}
