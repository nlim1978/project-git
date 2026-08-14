<?php

namespace App\Models;

use App\Policies\DocumentDataScope;
use CodeIgniter\Model;

class DocumentMonitoringModel extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'document_id';
    protected $returnType = 'array';

    /** @param array<string, string> $filters @return array<int, array<string, mixed>> */
    public function search(array $filters, DocumentDataScope $scope): array
    {
        $sectionIds = $scope->sectionIds();
        if ($sectionIds === []) {
            return [];
        }
        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.document_control_number, d.receiving_number, d.subject, d.description, d.current_responsible_user_id, ds.status_name, ds.is_terminal, s.section_code, s.section_name, u.first_name AS responsible_first_name, u.last_name AS responsible_last_name')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id')
            ->join('users u', 'u.user_id = d.current_responsible_user_id', 'left');

        if ($scope->officeId() !== null) {
            $builder->where('dep.office_id', $scope->officeId());
        }
        if ($sectionIds !== null) {
            $builder->whereIn('d.current_section_id', $sectionIds);
        }

        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('d.document_control_number', $filters['q'])
                ->orLike('d.receiving_number', $filters['q'])
                ->orLike('d.subject', $filters['q'])
                ->orLike('d.sender_name', $filters['q'])
                ->groupEnd();
        }
        if ($filters['section'] !== '') {
            $builder->where('d.current_section_id', $filters['section']);
        }
        if ($filters['person'] !== '') {
            $builder->where('d.current_responsible_user_id', $filters['person']);
        }
        if ($filters['status'] !== '') {
            $builder->where('d.status_id', $filters['status']);
        }
        if ($filters['type'] !== '') {
            $builder->where('d.document_type_id', $filters['type']);
        }
        if ($filters['from'] !== '') {
            $builder->where('d.date_received >=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $nextDay = (new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
            $builder->where('d.date_received <', $nextDay . ' 00:00:00');
        }

        return $builder->orderBy('d.updated_at', 'DESC')->limit(200)->get()->getResultArray();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function references(DocumentDataScope $scope): array
    {
        $sectionIds = $scope->sectionIds();
        $sections = $this->db->table('sections s')->select('s.section_id, s.section_code, s.section_name')
            ->join('departments d', 'd.department_id = s.department_id')->where('s.active', 1);
        $people = $this->db->table('users u')->distinct()->select('u.user_id, u.employee_id, u.first_name, u.last_name')
            ->join('user_sections us', 'us.user_id = u.user_id')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')->where('u.account_status', 'Active');
        if ($scope->officeId() !== null) {
            $sections->where('d.office_id', $scope->officeId());
            $people->where('d.office_id', $scope->officeId());
        }
        if ($sectionIds !== null && $sectionIds !== []) {
            $sections->whereIn('s.section_id', $sectionIds);
            $people->whereIn('s.section_id', $sectionIds);
        } elseif ($sectionIds === []) {
            $sections->where('1 = 0', null, false);
            $people->where('1 = 0', null, false);
        }
        return [
            'sections' => $sections->orderBy('s.section_name')->get()->getResultArray(),
            'people' => $people->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray(),
            'statuses' => $this->db->table('document_statuses')->select('status_id, status_name')->where('active', 1)->orderBy('status_name')->get()->getResultArray(),
            'types' => $this->db->table('document_types')->select('document_type_id, type_name')->where('active', 1)->orderBy('type_name')->get()->getResultArray(),
        ];
    }
}
