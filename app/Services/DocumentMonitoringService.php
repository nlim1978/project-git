<?php

namespace App\Services;

use App\Models\DocumentMonitoringModel;

class DocumentMonitoringService
{
    private DocumentMonitoringModel $documents;

    public function __construct()
    {
        $this->documents = new DocumentMonitoringModel();
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function normalizeFilters(array $input): array
    {
        return [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 150),
            'section' => $this->identifier($input['section'] ?? null),
            'person' => $this->identifier($input['person'] ?? null),
            'status' => $this->identifier($input['status'] ?? null),
            'type' => $this->identifier($input['type'] ?? null),
            'from' => $this->date($input['from'] ?? null),
            'to' => $this->date($input['to'] ?? null),
        ];
    }

    /** @param array<string, string> $filters @return array<int, array<string, mixed>> */
    public function search(array $filters, string $actorId): array
    {
        $scope = new OrganizationScopeService();
        return $this->documents->search($filters, $scope->documentDataScope($actorId));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function references(string $actorId): array
    {
        $scope = new OrganizationScopeService();
        return $this->documents->references($scope->documentDataScope($actorId));
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
