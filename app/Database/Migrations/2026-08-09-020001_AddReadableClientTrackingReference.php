<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReadableClientTrackingReference extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
IF COL_LENGTH(N'dbo.documents', N'client_tracking_reference') IS NULL
BEGIN
    ALTER TABLE [dbo].[documents] ADD [client_tracking_reference] VARCHAR(13) NULL;
END

IF OBJECT_ID(N'dbo.client_tracking_sequences', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[client_tracking_sequences] (
        [tracking_year] SMALLINT NOT NULL,
        [last_number] INT NOT NULL,
        CONSTRAINT [PK_client_tracking_sequences] PRIMARY KEY ([tracking_year]),
        CONSTRAINT [CK_client_tracking_sequences_range] CHECK ([last_number] BETWEEN 0 AND 9999)
    );
END
SQL);

        // Give legacy documents stable readable aliases in chronological order.
        $this->db->query(<<<'SQL'
IF EXISTS (
    SELECT 1
    FROM dbo.documents
    GROUP BY YEAR(date_received)
    HAVING COUNT_BIG(*) > 9999
)
    THROW 50001, 'A calendar year contains more than 9,999 documents; enlarge the tracking series before migration.', 1;

;WITH numbered AS (
    SELECT document_id,
           date_received,
           ROW_NUMBER() OVER (
               PARTITION BY YEAR(date_received)
               ORDER BY date_received, document_id
           ) AS annual_number
    FROM dbo.documents
    WHERE client_tracking_reference IS NULL OR client_tracking_reference = ''
)
UPDATE d
   SET client_tracking_reference = CONCAT(
       'TRK-',
       RIGHT('0' + CONVERT(VARCHAR(2), MONTH(n.date_received)), 2),
       RIGHT(CONVERT(VARCHAR(4), YEAR(n.date_received)), 2),
       '-',
       RIGHT('0000' + CONVERT(VARCHAR(4), n.annual_number), 4)
   )
FROM dbo.documents AS d
INNER JOIN numbered AS n ON n.document_id = d.document_id;

MERGE dbo.client_tracking_sequences WITH (HOLDLOCK) AS target
USING (
    SELECT YEAR(date_received) AS tracking_year, COUNT_BIG(*) AS last_number
    FROM dbo.documents
    GROUP BY YEAR(date_received)
) AS source
ON target.tracking_year = source.tracking_year
WHEN MATCHED THEN UPDATE SET last_number = CONVERT(INT, source.last_number)
WHEN NOT MATCHED THEN INSERT (tracking_year, last_number)
VALUES (source.tracking_year, CONVERT(INT, source.last_number));
SQL);

        $this->db->query(<<<'SQL'
IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE [object_id] = OBJECT_ID(N'dbo.documents')
      AND [name] = N'client_tracking_reference'
      AND [is_nullable] = 1
)
    ALTER TABLE [dbo].[documents] ALTER COLUMN [client_tracking_reference] VARCHAR(13) NOT NULL;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE [object_id] = OBJECT_ID(N'dbo.documents')
      AND [name] = N'UQ_documents_client_tracking_reference'
)
    CREATE UNIQUE INDEX [UQ_documents_client_tracking_reference]
        ON [dbo].[documents] ([client_tracking_reference]);
SQL);
    }

    public function down(): void
    {
        $this->db->query(<<<'SQL'
IF EXISTS (SELECT 1 FROM sys.indexes WHERE [object_id] = OBJECT_ID(N'dbo.documents') AND [name] = N'UQ_documents_client_tracking_reference')
    DROP INDEX [UQ_documents_client_tracking_reference] ON [dbo].[documents];
IF COL_LENGTH(N'dbo.documents', N'client_tracking_reference') IS NOT NULL
    ALTER TABLE [dbo].[documents] DROP COLUMN [client_tracking_reference];
IF OBJECT_ID(N'dbo.client_tracking_sequences', N'U') IS NOT NULL
    DROP TABLE [dbo].[client_tracking_sequences];
SQL);
    }
}
