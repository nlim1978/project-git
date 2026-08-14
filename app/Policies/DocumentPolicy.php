<?php

namespace App\Policies;

use App\Services\AuthorizationService;
use App\Services\BaseService;
use App\Services\OrganizationScopeService;
use CodeIgniter\Database\BaseConnection;

/**
 * Relationship-based policy for one document.
 *
 * The policy combines explicit permissions where a capability has one (for
 * example ROUTE) with the actor's live relationship to the document. Some
 * lightweight capabilities such as engagement confirmation are deliberately
 * relationship-driven because their routes are available from General Inbox.
 */
final class DocumentPolicy extends BaseService
{
    private OrganizationScopeService $scope;
    private AuthorizationService $authorization;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->scope = new OrganizationScopeService($this->db);
        $this->authorization = new AuthorizationService($this->db);
    }

    /** @param array<string, mixed> $document */
    public function canView(array $document, string $actorId, bool $canRecall = false): bool
    {
        $documentId = (string) $document['document_id'];
        if (! $this->scope->canAccessDocument($actorId, $documentId) && ! $canRecall) {
            return false;
        }

        if ($this->scope->isSuperAdmin($actorId) || $this->hasMonitoringAccess($actorId)) {
            return true;
        }

        $sectionId = (string) $document['current_section_id'];
        $receiver = hash_equals((string) ($document['receiving_personnel_id'] ?? ''), $actorId);
        $hasReceivingControl = $receiver && (
            ! $this->hasProcessingRoutingHistory($documentId)
            || $this->userBelongsToSection($actorId, $sectionId)
        );
        $responsible = hash_equals((string) ($document['current_responsible_user_id'] ?? ''), $actorId);
        $queueMember = $document['current_responsible_user_id'] === null
            && $this->userBelongsToSection($actorId, $sectionId);

        return $hasReceivingControl
            || $responsible
            || $this->isSectionHead($actorId, $sectionId)
            || $this->scope->isDepartmentHead($actorId)
            || $canRecall
            || $queueMember;
    }

    /** @param array<string, mixed> $document */
    public function canConfirm(array $document, string $actorId): bool
    {
        $responsible = trim((string) ($document['current_responsible_user_id'] ?? ''));
        if ($responsible !== '') {
            return hash_equals($responsible, $actorId);
        }

        return $this->isSectionHead($actorId, (string) $document['current_section_id']);
    }

    /** @param array<string, mixed> $document */
    public function canRoute(array $document, string $actorId): bool
    {
        if (! $this->authorization->hasPermission($actorId, 'Document Routing', 'Routing', 'ROUTE')) {
            return false;
        }

        $responsible = trim((string) ($document['current_responsible_user_id'] ?? ''));
        if ($responsible !== '') {
            return hash_equals($responsible, $actorId);
        }

        $sectionId = (string) $document['current_section_id'];
        return $this->isSectionHead($actorId, $sectionId)
            || $this->userBelongsToSection($actorId, $sectionId);
    }

    /** @param array<string, mixed> $document */
    public function canReassign(array $document, string $actorId): bool
    {
        if ($this->scope->isSuperAdmin($actorId)) {
            return true;
        }

        $sectionId = (string) $document['current_section_id'];
        if ($this->isSectionHead($actorId, $sectionId)) {
            return true;
        }

        if (! hash_equals((string) ($document['receiving_personnel_id'] ?? ''), $actorId)) {
            return false;
        }

        if (! $this->hasProcessingRoutingHistory((string) $document['document_id'])) {
            return true;
        }

        return $this->userBelongsToSection($actorId, $sectionId);
    }

    /** @param array<string, mixed> $document */
    public function canUseDocumentTools(array $document, string $actorId): bool
    {
        $documentId = (string) $document['document_id'];
        if (! $this->scope->canAccessDocument($actorId, $documentId)) {
            return false;
        }
        if ($this->scope->isSuperAdmin($actorId)) {
            return true;
        }

        $sectionId = (string) $document['current_section_id'];
        if ($this->scope->isDepartmentHead($actorId)) {
            return in_array($sectionId, $this->scope->departmentSectionIds($actorId), true);
        }
        if ($this->isSectionHead($actorId, $sectionId)) {
            return true;
        }

        $responsible = trim((string) ($document['current_responsible_user_id'] ?? ''));
        if ($responsible !== '') {
            return hash_equals($responsible, $actorId);
        }
        if ($this->userBelongsToSection($actorId, $sectionId)) {
            return true;
        }

        if (! hash_equals((string) ($document['receiving_personnel_id'] ?? ''), $actorId)) {
            return false;
        }
        if (! $this->hasProcessingRoutingHistory($documentId)) {
            return true;
        }

        return $this->userBelongsToSection($actorId, $sectionId);
    }

    /** @param array<string, mixed> $document @param array<string, mixed>|null $latest */
    public function canRecall(array $document, string $actorId, ?array $latest = null): bool
    {
        $documentId = (string) $document['document_id'];
        $latest ??= $this->latestRoutingEvent($documentId);
        if ($latest === null || (int) ($latest['is_reassigned'] ?? 0) === 1) {
            return false;
        }

        $fromSectionId = trim((string) ($latest['from_section_id'] ?? ''));
        $destinationSectionId = trim((string) ($latest['destination_section_id'] ?? ''));
        if ($fromSectionId === '' || $destinationSectionId === '' || hash_equals($fromSectionId, $destinationSectionId)) {
            return false;
        }
        if (! hash_equals($destinationSectionId, (string) ($document['current_section_id'] ?? ''))) {
            return false;
        }
        if ($this->hadConfirmationSince($documentId, (string) $latest['routed_at'])) {
            return false;
        }

        if ($this->scope->isSuperAdmin($actorId)) {
            return true;
        }
        if (! $this->scope->canAccessSection($actorId, $fromSectionId)) {
            return false;
        }
        if ($this->isSectionHead($actorId, $fromSectionId)) {
            return true;
        }

        return hash_equals((string) ($latest['routed_by'] ?? ''), $actorId)
            && $this->userBelongsToSection($actorId, $fromSectionId);
    }

    public function canBypassWorkLock(string $actorId): bool
    {
        return $this->scope->isSuperAdmin($actorId);
    }

    private function hasMonitoringAccess(string $actorId): bool
    {
        return $this->authorization->hasPermission($actorId, 'Monitoring', 'Monitoring', 'VIEW');
    }

    private function userBelongsToSection(string $userId, string $sectionId): bool
    {
        return $this->db->table('user_sections')
            ->where('user_id', $userId)
            ->where('section_id', $sectionId)
            ->countAllResults() > 0;
    }

    private function isSectionHead(string $userId, string $sectionId): bool
    {
        return in_array($sectionId, $this->scope->managedSectionIds($userId), true);
    }

    private function hasProcessingRoutingHistory(string $documentId): bool
    {
        return $this->db->table('routing_history')
            ->where('document_id', $documentId)
            ->where('is_reassigned', 0)
            ->countAllResults() > 0;
    }

    /** @return array<string, mixed>|null */
    private function latestRoutingEvent(string $documentId): ?array
    {
        $row = $this->db->table('routing_history')
            ->select('routing_id, document_id, from_section_id, from_user_id, destination_section_id, destination_user_id, action_id, resulting_status_id, remarks, routed_by, routed_at, is_reassigned')
            ->where('document_id', $documentId)
            ->orderBy('routed_at', 'DESC')
            ->limit(1)->get()->getRowArray();
        return $row === null ? null : $row;
    }

    private function hadConfirmationSince(string $documentId, string $since): bool
    {
        if (! $this->db->tableExists('document_engagements')) {
            return false;
        }

        return $this->db->table('document_engagements')
            ->where('document_id', $documentId)
            ->where('confirmed_at >', $since)
            ->countAllResults() > 0;
    }
}
