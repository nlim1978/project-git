<?php

namespace App\Services;

use App\Models\AttachmentModel;
use App\Models\DocumentEngagementModel;
use App\Models\DocumentModel;
use App\Models\RoutingModel;
use App\Policies\DocumentPolicy;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentRoutingService extends BaseService
{
    private RoutingModel $routing;
    private AttachmentModel $attachments;
    private DocumentModel $documents;
    private DocumentEngagementModel $engagements;
    private NotificationDeliveryService $notifications;
    private DocumentPolicy $policy;
    private DocumentEngagementService $engagementService;
    private DocumentRoutingCommandService $commands;
    private RoutingDestinationService $destinations;

    public function __construct()
    {
        parent::__construct();
        $this->routing = new RoutingModel();
        $this->attachments = new AttachmentModel();
        $this->documents = new DocumentModel();
        $this->engagements = new DocumentEngagementModel();
        $this->notifications = new NotificationDeliveryService();
        $this->policy = new DocumentPolicy($this->db);
        $this->engagementService = new DocumentEngagementService($this->db);
        $this->commands = new DocumentRoutingCommandService($this->db);
        $this->destinations = new RoutingDestinationService($this->db);
    }

    /** @return array<int, array<string, mixed>> */
    public function inbox(string $actorId): array
    {
        // The BRD defines General Inbox as the user's work queue, not a global
        // monitoring list. Even administrators see only their assigned sections here.
        $scope = new OrganizationScopeService($this->db);
        $documents = $this->routing->inboxDocuments($actorId, $scope->documentDataScope($actorId)->officeId());
        $states = $this->engagements->activeForDocuments(array_map(static fn (array $row): string => (string) $row['document_id'], $documents));
        foreach ($documents as &$document) {
            $document['engagement'] = $states[strtolower((string) $document['document_id'])] ?? null;
            $document['can_confirm_engagement'] = $this->policy->canConfirm($document, $actorId);
            $document['can_use_qr'] = $this->policy->canUseDocumentTools($document, $actorId)
                && (new AuthorizationService())->hasPermission($actorId, 'Document Details', 'Document Details', 'VIEW');
        }
        unset($document);
        return $documents;
    }

    /** @return array<int, array<string, mixed>> */
    public function inboxEvents(string $actorId, int $sinceEpoch): array
    {
        $scope = new OrganizationScopeService($this->db);
        $officeId = $scope->documentDataScope($actorId)->officeId();
        $rows = $this->routing->inboxEvents($actorId, $sinceEpoch, $officeId);

        return array_map(static function (array $row): array {
            $routingId = trim((string) ($row['routing_id'] ?? ''));
            $updatedAt = (string) ($row['updated_at'] ?? '');
            $routedBy = trim((string) (($row['routed_by_first_name'] ?? '') . ' ' . ($row['routed_by_last_name'] ?? '')));
            return [
                'event_key' => strtolower((string) $row['document_id']) . ':' . ($routingId !== '' ? strtolower($routingId) : $updatedAt),
                'document_id' => (string) $row['document_id'],
                'control_number' => short_control_number((string) $row['document_control_number']),
                'subject' => (string) $row['subject'],
                'document_type' => (string) $row['type_name'],
                'status' => (string) $row['status_name'],
                'section' => (string) $row['section_name'],
                'message' => $routingId !== ''
                    ? 'New transaction received' . ($routedBy !== '' ? ' from ' . $routedBy : '')
                    : 'New document received in your General Inbox',
                'remarks' => trim((string) ($row['routing_remarks'] ?? '')),
                'occurred_at' => (string) (($row['routed_at'] ?? '') ?: $updatedAt),
            ];
        }, $rows);
    }

    /** @return array<string, mixed>|null */
    public function document(string $documentId, string $actorId): ?array
    {
        $document = $this->routing->documentForRouting($documentId);
        if ($document === null) {
            return null;
        }

        $scope = new OrganizationScopeService($this->db);
        $canRecall = (int) $document['is_terminal'] === 0 && $this->policy->canRecall($document, $actorId);
        if (! $this->policy->canView($document, $actorId, $canRecall)) {
            return null;
        }
        $administrator = $scope->isSuperAdmin($actorId);
        $document['is_administrator'] = $administrator;

        $engagement = $this->engagements->activeForDocument($documentId);
        $workLockedByAnother = $engagement !== null
            && ! hash_equals((string) $engagement['confirmed_by'], $actorId)
            && ! $administrator;
        $document['can_route_from_assignment'] = ! $workLockedByAnother && $this->policy->canRoute($document, $actorId);
        $document['can_reassign_assignment'] = (int) $document['is_terminal'] === 0
            && ! $workLockedByAnother
            && $this->policy->canReassign($document, $actorId);
        $document['can_recall_routing'] = $canRecall;
        $document['can_download_attachments'] = $this->policy->canUseDocumentTools($document, $actorId);
        $document['can_print_qr'] = $document['can_download_attachments'];
        $document['engagement'] = $engagement;
        $document['can_confirm_engagement'] = (int) $document['is_terminal'] === 0 && $this->policy->canConfirm($document, $actorId);
        $document['actions'] = $this->routing->allowedActions($actorId);
        $destinations = $this->destinations->options($actorId);
        $document['sections'] = $destinations['sections'];
        $document['section_users'] = $destinations['section_users'];
        $document['timeline'] = $this->routing->timeline($documentId);
        $document['attachments'] = $this->attachments->forDocument($documentId);
        $document['latest_sender_remark'] = null;
        for ($i = count($document['timeline']) - 1; $i >= 0; $i--) {
            $event = $document['timeline'][$i];
            if (trim((string) ($event['remarks'] ?? '')) !== '' && (int) ($event['is_recall'] ?? 0) !== 1) {
                $document['latest_sender_remark'] = $event;
                break;
            }
        }
        // Delivery diagnostics are operational/admin data, not part of the
        // normal document-details experience.
        $document['notifications'] = $administrator
            ? $this->notifications->logsForDocument($documentId)
            : [];
        return $document;
    }

    /** @return array<string, mixed>|null */
    public function engagementState(string $documentId, string $actorId): ?array
    {
        return $this->engagementService->state($documentId, $actorId);
    }

    /** @return array<string, mixed> */
    public function confirmEngagement(string $documentId, string $actorId, ?string $ipAddress = null, ?string $browser = null): array
    {
        return $this->engagementService->confirm($documentId, $actorId, $ipAddress, $browser);
    }

    /** @return array<string, mixed> */
    public function heartbeatEngagement(string $documentId, string $actorId): array
    {
        return $this->engagementService->heartbeat($documentId, $actorId);
    }

    /** @return array<string, mixed>|null */
    public function documentByQrToken(string $token, string $actorId): ?array
    {
        $matched = $this->documents->byQrToken($token);
        if ($matched === null) {
            return null;
        }

        return $this->document((string) $matched['document_id'], $actorId);
    }

    /** @return array<string, mixed>|null */
    public function reassignmentContext(string $documentId, string $actorId): ?array
    {
        $document = $this->routing->documentForRouting($documentId);
        if ($document === null) {
            return null;
        }

        $scope = new OrganizationScopeService($this->db);
        if (! $this->policy->canView($document, $actorId)) {
            return null;
        }

        $destinations = $this->destinations->options($actorId);
        return [
            'can_reassign' => (int) $document['is_terminal'] === 0 && $this->policy->canReassign($document, $actorId),
            'current_section_id' => (string) $document['current_section_id'],
            'current_section_name' => (string) $document['section_name'],
            'current_responsible_user_id' => $document['current_responsible_user_id'],
            'current_responsible_name' => $document['current_responsible_user_id']
                ? trim((string) $document['responsible_first_name'] . ' ' . (string) $document['responsible_last_name'])
                : null,
            'sections' => $destinations['sections'],
            'section_users' => $destinations['section_users'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function attachment(string $documentId, string $attachmentId): ?array
    {
        return $this->attachments->belongingToDocument($attachmentId, $documentId);
    }

    public function canDownloadAttachment(string $documentId, string $actorId): bool
    {
        $document = $this->routing->documentForRouting($documentId);
        if ($document === null) {
            return false;
        }

        return $this->policy->canUseDocumentTools($document, $actorId);
    }

    public function canOperateOnDocument(string $documentId, string $actorId): bool
    {
        $document = $this->routing->documentForRouting($documentId);
        if ($document === null) {
            return false;
        }

        return $this->policy->canUseDocumentTools($document, $actorId);
    }

    /** @param array<string, mixed> $input */
    public function route(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null, ?UploadedFile $evidence = null): void
    {
        $this->commands->route($documentId, $input, $actorId, $ipAddress, $browser, $evidence);
    }

    /** @param array<string, mixed> $input */
    public function reassign(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $this->commands->reassign($documentId, $input, $actorId, $ipAddress, $browser);
    }

    public function recall(string $documentId, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $this->commands->recall($documentId, $actorId, $ipAddress, $browser);
    }
}
