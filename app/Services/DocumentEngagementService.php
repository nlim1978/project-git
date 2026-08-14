<?php

namespace App\Services;

use App\Models\DocumentEngagementModel;
use App\Models\RoutingModel;
use App\Policies\DocumentPolicy;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class DocumentEngagementService extends BaseService
{
    private const LOCK_MINUTES = 15;

    private RoutingModel $routing;
    private DocumentEngagementModel $engagements;
    private DocumentPolicy $policy;
    private DocumentConcurrencyService $concurrency;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->routing = new RoutingModel();
        $this->engagements = new DocumentEngagementModel();
        $this->policy = new DocumentPolicy($this->db);
        $this->concurrency = new DocumentConcurrencyService($this->db);
    }

    /** @return array<string, mixed>|null */
    public function state(string $documentId, string $actorId): ?array
    {
        $document = $this->routing->documentForRouting($documentId);
        if ($document === null) {
            return null;
        }
        $canRecall = (int) $document['is_terminal'] === 0 && $this->policy->canRecall($document, $actorId);
        if (! $this->policy->canView($document, $actorId, $canRecall)) {
            return null;
        }
        return $this->formatState($document, $actorId, $this->engagements->activeForDocument($documentId));
    }

    /** @return array<string, mixed> */
    public function confirm(string $documentId, string $actorId, ?string $ipAddress = null, ?string $browser = null): array
    {
        if (! $this->engagements->isAvailable()) {
            throw new RuntimeException('Verify / Confirm is not available until the latest database migration is applied.');
        }

        $this->db->transBegin();
        try {
            $document = $this->concurrency->lockState($documentId);
            if ($document === null) {
                throw new RuntimeException('Document not found.');
            }
            if ((int) $document['is_terminal'] === 1 || ! $this->policy->canConfirm($document, $actorId)) {
                throw new RuntimeException('Only the currently assigned person may confirm this document. For an unassigned section queue, the current Section Head may confirm it.');
            }
            $this->engagements->expireStale($documentId);
            $active = $this->engagements->activeForDocument($documentId);
            $now = gmdate('Y-m-d H:i:s');
            $expires = gmdate('Y-m-d H:i:s', time() + (self::LOCK_MINUTES * 60));
            if ($active !== null && ! hash_equals((string) $active['confirmed_by'], $actorId)) {
                throw new RuntimeException('This document is already being handled by ' . trim((string) $active['first_name'] . ' ' . (string) $active['last_name']) . '.');
            }

            if ($active !== null) {
                $this->db->table('document_engagements')->where('engagement_id', $active['engagement_id'])
                    ->update(['last_seen_at' => $now, 'lock_expires_at' => $expires]);
            } else {
                $engagementId = $this->uuidV4();
                if (! $this->db->table('document_engagements')->insert([
                    'engagement_id' => $engagementId,
                    'document_id' => $documentId,
                    'section_id' => $document['current_section_id'],
                    'responsible_user_id' => $document['current_responsible_user_id'],
                    'confirmed_by' => $actorId,
                    'confirmed_at' => $now,
                    'last_seen_at' => $now,
                    'lock_expires_at' => $expires,
                ])) {
                    throw new RuntimeException('Document confirmation could not be saved.');
                }
                if (! $this->db->table('audit_logs')->insert([
                    'user_id' => $actorId, 'document_id' => $documentId, 'module_name' => 'General Inbox',
                    'action_name' => 'CONFIRM', 'description' => 'Confirmed active handling of ' . $document['document_control_number'],
                    'old_value' => null, 'new_value' => json_encode(['engagement_id' => $engagementId, 'section_id' => $document['current_section_id']], JSON_UNESCAPED_SLASHES),
                    'ip_address' => $this->nullable($ipAddress), 'browser' => $this->nullable($browser),
                ])) {
                    throw new RuntimeException('Document confirmation audit record could not be saved.');
                }
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $this->state($documentId, $actorId) ?? [];
    }

    /** @return array<string, mixed> */
    public function heartbeat(string $documentId, string $actorId): array
    {
        $state = $this->state($documentId, $actorId);
        if ($state === null) {
            return [];
        }
        if (! $this->engagements->isAvailable()) {
            return $state;
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->engagements->renewOwnedActive(
            $documentId,
            $actorId,
            $now,
            gmdate('Y-m-d H:i:s', time() + (self::LOCK_MINUTES * 60))
        );
        return $this->state($documentId, $actorId) ?? [];
    }

    public function assertMutationAllowed(string $documentId, string $actorId): void
    {
        $active = $this->engagements->activeForDocument($documentId);
        if ($active === null || hash_equals((string) $active['confirmed_by'], $actorId) || $this->policy->canBypassWorkLock($actorId)) {
            return;
        }
        throw new RuntimeException('This document is currently being handled by ' . trim((string) $active['first_name'] . ' ' . (string) $active['last_name']) . '. Wait for the work lock to expire before changing it.');
    }

    /** @param array<string, mixed> $document @param array<string, mixed>|null $engagement @return array<string, mixed> */
    private function formatState(array $document, string $actorId, ?array $engagement): array
    {
        return [
            'active' => $engagement !== null,
            'confirmed_by' => $engagement['confirmed_by'] ?? null,
            'confirmed_by_name' => $engagement === null ? null : trim((string) $engagement['first_name'] . ' ' . (string) $engagement['last_name']),
            'confirmed_at' => $engagement['confirmed_at'] ?? null,
            'lock_expires_at' => $engagement['lock_expires_at'] ?? null,
            'owned_by_actor' => $engagement !== null && hash_equals((string) $engagement['confirmed_by'], $actorId),
            'can_confirm' => (int) ($document['is_terminal'] ?? 0) === 0 && $this->policy->canConfirm($document, $actorId),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
