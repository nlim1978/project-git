<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentEngagementModel extends Model
{
    protected $table = 'document_engagements';
    protected $primaryKey = 'engagement_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'engagement_id', 'document_id', 'section_id', 'responsible_user_id', 'confirmed_by',
        'confirmed_at', 'last_seen_at', 'lock_expires_at', 'ended_at', 'ended_reason',
    ];

    public function isAvailable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /** @return array<string, mixed>|null */
    public function activeForDocument(string $documentId): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $row = $this->db->table('document_engagements de')
            ->select('de.engagement_id, de.document_id, de.section_id, de.responsible_user_id, de.confirmed_by, de.confirmed_at, de.last_seen_at, de.lock_expires_at, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = de.confirmed_by')
            ->where('de.document_id', $documentId)
            ->where('de.ended_at', null)
            ->where('de.lock_expires_at >', gmdate('Y-m-d H:i:s'))
            ->orderBy('de.confirmed_at', 'DESC')->limit(1)->get()->getRowArray();

        return $row === null ? null : $row;
    }

    /** @param array<int, string> $documentIds @return array<string, array<string, mixed>> */
    public function activeForDocuments(array $documentIds): array
    {
        if ($documentIds === [] || ! $this->isAvailable()) {
            return [];
        }
        $rows = $this->db->table('document_engagements de')
            ->select('de.engagement_id, de.document_id, de.section_id, de.responsible_user_id, de.confirmed_by, de.confirmed_at, de.last_seen_at, de.lock_expires_at, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = de.confirmed_by')
            ->whereIn('de.document_id', $documentIds)
            ->where('de.ended_at', null)
            ->where('de.lock_expires_at >', gmdate('Y-m-d H:i:s'))
            ->orderBy('de.confirmed_at', 'DESC')->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['document_id']);
            $result[$key] ??= $row;
        }
        return $result;
    }

    public function expireStale(string $documentId): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('document_engagements')
            ->where('document_id', $documentId)->where('ended_at', null)->where('lock_expires_at <=', $now)
            ->update(['ended_at' => $now, 'ended_reason' => 'LOCK_EXPIRED']);
    }

    public function endActive(string $documentId, string $reason): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->db->table('document_engagements')->where('document_id', $documentId)->where('ended_at', null)
            ->update(['ended_at' => gmdate('Y-m-d H:i:s'), 'ended_reason' => mb_substr($reason, 0, 80)]);
    }

    public function renewOwnedActive(string $documentId, string $actorId, string $now, string $expires): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $this->db->table('document_engagements')
            ->where('document_id', $documentId)
            ->where('confirmed_by', $actorId)
            ->where('ended_at', null)
            ->where('lock_expires_at >', $now)
            ->update(['last_seen_at' => $now, 'lock_expires_at' => $expires]);

        return $this->db->affectedRows() === 1;
    }

}
