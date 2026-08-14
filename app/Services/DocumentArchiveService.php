<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DocumentArchiveService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function normalizeFilters(array $input): array
    {
        $state = ucfirst(strtolower(trim((string) ($input['state'] ?? ''))));
        return [
            'q' => mb_substr(trim((string) ($input['q'] ?? '')), 0, 150),
            'state' => in_array($state, ['Filed', 'Released'], true) ? $state : '',
            'section' => $this->identifier($input['section'] ?? null),
            'type' => $this->identifier($input['type'] ?? null),
            'from' => $this->date($input['from'] ?? null),
            'to' => $this->date($input['to'] ?? null),
        ];
    }

    /** @param array<string, string> $filters @return list<array<string, mixed>> */
    public function search(array $filters, string $actorId): array
    {
        $scope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
        if ($scope->sectionIds() === []) {
            return [];
        }

        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.document_control_number, d.client_tracking_reference, d.subject, d.sender_name, d.sender_organization, d.date_received, dt.type_name, s.section_code, s.section_name, ra.action_name AS archive_state, rh.routed_at AS archived_at')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id')
            ->join('routing_history rh', 'rh.document_id = d.document_id')
            ->join('routing_actions ra', 'ra.action_id = rh.action_id')
            ->whereIn('ra.action_name', ['Filed', 'Released']);

        if ($scope->officeId() !== null) {
            $builder->where('dep.office_id', $scope->officeId());
        }
        if ($scope->sectionIds() !== null) {
            $builder->whereIn('d.current_section_id', $scope->sectionIds());
        }
        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('d.document_control_number', $filters['q'])
                ->orLike('d.receiving_number', $filters['q'])
                ->orLike('d.client_tracking_reference', $filters['q'])
                ->orLike('d.subject', $filters['q'])
                ->orLike('d.sender_name', $filters['q'])
                ->orLike('d.sender_organization', $filters['q'])
                ->groupEnd();
        }
        if ($filters['state'] !== '') {
            $builder->where('ra.action_name', $filters['state']);
        }
        if ($filters['section'] !== '') {
            $builder->where('d.current_section_id', $filters['section']);
        }
        if ($filters['type'] !== '') {
            $builder->where('d.document_type_id', $filters['type']);
        }
        if ($filters['from'] !== '') {
            $builder->where('rh.routed_at >=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $builder->where('rh.routed_at <', (new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00');
        }

        return $builder->orderBy('rh.routed_at', 'DESC')->limit(200)->get()->getResultArray();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function references(string $actorId): array
    {
        $scope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
        $sections = $this->db->table('sections s')->select('s.section_id, s.section_code, s.section_name')
            ->join('departments d', 'd.department_id = s.department_id')->where('s.active', 1);
        if ($scope->officeId() !== null) {
            $sections->where('d.office_id', $scope->officeId());
        }
        if ($scope->sectionIds() !== null && $scope->sectionIds() !== []) {
            $sections->whereIn('s.section_id', $scope->sectionIds());
        } elseif ($scope->sectionIds() === []) {
            $sections->where('1 = 0', null, false);
        }
        return [
            'sections' => $sections->orderBy('s.section_name')->get()->getResultArray(),
            'types' => $this->db->table('document_types')->select('document_type_id, type_name')->where('active', 1)->orderBy('type_name')->get()->getResultArray(),
        ];
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
