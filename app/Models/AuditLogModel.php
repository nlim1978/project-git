<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'audit_id';
    protected $returnType = 'array';

    /** @param array<string, string> $filters @return array{records:array<int,array<string,mixed>>,total:int} */
    public function page(array $filters, int $page, int $perPage, ?string $officeId = null): array
    {
        $total = $this->filteredBuilder($filters, $officeId)->countAllResults();
        $offset = ($page - 1) * $perPage;
        $records = $this->filteredBuilder($filters, $officeId)
            ->select('a.audit_id, a.user_id, a.document_id, a.module_name, a.action_name, a.description, a.old_value, a.new_value, a.ip_address, a.browser, a.occurred_at')
            ->select('u.employee_id, u.username, u.first_name, u.last_name')
            ->orderBy('a.occurred_at', 'DESC')
            ->orderBy('a.audit_id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return ['records' => $records, 'total' => $total];
    }

    /** @param array<string, string> $filters @return array<int,array<string,mixed>> */
    public function export(array $filters, ?string $officeId = null): array
    {
        return $this->filteredBuilder($filters, $officeId)
            ->select('a.audit_id, a.module_name, a.action_name, a.description, a.old_value, a.new_value, a.ip_address, a.browser, a.occurred_at')
            ->select('u.employee_id, u.username, u.first_name, u.last_name')
            ->orderBy('a.occurred_at', 'DESC')
            ->orderBy('a.audit_id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** @return array{users:array<int,array<string,mixed>>,modules:array<int,array<string,mixed>>,actions:array<int,array<string,mixed>>} */
    public function references(?string $officeId = null): array
    {
        $users = $this->db->table('audit_logs a')->distinct()
            ->select('a.user_id, u.employee_id, u.username, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = a.user_id', 'left')
            ->where('a.user_id IS NOT NULL', null, false);
        if ($officeId !== null) {
            $users->where('EXISTS (SELECT 1 FROM dbo.user_sections rus INNER JOIN dbo.sections rs ON rs.section_id = rus.section_id INNER JOIN dbo.departments rd ON rd.department_id = rs.department_id WHERE rus.user_id = a.user_id AND rd.office_id = ' . $this->db->escape($officeId) . ')', null, false);
        }
        return [
            'users' => $users->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray(),
            'modules' => $this->db->table('audit_logs')->select('module_name')->distinct()->orderBy('module_name')->get()->getResultArray(),
            'actions' => $this->db->table('audit_logs')->select('action_name')->distinct()->orderBy('action_name')->get()->getResultArray(),
        ];
    }

    /** @param array<string, string> $filters */
    private function filteredBuilder(array $filters, ?string $officeId = null): BaseBuilder
    {
        $builder = $this->db->table('audit_logs a')->join('users u', 'u.user_id = a.user_id', 'left');

        if ($officeId !== null) {
            $escapedOffice = $this->db->escape($officeId);
            $builder->where("((a.document_id IS NOT NULL AND EXISTS (SELECT 1 FROM dbo.documents ad INNER JOIN dbo.sections ads ON ads.section_id = ad.current_section_id INNER JOIN dbo.departments addp ON addp.department_id = ads.department_id WHERE ad.document_id = a.document_id AND addp.office_id = {$escapedOffice})) OR (a.document_id IS NULL AND a.user_id IS NOT NULL AND EXISTS (SELECT 1 FROM dbo.user_sections aus INNER JOIN dbo.sections ausec ON ausec.section_id = aus.section_id INNER JOIN dbo.departments aud ON aud.department_id = ausec.department_id WHERE aus.user_id = a.user_id AND aud.office_id = {$escapedOffice})))", null, false);
        }

        if ($filters['q'] !== '') {
            $builder->groupStart()
                ->like('a.description', $filters['q'])
                ->orLike('u.first_name', $filters['q'])
                ->orLike('u.last_name', $filters['q'])
                ->orLike('u.username', $filters['q'])
                ->orLike('a.ip_address', $filters['q'])
                ->orLike('a.browser', $filters['q'])
                ->groupEnd();
        }
        if ($filters['user'] === '__system__') {
            $builder->where('a.user_id IS NULL', null, false);
        } elseif ($filters['user'] !== '') {
            $builder->where('a.user_id', $filters['user']);
        }
        if ($filters['module'] !== '') {
            $builder->where('a.module_name', $filters['module']);
        }
        if ($filters['action'] !== '') {
            $builder->where('a.action_name', $filters['action']);
        }
        if ($filters['from'] !== '') {
            $builder->where('a.occurred_at >=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $nextDay = (new \DateTimeImmutable($filters['to']))->modify('+1 day')->format('Y-m-d');
            $builder->where('a.occurred_at <', $nextDay . ' 00:00:00');
        }

        return $builder;
    }
}
