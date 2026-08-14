<?php

namespace App\Models;

use App\Policies\DocumentDataScope;
use CodeIgniter\Model;

class ReportModel extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'document_id';
    protected $returnType = 'array';

    /** @param array<string, string> $filters @return array<int, array<string, mixed>> */
    public function records(array $filters, DocumentDataScope $scope): array
    {
        $sectionIds = $scope->sectionIds();
        if ($sectionIds === []) {
            return [];
        }
        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.document_control_number, d.receiving_number, d.date_received, d.subject, d.sender_name, d.updated_at, ds.status_name, dt.type_name, s.section_name, u.first_name AS responsible_first_name, u.last_name AS responsible_last_name')
            ->select("COALESCE((SELECT TOP 1 COALESCE(ra.action_name, 'Routed') FROM dbo.routing_history rh LEFT JOIN dbo.routing_actions ra ON ra.action_id = rh.action_id WHERE rh.document_id = d.document_id ORDER BY rh.routed_at DESC), 'Received') AS latest_action", false)
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

        if ($filters['from'] !== '') {
            $builder->where('d.date_received >=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $nextDay = (new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
            $builder->where('d.date_received <', $nextDay . ' 00:00:00');
        }
        if ($filters['section'] !== '') {
            $builder->where('d.current_section_id', $filters['section']);
        }
        if ($filters['user'] !== '') {
            $builder->where('d.current_responsible_user_id', $filters['user']);
        }
        if ($filters['status'] !== '') {
            $builder->where('d.status_id', $filters['status']);
        }
        if ($filters['type'] !== '') {
            $builder->where('d.document_type_id', $filters['type']);
        }
        if ($filters['action'] === 'RECEIVED') {
            $builder->where('NOT EXISTS (SELECT 1 FROM dbo.routing_history rhf WHERE rhf.document_id = d.document_id)', null, false);
        } elseif ($filters['action'] === 'ROUTED') {
            $builder->where('EXISTS (SELECT 1 FROM dbo.routing_history rhx WHERE rhx.document_id = d.document_id)', null, false)
                ->where('(SELECT TOP 1 rhy.action_id FROM dbo.routing_history rhy WHERE rhy.document_id = d.document_id ORDER BY rhy.routed_at DESC) IS NULL', null, false);
        } elseif ($filters['action'] !== '') {
            $builder->where('(SELECT TOP 1 rhz.action_id FROM dbo.routing_history rhz WHERE rhz.document_id = d.document_id ORDER BY rhz.routed_at DESC) = ' . $this->db->escape($filters['action']), null, false);
        }

        return $builder->orderBy('d.date_received', 'DESC')->limit(5000)->get()->getResultArray();
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function references(DocumentDataScope $scope): array
    {
        $sectionIds = $scope->sectionIds();
        $sections = $this->db->table('sections s')->select('s.section_id, s.section_code, s.section_name')
            ->join('departments d', 'd.department_id = s.department_id')->where('s.active', 1);
        $users = $this->db->table('users u')->distinct()->select('u.user_id, u.employee_id, u.first_name, u.last_name')
            ->join('user_sections us', 'us.user_id = u.user_id')->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')->where('u.account_status', 'Active');
        if ($scope->officeId() !== null) {
            $sections->where('d.office_id', $scope->officeId());
            $users->where('d.office_id', $scope->officeId());
        }
        if ($sectionIds !== null && $sectionIds !== []) {
            $sections->whereIn('s.section_id', $sectionIds);
            $users->whereIn('s.section_id', $sectionIds);
        } elseif ($sectionIds === []) {
            $sections->where('1 = 0', null, false);
            $users->where('1 = 0', null, false);
        }
        return [
            'sections' => $sections->orderBy('s.section_name')->get()->getResultArray(),
            'users' => $users->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray(),
            'statuses' => $this->db->table('document_statuses')->select('status_id, status_name')->where('active', 1)->orderBy('status_name')->get()->getResultArray(),
            'types' => $this->db->table('document_types')->select('document_type_id, type_name')->where('active', 1)->orderBy('type_name')->get()->getResultArray(),
            'actions' => $this->db->table('routing_actions')->select('action_id, action_name')->where('active', 1)->orderBy('action_name')->get()->getResultArray(),
        ];
    }
}
