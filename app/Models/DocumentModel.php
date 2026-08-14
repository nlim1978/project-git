<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    protected $table = 'documents';
    protected $primaryKey = 'document_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'receiving_number', 'document_control_number', 'qr_token', 'client_tracking_token', 'client_tracking_reference', 'document_type_id',
        'subject', 'description', 'sender_name', 'sender_organization', 'sender_email',
        'sender_contact_number', 'date_received', 'receiving_personnel_id', 'initial_section_id',
        'initial_responsible_user_id', 'current_section_id', 'current_responsible_user_id',
        'status_id', 'remarks', 'created_by', 'updated_at',
    ];

    /**
     * Insert through Query Builder so SQL Server's NEWID() default supplies document_id.
     * CI4 Model::insert() requires an explicit key when useAutoIncrement is false.
     *
     * @param array<string, mixed> $data
     */
    public function insertRecord(array $data): bool
    {
        return $this->db->table($this->table)->insert($data);
    }

    /** @param array<string, mixed> $data */
    public function updateRecord(string $documentId, array $data): bool
    {
        return $this->db->table($this->table)
            ->where('document_id', $documentId)
            ->update($data);
    }

    /** @return array<int, array<string, mixed>> */
    public function receivingList(int $limit = 100, ?string $officeId = null, ?array $sectionIds = null): array
    {
        if ($sectionIds === []) {
            return [];
        }
        $builder = $this->db->table('documents d')
            ->select('d.document_id, d.receiving_number, d.document_control_number, d.subject, d.sender_name, d.date_received, dt.type_name, ds.status_name, s.section_name')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id');
        if ($officeId !== null) {
            $builder->where('dep.office_id', $officeId);
        }
        if ($sectionIds !== null) {
            $builder->whereIn('d.current_section_id', $sectionIds);
        }
        return $builder->orderBy('d.date_received', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function receivingDetail(string $documentId, ?string $officeId = null, ?array $sectionIds = null): ?array
    {
        if ($sectionIds === []) {
            return null;
        }
        $builder = $this->db->table('documents d')
            ->select('d.*, dt.type_code, dt.type_name, dt.prefix, ds.status_code, ds.status_name, s.section_code, s.section_name, u.first_name AS responsible_first_name, u.last_name AS responsible_last_name, rp.first_name AS receiver_first_name, rp.last_name AS receiver_last_name')
            ->join('document_types dt', 'dt.document_type_id = d.document_type_id')
            ->join('document_statuses ds', 'ds.status_id = d.status_id')
            ->join('sections s', 's.section_id = d.current_section_id')
            ->join('departments dep', 'dep.department_id = s.department_id')
            ->join('users u', 'u.user_id = d.current_responsible_user_id', 'left')
            ->join('users rp', 'rp.user_id = d.receiving_personnel_id')
            ->where('d.document_id', $documentId);
        if ($officeId !== null) {
            $builder->where('dep.office_id', $officeId);
        }
        if ($sectionIds !== null) {
            $builder->whereIn('d.current_section_id', $sectionIds);
        }
        return $builder->get()->getRowArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function activeTypes(): array
    {
        return $this->db->table('document_types')->where('active', 1)->orderBy('type_name')->get()->getResultArray();
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

    /** @return array<string, mixed>|null */
    public function activeType(string $id): ?array
    {
        return $this->db->table('document_types')->where('document_type_id', $id)->where('active', 1)->get()->getRowArray();
    }

    public function activeSectionExists(string $id): bool
    {
        return $this->db->table('sections')->where('section_id', $id)->where('active', 1)->countAllResults() === 1;
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

    public function receivedStatusId(): ?string
    {
        $row = $this->db->table('document_statuses')->select('status_id')->where('status_code', 'RECEIVED')->where('active', 1)->get()->getRowArray();
        return $row === null ? null : (string) $row['status_id'];
    }

    /** @return array<string, mixed>|null */
    public function byQrToken(string $qrToken): ?array
    {
        return $this->db->table('documents')->where('qr_token', $qrToken)->get()->getRowArray();
    }

    /** @return array<string, mixed>|null */
    public function byClientTrackingToken(string $token): ?array
    {
        $row = $this->db->table('documents')
            ->where('client_tracking_token', strtolower($token))
            ->get()->getRowArray();
        return $row === null ? null : $row;
    }
}
