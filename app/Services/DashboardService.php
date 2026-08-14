<?php

namespace App\Services;

class DashboardService extends BaseService
{
    public const ATTENTION_DAYS = 5;

    /** @return array{department_id:string,department_code:string,department_name:string}|null */
    public function departmentForUser(string $userId): ?array
    {
        $row = $this->db->table('user_sections us')
            ->select('d.department_id, d.department_code, d.department_name')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->join('offices o', 'o.office_id = d.office_id')
            ->where('us.user_id', $userId)
            ->where('s.active', 1)
            ->where('d.active', 1)
            ->where('o.active', 1)
            ->orderBy('us.is_primary', 'DESC')
            ->orderBy('s.section_name', 'ASC')
            ->limit(1)
            ->get()->getRowArray();

        return $row ?: null;
    }

    /** @return array{key:string,label:string,from:string,to:string} */
    public function period(string $requested): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($requested === 'last_30_days') {
            return [
                'key' => 'last_30_days',
                'label' => 'Last 30 days',
                'from' => $now->modify('-29 days')->setTime(0, 0)->format('Y-m-d H:i:s'),
                'to' => $now->modify('+1 day')->setTime(0, 0)->format('Y-m-d H:i:s'),
            ];
        }

        if ($requested === 'last_90_days') {
            return [
                'key' => 'last_90_days',
                'label' => 'Last 90 days',
                'from' => $now->modify('-89 days')->setTime(0, 0)->format('Y-m-d H:i:s'),
                'to' => $now->modify('+1 day')->setTime(0, 0)->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'key' => 'this_month',
            'label' => 'This month',
            'from' => $now->modify('first day of this month')->setTime(0, 0)->format('Y-m-d H:i:s'),
            'to' => $now->modify('first day of next month')->setTime(0, 0)->format('Y-m-d H:i:s'),
        ];
    }

    /** @param array{from:string,to:string} $period @return array{needs_attention:int,received:int,in_progress:int,completed:int} */
    public function departmentSummary(string $departmentId, array $period, array $sectionIds = []): array
    {
        $scopeParams = array_merge([$departmentId], $sectionIds);
        $current = $this->db->query(
            $this->departmentSql(
                "SELECT
                    SUM(CASE WHEN ds.is_terminal = 0
                        AND DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) >= " . self::ATTENTION_DAYS . "
                        THEN 1 ELSE 0 END) AS needs_attention,
                    SUM(CASE WHEN ds.status_code = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress",
                count($sectionIds)
            ),
            $scopeParams
        )->getRowArray() ?: [];

        $receivedRow = $this->db->query(
            $this->departmentSql('SELECT COUNT(*) AS record_count', count($sectionIds))
                . ' AND d.date_received >= ? AND d.date_received < ?',
            array_merge($scopeParams, [$period['from'], $period['to']])
        )->getRowArray() ?: [];

        $completedRow = $this->db->query(
            $this->departmentSql('SELECT COUNT(*) AS record_count', count($sectionIds))
                . ' AND ds.is_terminal = 1'
                . ' AND COALESCE(lr.last_routed_at, d.date_received) >= ?'
                . ' AND COALESCE(lr.last_routed_at, d.date_received) < ?',
            array_merge($scopeParams, [$period['from'], $period['to']])
        )->getRowArray() ?: [];

        return [
            'needs_attention' => (int) ($current['needs_attention'] ?? 0),
            'received' => (int) ($receivedRow['record_count'] ?? 0),
            'in_progress' => (int) ($current['in_progress'] ?? 0),
            'completed' => (int) ($completedRow['record_count'] ?? 0),
        ];
    }

    /** @return array{fresh:int,watch:int,attention:int,critical:int} */
    public function agingBuckets(string $departmentId, array $sectionIds = []): array
    {
        $row = $this->db->query(
            $this->departmentSql(
                'SELECT
                    SUM(CASE WHEN DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) <= 2 THEN 1 ELSE 0 END) AS fresh,
                    SUM(CASE WHEN DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) BETWEEN 3 AND 4 THEN 1 ELSE 0 END) AS watch,
                    SUM(CASE WHEN DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) BETWEEN 5 AND 9 THEN 1 ELSE 0 END) AS attention,
                    SUM(CASE WHEN DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) >= 10 THEN 1 ELSE 0 END) AS critical',
                count($sectionIds)
            ) . ' AND ds.is_terminal = 0',
            array_merge([$departmentId], $sectionIds)
        )->getRowArray() ?: [];

        return [
            'fresh' => (int) ($row['fresh'] ?? 0),
            'watch' => (int) ($row['watch'] ?? 0),
            'attention' => (int) ($row['attention'] ?? 0),
            'critical' => (int) ($row['critical'] ?? 0),
        ];
    }

    /** @param array{from:string,to:string} $period @return array<int,array{type_name:string,document_count:int}> */
    public function topDocumentTypes(string $departmentId, array $period, int $limit = 3, array $sectionIds = []): array
    {
        $safeLimit = max(1, min($limit, 3));
        $sectionClause = $sectionIds === [] ? '' : ' AND s.section_id IN (' . implode(',', array_fill(0, count($sectionIds), '?')) . ')';
        $rows = $this->db->query(
            "SELECT TOP ({$safeLimit}) dt.type_name, COUNT(*) AS document_count
             FROM documents d
             INNER JOIN sections s ON s.section_id = d.current_section_id
             INNER JOIN document_types dt ON dt.document_type_id = d.document_type_id
             WHERE s.department_id = ?
               {$sectionClause}
               AND d.date_received >= ? AND d.date_received < ?
             GROUP BY dt.document_type_id, dt.type_name
             ORDER BY COUNT(*) DESC, dt.type_name ASC",
            array_merge([$departmentId], $sectionIds, [$period['from'], $period['to']])
        )->getResultArray();

        return array_map(static fn (array $row): array => [
            'type_name' => (string) $row['type_name'],
            'document_count' => (int) $row['document_count'],
        ], $rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function attentionDocuments(string $departmentId, int $limit = 6, array $sectionIds = []): array
    {
        $safeLimit = max(1, min($limit, 10));
        return $this->db->query(
            $this->departmentSql(
                "SELECT TOP ({$safeLimit})
                    d.document_id, d.document_control_number, d.subject,
                    ds.status_name, s.section_name,
                    DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) AS age_days",
                count($sectionIds)
            )
                . ' AND ds.is_terminal = 0'
                . ' AND DATEDIFF(day, COALESCE(lr.last_routed_at, d.date_received), SYSUTCDATETIME()) >= ' . self::ATTENTION_DAYS
                . ' ORDER BY age_days DESC, d.subject ASC',
            array_merge([$departmentId], $sectionIds)
        )->getResultArray();
    }

    private function departmentSql(string $select, int $sectionCount = 0): string
    {
        $sectionClause = $sectionCount > 0
            ? ' AND s.section_id IN (' . implode(',', array_fill(0, $sectionCount, '?')) . ')'
            : '';
        return $select . '
            FROM documents d
            INNER JOIN document_statuses ds ON ds.status_id = d.status_id
            INNER JOIN sections s ON s.section_id = d.current_section_id
            LEFT JOIN (
                SELECT document_id, MAX(routed_at) AS last_routed_at
                FROM routing_history
                GROUP BY document_id
            ) lr ON lr.document_id = d.document_id
            WHERE s.department_id = ?' . $sectionClause;
    }
}
