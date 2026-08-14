<?php

namespace App\Models;

use App\Policies\SystemRole;
use CodeIgniter\Model;

class RoutingModel extends Model
{
    protected $table = 'routing_history';
    protected $primaryKey = 'routing_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'routing_id', 'document_id', 'from_section_id', 'from_user_id', 'destination_section_id',
        'destination_user_id', 'action_id', 'resulting_status_id', 'remarks',
        'routed_by', 'is_reassigned',
    ];

    /** @param array<string, mixed> $data */
    public function insertRecord(array $data): bool
    {
        return $this->db->table($this->table)->insert($data);
    }

    public function isAdministrator(string $userId): bool
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.role_id = ur.role_id')
            ->where('ur.user_id', $userId)->whereIn('r.role_name', SystemRole::GLOBAL_ADMINISTRATORS)->where('r.active', 1)
            ->countAllResults() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function inboxDocuments(string $userId, ?string $officeId = null): array
    {
        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.document_control_number, d.receiving_number, d.subject, d.sender_name, d.date_received, d.receiving_personnel_id, d.current_section_id, d.current_responsible_user_id, d.updated_at, ds.status_name, s.section_name, dt.type_name, u.first_name AS responsible_first_name, u.last_name AS responsible_last_name')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('users u', 'u.user_id = d.current_responsible_user_id', 'left')
            ->where('ds.is_terminal', 0);

        // A work item belongs in the user's inbox when the user is explicitly
        // responsible, belongs to its current section, or is configured as that
        // section's head. Do not require all three to agree; legacy/user setup can
        // legitimately keep a Department Head's primary membership elsewhere.
        $escapedUser = $this->db->escape($userId);
        $builder->groupStart()
            ->where('d.current_responsible_user_id', $userId)
            ->orWhere('s.head_user_id', $userId)
            ->orWhere("EXISTS (SELECT 1 FROM user_sections us WHERE us.section_id = d.current_section_id AND us.user_id = {$escapedUser})", null, false)
            ->groupEnd();
        if ($officeId !== null) {
            $builder->where('dep.office_id', $officeId);
        }

        return $builder->orderBy('d.updated_at', 'DESC')->limit(100)->get()->getResultArray();
    }

    /**
     * Return current Inbox items changed since a recent client cursor. The
     * one-second overlap is deliberate; the browser de-duplicates event keys
     * and SQL Server timestamps may only be written to whole-second precision.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inboxEvents(string $userId, int $sinceEpoch, ?string $officeId = null): array
    {
        $since = gmdate('Y-m-d H:i:s', max(0, $sinceEpoch - 1));
        $builder = $this->db->table('documents d')
            ->select("d.document_id, d.document_control_number, d.subject, d.updated_at, d.current_section_id, d.current_responsible_user_id, s.section_name, dt.type_name, ds.status_name, rh.routing_id, rh.remarks AS routing_remarks, rh.routed_at, ru.first_name AS routed_by_first_name, ru.last_name AS routed_by_last_name", false)
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('routing_history rh', 'rh.routing_id = (SELECT TOP 1 rh2.routing_id FROM dbo.routing_history rh2 WHERE rh2.document_id = d.document_id ORDER BY rh2.routed_at DESC, rh2.routing_id DESC)', 'left', false)
            ->join('users ru', 'ru.user_id = rh.routed_by', 'left')
            ->where('ds.is_terminal', 0)
            ->where('d.updated_at >=', $since);

        $escapedUser = $this->db->escape($userId);
        $builder->groupStart()
            ->where('d.current_responsible_user_id', $userId)
            ->orWhere('s.head_user_id', $userId)
            ->orWhere("EXISTS (SELECT 1 FROM user_sections us WHERE us.section_id = d.current_section_id AND us.user_id = {$escapedUser})", null, false)
            ->groupEnd();
        if ($officeId !== null) {
            $builder->where('dep.office_id', $officeId);
        }

        return $builder->orderBy('d.updated_at', 'ASC')->limit(50)->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function documentForRouting(string $documentId): ?array
    {
        return $this->db->table('documents d')
            ->select('d.document_id, d.document_control_number, d.receiving_number, d.qr_token, d.subject, d.description, d.sender_name, d.sender_organization, d.sender_email, d.sender_contact_number, d.receiving_personnel_id, d.initial_section_id, d.initial_responsible_user_id, d.current_section_id, d.current_responsible_user_id, d.date_received, d.remarks, d.created_at, d.updated_at, ds.status_id, ds.status_code, ds.status_name, ds.is_terminal, dt.type_name, s.section_code, s.section_name, ins.section_code AS initial_section_code, ins.section_name AS initial_section_name, u.first_name AS responsible_first_name, u.last_name AS responsible_last_name, iu.first_name AS initial_responsible_first_name, iu.last_name AS initial_responsible_last_name, rp.first_name AS receiver_first_name, rp.last_name AS receiver_last_name')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('sections ins', 'ins.section_id = d.initial_section_id')
            ->join('users u', 'u.user_id = d.current_responsible_user_id', 'left')
            ->join('users iu', 'iu.user_id = d.initial_responsible_user_id', 'left')
            ->join('users rp', 'rp.user_id = d.receiving_personnel_id')
            ->where('d.document_id', $documentId)->get()->getRowArray();
    }

    /** @return array<string, mixed>|null */
    public function latestRoutingEvent(string $documentId): ?array
    {
        $row = $this->db->table('routing_history')
            ->select('routing_id, document_id, from_section_id, from_user_id, destination_section_id, destination_user_id, action_id, resulting_status_id, remarks, routed_by, routed_at, is_reassigned')
            ->where('document_id', $documentId)
            ->orderBy('routed_at', 'DESC')
            ->limit(1)
            ->get()->getRowArray();

        return $row === null ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function allowedActions(string $userId): array
    {
        $builder = $this->db->table('routing_actions a')
            ->distinct()
            ->select("a.action_id, a.action_name, a.requires_remarks, a.resulting_status_id, ds.status_name AS resulting_status_name, ds.is_terminal, CASE WHEN LOWER(a.action_name) IN ('filed', 'released') THEN 1 ELSE 0 END AS requires_evidence", false)
            ->join('document_statuses ds', 'ds.status_id = a.resulting_status_id')
            ->where('a.active', 1);

        if (! $this->isAdministrator($userId)) {
            $builder->join('routing_action_roles ar', 'ar.action_id = a.action_id')
                ->join('user_roles ur', 'ur.role_id = ar.role_id')
                ->join('roles r', 'r.role_id = ur.role_id')
                ->where('ur.user_id', $userId)->where('r.active', 1);
        }

        return $builder->orderBy('a.action_name')->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function allowedAction(string $actionId, string $userId): ?array
    {
        $builder = $this->db->table('routing_actions a')
            ->distinct()
            ->select('a.action_id, a.action_name, a.requires_remarks, a.resulting_status_id, ds.status_name AS resulting_status_name, ds.is_terminal')
            ->join('document_statuses ds', 'ds.status_id = a.resulting_status_id')
            ->where('a.action_id', $actionId)->where('a.active', 1);

        if (! $this->isAdministrator($userId)) {
            $builder->join('routing_action_roles ar', 'ar.action_id = a.action_id')
                ->join('user_roles ur', 'ur.role_id = ar.role_id')
                ->join('roles r', 'r.role_id = ur.role_id')
                ->where('ur.user_id', $userId)->where('r.active', 1);
        }

        return $builder->get()->getRowArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSections(?string $officeId = null): array
    {
        $builder = $this->db->table('sections s')
            ->select('s.section_id, s.section_code, s.section_name, d.department_name, o.office_name')
            ->join('departments d', 'd.department_id = s.department_id')
            ->join('offices o', 'o.office_id = d.office_id')
            ->where('s.active', 1)->where('d.active', 1)->where('o.active', 1);
        if ($officeId !== null) {
            $builder->where('o.office_id', $officeId);
        }
        return $builder->orderBy('o.office_name')->orderBy('d.department_name')->orderBy('s.section_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSectionUsers(?string $officeId = null): array
    {
        $builder = $this->db->table('user_sections us')
            ->select('us.section_id, u.user_id, u.employee_id, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = us.user_id')
            ->join('sections s', 's.section_id = us.section_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('u.account_status', 'Active');
        if ($officeId !== null) {
            $builder->where('d.office_id', $officeId);
        }
        $rows = $builder->orderBy('u.last_name')->orderBy('u.first_name')->get()->getResultArray();

        $heads = $this->db->table('sections s')
            ->select('s.section_id, u.user_id, u.employee_id, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = s.head_user_id')
            ->join('departments d', 'd.department_id = s.department_id')
            ->where('s.active', 1)->where('u.account_status', 'Active');
        if ($officeId !== null) {
            $heads->where('d.office_id', $officeId);
        }
        foreach ($heads->get()->getResultArray() as $head) {
            $key = strtolower((string) $head['section_id'] . ':' . (string) $head['user_id']);
            $exists = false;
            foreach ($rows as $row) {
                if (strtolower((string) $row['section_id'] . ':' . (string) $row['user_id']) === $key) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $rows[] = $head;
            }
        }
        usort($rows, static fn (array $a, array $b): int => strcasecmp(
            (string) $a['last_name'] . ' ' . (string) $a['first_name'],
            (string) $b['last_name'] . ' ' . (string) $b['first_name']
        ));
        return $rows;
    }

    public function activeUserBelongsToSection(string $userId, string $sectionId): bool
    {
        $member = $this->db->table('user_sections us')
            ->join('users u', 'u.user_id = us.user_id')
            ->where('us.user_id', $userId)->where('us.section_id', $sectionId)
            ->where('u.account_status', 'Active')->countAllResults() === 1;
        if ($member) {
            return true;
        }
        return $this->db->table('sections s')
            ->join('users u', 'u.user_id = s.head_user_id')
            ->where('s.section_id', $sectionId)->where('s.head_user_id', $userId)
            ->where('s.active', 1)->where('u.account_status', 'Active')
            ->countAllResults() === 1;
    }

    /** @return array<int, array<string, mixed>> */
    public function timeline(string $documentId): array
    {
        return $this->db->table('routing_history rh')
            ->select("rh.routing_id, rh.from_section_id, rh.from_user_id, rh.destination_section_id, rh.destination_user_id, rh.remarks, rh.routed_at, rh.is_reassigned, CASE WHEN rh.is_reassigned = 1 AND rh.remarks = 'Routing recalled before destination action.' THEN 1 ELSE 0 END AS is_recall, a.action_name, ds.status_name, fs.section_name AS from_section_name, ts.section_name AS destination_section_name, fu.first_name AS from_first_name, fu.last_name AS from_last_name, tu.first_name AS destination_first_name, tu.last_name AS destination_last_name, ru.first_name AS routed_by_first_name, ru.last_name AS routed_by_last_name", false)
            ->join('routing_actions a', 'a.action_id = rh.action_id', 'left')
            ->join('document_statuses ds', 'ds.status_id = rh.resulting_status_id')
            ->join('sections fs', 'fs.section_id = rh.from_section_id', 'left')
            ->join('sections ts', 'ts.section_id = rh.destination_section_id')
            ->join('users fu', 'fu.user_id = rh.from_user_id', 'left')
            ->join('users tu', 'tu.user_id = rh.destination_user_id', 'left')
            ->join('users ru', 'ru.user_id = rh.routed_by')
            ->where('rh.document_id', $documentId)->orderBy('rh.routed_at', 'ASC')->get()->getResultArray();
    }
}
