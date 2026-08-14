<?php

namespace App\Services;

use App\Models\AuditLogModel;

class AuditLogService
{
    public const PER_PAGE = 20;

    private AuditLogModel $auditLogs;

    public function __construct()
    {
        $this->auditLogs = new AuditLogModel();
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public function normalizeFilters(array $input): array
    {
        return [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 100),
            'user' => $this->userFilter($input['user'] ?? null),
            'module' => mb_substr(trim((string) ($input['module'] ?? '')), 0, 100),
            'action' => mb_substr(trim((string) ($input['action'] ?? '')), 0, 100),
            'from' => $this->date($input['from'] ?? null),
            'to' => $this->date($input['to'] ?? null),
        ];
    }

    /** @param array<string,string> $filters @return array{records:array<int,array<string,mixed>>,total:int,page:int,pages:int} */
    public function page(array $filters, int $requestedPage, string $actorId): array
    {
        $officeId = $this->officeScope($actorId);
        $page = max(1, $requestedPage);
        $result = $this->auditLogs->page($filters, $page, self::PER_PAGE, $officeId);
        $pages = max(1, (int) ceil($result['total'] / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
            $result = $this->auditLogs->page($filters, $page, self::PER_PAGE, $officeId);
        }
        return ['records' => $result['records'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages];
    }

    /** @return array{users:array<int,array<string,mixed>>,modules:array<int,array<string,mixed>>,actions:array<int,array<string,mixed>>} */
    public function references(string $actorId): array
    {
        return $this->auditLogs->references($this->officeScope($actorId));
    }

    /** @param array<string,string> $filters */
    public function csv(array $filters, string $actorId): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) return '';
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Audit ID', 'Date Time (UTC)', 'User', 'Employee ID', 'Module', 'Action', 'Description', 'Old Value', 'New Value', 'IP Address', 'Browser']);
        foreach ($this->auditLogs->export($filters, $this->officeScope($actorId)) as $record) {
            fputcsv($stream, [
                $record['audit_id'], $record['occurred_at'], $this->userName($record), $record['employee_id'] ?? '',
                $record['module_name'], $record['action_name'], $record['description'], $record['old_value'] ?? '',
                $record['new_value'] ?? '', $record['ip_address'] ?? '', $record['browser'] ?? '',
            ]);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $content === false ? '' : $content;
    }

    /** @param array<string,mixed> $record */
    private function userName(array $record): string
    {
        $name = trim((string) (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')));
        return $name !== '' ? $name : (($record['username'] ?? '') !== '' ? (string) $record['username'] : 'System');
    }

    private function officeScope(string $actorId): ?string
    {
        $scope = new OrganizationScopeService();
        return $scope->documentDataScope($actorId)->officeId();
    }

    private function userFilter(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '__system__') return $value;
        return preg_match('/^[0-9A-Fa-f-]{36}$/', $value) === 1 ? $value : '';
    }

    private function date(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : '';
    }
}
