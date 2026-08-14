<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddClientTrackingToken extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
IF COL_LENGTH(N'dbo.documents', N'client_tracking_token') IS NULL
BEGIN
    ALTER TABLE [dbo].[documents] ADD [client_tracking_token] VARCHAR(32) NULL;
END
SQL);

        // Backfill legacy rows with an opaque UUID-derived token. New rows use
        // PHP random_bytes(16); both provide an unguessable client reference.
        $this->db->query(<<<'SQL'
UPDATE [dbo].[documents]
   SET [client_tracking_token] = LOWER(REPLACE(CONVERT(VARCHAR(36), NEWID()), '-', ''))
 WHERE [client_tracking_token] IS NULL OR [client_tracking_token] = ''
SQL);

        $this->db->query(<<<'SQL'
IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE [object_id] = OBJECT_ID(N'dbo.documents')
      AND [name] = N'client_tracking_token'
      AND [is_nullable] = 1
)
BEGIN
    ALTER TABLE [dbo].[documents] ALTER COLUMN [client_tracking_token] VARCHAR(32) NOT NULL;
END
SQL);

        $this->db->query(<<<'SQL'
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE [object_id] = OBJECT_ID(N'dbo.documents')
      AND [name] = N'UQ_documents_client_tracking_token'
)
BEGIN
    CREATE UNIQUE INDEX [UQ_documents_client_tracking_token]
        ON [dbo].[documents] ([client_tracking_token]);
END
SQL);
    }

    public function down(): void
    {
        $this->db->query(<<<'SQL'
IF EXISTS (SELECT 1 FROM sys.indexes WHERE [object_id] = OBJECT_ID(N'dbo.documents') AND [name] = N'UQ_documents_client_tracking_token')
    DROP INDEX [UQ_documents_client_tracking_token] ON [dbo].[documents];
IF COL_LENGTH(N'dbo.documents', N'client_tracking_token') IS NOT NULL
    ALTER TABLE [dbo].[documents] DROP COLUMN [client_tracking_token];
SQL);
    }
}
