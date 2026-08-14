<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenDocumentConcurrency extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'SQLSRV' || ! $this->db->tableExists('document_engagements')) {
            return;
        }

        // Older installations may already contain duplicate un-ended rows.
        // Keep the newest one and close the rest before adding the invariant.
        $this->db->query(<<<'SQL'
;WITH active_engagements AS (
    SELECT engagement_id,
           ROW_NUMBER() OVER (
               PARTITION BY document_id
               ORDER BY confirmed_at DESC, engagement_id DESC
           ) AS row_number
    FROM dbo.document_engagements
    WHERE ended_at IS NULL
)
UPDATE de
   SET ended_at = SYSUTCDATETIME(),
       ended_reason = N'DUPLICATE_LOCK_CLEANUP'
FROM dbo.document_engagements AS de
INNER JOIN active_engagements AS ae ON ae.engagement_id = de.engagement_id
WHERE ae.row_number > 1;
SQL);

        $this->db->query(<<<'SQL'
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE [name] = N'UX_document_engagements_one_active'
      AND [object_id] = OBJECT_ID(N'dbo.document_engagements')
)
BEGIN
    CREATE UNIQUE INDEX [UX_document_engagements_one_active]
        ON [dbo].[document_engagements]([document_id])
        WHERE [ended_at] IS NULL;
END
SQL);
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'SQLSRV' || ! $this->db->tableExists('document_engagements')) {
            return;
        }

        $this->db->query(<<<'SQL'
IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE [name] = N'UX_document_engagements_one_active'
      AND [object_id] = OBJECT_ID(N'dbo.document_engagements')
)
BEGIN
    DROP INDEX [UX_document_engagements_one_active] ON [dbo].[document_engagements];
END
SQL);
    }
}
