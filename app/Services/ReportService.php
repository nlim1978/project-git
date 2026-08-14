<?php

namespace App\Services;

use App\Models\ReportModel;

class ReportService
{
    private const REPORT_TYPES = [
        'Receiving Report',
        'Routing Report',
        'Status Report',
        'User Activity Report',
        'Section Report',
        'Document Type Report',
        'Action Taken Report',
    ];

    private ReportModel $reports;

    public function __construct()
    {
        $this->reports = new ReportModel();
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function normalizeFilters(array $input): array
    {
        $reportType = trim((string) ($input['report_type'] ?? ''));
        if (! in_array($reportType, self::REPORT_TYPES, true)) {
            $reportType = 'Receiving Report';
        }

        $action = trim((string) ($input['action'] ?? ''));
        if (! in_array($action, ['RECEIVED', 'ROUTED'], true)) {
            $action = $this->identifier($action);
        }

        return [
            'report_type' => $reportType,
            'from' => $this->date($input['from'] ?? null),
            'to' => $this->date($input['to'] ?? null),
            'section' => $this->identifier($input['section'] ?? null),
            'user' => $this->identifier($input['user'] ?? null),
            'status' => $this->identifier($input['status'] ?? null),
            'type' => $this->identifier($input['type'] ?? null),
            'action' => $action,
        ];
    }

    /** @param array<string, string> $filters @return array<int, array<string, mixed>> */
    public function records(array $filters, string $actorId): array
    {
        $scope = new OrganizationScopeService();
        return $this->reports->records($filters, $scope->documentDataScope($actorId));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function references(string $actorId): array
    {
        $scope = new OrganizationScopeService();
        return $this->reports->references($scope->documentDataScope($actorId));
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, int> */
    public function summary(array $records): array
    {
        $summary = ['total' => count($records), 'received' => 0, 'in_progress' => 0, 'completed' => 0];
        foreach ($records as $record) {
            if ($record['status_name'] === 'Received') {
                $summary['received']++;
            } elseif ($record['status_name'] === 'In Progress') {
                $summary['in_progress']++;
            } elseif ($record['status_name'] === 'Completed') {
                $summary['completed']++;
            }
        }
        return $summary;
    }

    /** @param array<int, array<string, mixed>> $records */
    public function csv(array $records): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Document Number', 'Receiving Number', 'Date Received', 'Document Type', 'Subject', 'Sender', 'Current Section', 'Responsible Person', 'Status', 'Latest Action', 'Last Updated']);
        foreach ($records as $record) {
            $responsible = $record['responsible_first_name']
                ? $record['responsible_first_name'] . ' ' . $record['responsible_last_name']
                : 'Section inbox / unassigned';
            fputcsv($stream, [
                $record['document_control_number'],
                $record['receiving_number'],
                $record['date_received'],
                $record['type_name'],
                $record['subject'],
                $record['sender_name'],
                $record['section_name'],
                $responsible,
                $record['status_name'],
                $record['latest_action'],
                $record['updated_at'],
            ]);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $content === false ? '' : $content;
    }

    /** @return array<int, string> */
    public function reportTypes(): array
    {
        return self::REPORT_TYPES;
    }

    private function identifier(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return preg_match('/^[0-9A-Fa-f-]{36}$/', $value) === 1 ? $value : '';
    }

    private function date(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }
}
