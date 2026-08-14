<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** SQL Server concurrency boundary for document mutations. */
final class DocumentConcurrencyService extends BaseService
{
    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
    }

    /** @return array<string,mixed>|null */
    public function lockState(string $documentId): ?array
    {
        return $this->db->query(
            'SELECT d.document_id, d.document_control_number, d.receiving_personnel_id, d.initial_section_id, d.current_section_id, '
            . 'd.current_responsible_user_id, d.status_id, d.updated_at, ds.is_terminal '
            . 'FROM dbo.documents AS d WITH (UPDLOCK, HOLDLOCK) '
            . 'INNER JOIN dbo.document_statuses AS ds ON ds.status_id = d.status_id '
            . 'WHERE d.document_id = ?',
            [$documentId]
        )->getRowArray();
    }

    /** @param array<string,mixed> $lockedState */
    public function assertExpectedVersion(array $lockedState, mixed $expectedVersion): void
    {
        $expected = trim((string) ($expectedVersion ?? ''));
        $actual = trim((string) ($lockedState['updated_at'] ?? ''));
        if ($expected === '' || $actual === '' || ! hash_equals($actual, $expected)) {
            throw new RuntimeException('This document changed after you opened it. Reload the page and review the latest state before trying again.');
        }
    }
}
