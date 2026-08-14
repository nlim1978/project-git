<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentArchivePermission extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
DECLARE @archive_permission UNIQUEIDENTIFIER;

SELECT @archive_permission = permission_id
FROM dbo.permissions
WHERE module_name = N'Document Archive'
  AND page_name = N'Archive'
  AND action_name = N'VIEW';

IF @archive_permission IS NULL
BEGIN
    SET @archive_permission = NEWID();
    INSERT INTO dbo.permissions (permission_id, module_name, page_name, action_name)
    VALUES (@archive_permission, N'Document Archive', N'Archive', N'VIEW');
END;

INSERT INTO dbo.role_permissions (role_id, permission_id, allowed)
SELECT DISTINCT rp.role_id, @archive_permission, 1
FROM dbo.role_permissions AS rp
INNER JOIN dbo.permissions AS p ON p.permission_id = rp.permission_id
WHERE p.module_name = N'Monitoring'
  AND p.page_name = N'Monitoring'
  AND p.action_name = N'VIEW'
  AND rp.allowed = 1
  AND NOT EXISTS (
      SELECT 1 FROM dbo.role_permissions existing
      WHERE existing.role_id = rp.role_id
        AND existing.permission_id = @archive_permission
  );
SQL);
    }

    public function down(): void
    {
        $this->db->query(<<<'SQL'
DECLARE @archive_permission UNIQUEIDENTIFIER;
SELECT @archive_permission = permission_id
FROM dbo.permissions
WHERE module_name = N'Document Archive'
  AND page_name = N'Archive'
  AND action_name = N'VIEW';

IF @archive_permission IS NOT NULL
BEGIN
    DELETE FROM dbo.role_permissions WHERE permission_id = @archive_permission;
    DELETE FROM dbo.permissions WHERE permission_id = @archive_permission;
END;
SQL);
    }
}
