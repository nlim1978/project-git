<?php

namespace App\Services;

use App\Models\DocumentEngagementModel;
use App\Models\RoutingModel;
use App\Policies\DocumentPolicy;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class DocumentRoutingCommandService extends BaseService
{
    private RoutingModel $routing;
    private DocumentEngagementModel $engagements;
    private NotificationDeliveryService $notifications;
    private DocumentPolicy $policy;
    private DocumentEngagementService $engagement;
    private DocumentAttachmentStorageService $storage;
    private RoutingDestinationService $destinations;
    private DocumentConcurrencyService $concurrency;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->routing = new RoutingModel();
        $this->engagements = new DocumentEngagementModel();
        $this->notifications = new NotificationDeliveryService();
        $this->policy = new DocumentPolicy($this->db);
        $this->engagement = new DocumentEngagementService($this->db);
        $this->storage = new DocumentAttachmentStorageService($this->db);
        $this->destinations = new RoutingDestinationService($this->db);
        $this->concurrency = new DocumentConcurrencyService($this->db);
    }

    /** @param array<string, mixed> $input */
    public function route(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null, ?UploadedFile $evidence = null): void
    {
        $routingId = '';
        $notificationUser = null;
        $notificationReassigned = false;
        $evidencePath = null;
        $evidenceName = null;
        $this->db->transBegin();
        try {
            $document = $this->concurrency->lockState($documentId);
            if ($document === null) {
                throw new RuntimeException('Document not found.');
            }
            $scope = new OrganizationScopeService($this->db);
            if (! $scope->canAccessDocument($actorId, $documentId)) {
                throw new RuntimeException('This document is outside your office scope.');
            }
            if ((int) $document['is_terminal'] === 1) {
                throw new RuntimeException('Completed documents cannot be routed.');
            }
            if (! $this->policy->canRoute($document, $actorId)) {
                throw new RuntimeException('You are not authorized to route this document from its current assignment.');
            }
            $this->engagement->assertMutationAllowed($documentId, $actorId);
            $this->concurrency->assertExpectedVersion($document, $input['document_version'] ?? null);

            $actionChoice = trim((string) ($input['action_id'] ?? ''));
            if ($actionChoice === '') {
                throw new RuntimeException('Select the action taken before choosing the next destination.');
            }
            $actionId = $actionChoice === 'route_only' ? '' : $actionChoice;
            $action = $actionId !== '' ? $this->routing->allowedAction($actionId, $actorId) : null;
            if ($actionId !== '' && $action === null) {
                throw new RuntimeException('The selected routing action is not permitted for your role.');
            }

            $terminalAction = $action !== null && (int) ($action['is_terminal'] ?? 0) === 1;
            if ($terminalAction) {
                // Terminal actions complete the record in its present custody.
                // They are not a forwarding movement and therefore do not accept
                // a client-supplied destination.
                $destinationSectionId = (string) $document['current_section_id'];
                $destinationUserId = trim((string) ($document['current_responsible_user_id'] ?? ''));
            } else {
                $destinationSectionId = trim((string) ($input['destination_section_id'] ?? ''));
                $destinationUserId = trim((string) ($input['destination_user_id'] ?? ''));
                if ($destinationSectionId === '') {
                    throw new RuntimeException('Choose the next destination section.');
                }
                $this->destinations->assertAllowed($actorId, $destinationSectionId, $destinationUserId);
            }

            $remarks = trim((string) ($input['remarks'] ?? ''));
            if ($action !== null && (int) $action['requires_remarks'] === 1 && $remarks === '') {
                throw new RuntimeException('Remarks are required for the selected routing action.');
            }

            $requiresEvidence = $action !== null && in_array(strtolower(trim((string) $action['action_name'])), ['filed', 'released'], true);
            if ($requiresEvidence) {
                if (! $evidence instanceof UploadedFile || $evidence->getError() === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Evidence is required when a document is Filed or Released.');
                }
                [$evidencePath, $evidenceName] = $this->storage->storeRoutingEvidence($evidence, $documentId, $actorId);
            }

            $fromUserId = $document['current_responsible_user_id'] !== null ? (string) $document['current_responsible_user_id'] : null;
            $destinationUser = $destinationUserId !== '' ? $destinationUserId : null;
            $isReassigned = $destinationSectionId === (string) $document['current_section_id'] && $destinationUser !== $fromUserId;
            $routingId = $this->uuidV4();

            if (! $this->routing->insertRecord([
                'routing_id' => $routingId,
                'document_id' => $documentId,
                'from_section_id' => $document['current_section_id'],
                'from_user_id' => $fromUserId,
                'destination_section_id' => $destinationSectionId,
                'destination_user_id' => $destinationUser,
                'action_id' => $action['action_id'] ?? null,
                'resulting_status_id' => $action['resulting_status_id'] ?? $document['status_id'],
                'remarks' => $remarks !== '' ? $remarks : null,
                'routed_by' => $actorId,
                'is_reassigned' => $isReassigned ? 1 : 0,
            ])) {
                throw new RuntimeException('The routing event could not be saved.');
            }

            if (! $this->db->table('documents')->where('document_id', $documentId)->update([
                'current_section_id' => $destinationSectionId,
                'current_responsible_user_id' => $destinationUser,
                'status_id' => $action['resulting_status_id'] ?? $document['status_id'],
                'updated_at' => new RawSql('SYSUTCDATETIME()'),
            ])) {
                throw new RuntimeException('The document current routing state could not be updated.');
            }
            $this->engagements->endActive($documentId, 'ROUTED');

            if (! $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => $documentId,
                'module_name' => 'Document Routing',
                'action_name' => 'ROUTE',
                'description' => 'Routed document ' . $document['document_control_number']
                    . ($action !== null ? ' using action ' . $action['action_name'] : ' without recording an action'),
                'old_value' => json_encode([
                    'section_id' => $document['current_section_id'],
                    'responsible_user_id' => $fromUserId,
                    'status_id' => $document['status_id'],
                ], JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode([
                    'section_id' => $destinationSectionId,
                    'responsible_user_id' => $destinationUser,
                    'status_id' => $action['resulting_status_id'] ?? $document['status_id'],
                    'action' => $action['action_name'] ?? null,
                    'evidence_file' => $evidenceName,
                ], JSON_UNESCAPED_SLASHES),
                'ip_address' => $this->nullable($ipAddress),
                'browser' => $this->nullable($browser),
            ])) {
                throw new RuntimeException('The routing audit record could not be saved.');
            }

            $this->db->transCommit();
            $notificationUser = $terminalAction ? null : $destinationUser;
            $notificationReassigned = $isReassigned;
        } catch (Throwable $e) {
            $this->db->transRollback();
            if ($evidencePath !== null && is_file($evidencePath)) {
                @unlink($evidencePath);
            }
            throw $e;
        }

        $this->notifications->afterRouting($documentId, $routingId, $notificationUser, $notificationReassigned);
    }

    /** @param array<string, mixed> $input */
    public function reassign(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $routingId = '';
        $notificationUser = null;
        $this->db->transBegin();
        try {
            $document = $this->concurrency->lockState($documentId);
            if ($document === null) {
                throw new RuntimeException('Document not found.');
            }
            $scope = new OrganizationScopeService($this->db);
            if (! $scope->canAccessDocument($actorId, $documentId)) {
                throw new RuntimeException('This document is outside your office scope.');
            }
            if ((int) $document['is_terminal'] === 1) {
                throw new RuntimeException('Completed documents cannot be reassigned.');
            }
            if (! $this->policy->canReassign($document, $actorId)) {
                throw new RuntimeException('Only the receiving personnel before the first routing movement, the current Section Head, or Super Admin may correct this assignment.');
            }
            $this->engagement->assertMutationAllowed($documentId, $actorId);
            $this->concurrency->assertExpectedVersion($document, $input['document_version'] ?? null);

            $destinationSectionId = trim((string) ($input['destination_section_id'] ?? ''));
            if ($destinationSectionId === '') {
                throw new RuntimeException('The destination section is outside your office scope.');
            }
            $destinationUserId = trim((string) ($input['destination_user_id'] ?? ''));
            $this->destinations->assertAllowed(
                $actorId,
                $destinationSectionId,
                $destinationUserId,
                'The responsible person must be active and assigned to the selected section.'
            );

            $fromUserId = $document['current_responsible_user_id'] !== null ? (string) $document['current_responsible_user_id'] : null;
            $destinationUser = $destinationUserId !== '' ? $destinationUserId : null;
            if ($destinationSectionId === (string) $document['current_section_id'] && $destinationUser === $fromUserId) {
                throw new RuntimeException('Select a different section or responsible person.');
            }

            $routingId = $this->uuidV4();
            if (! $this->routing->insertRecord([
                'routing_id' => $routingId,
                'document_id' => $documentId,
                'from_section_id' => $document['current_section_id'],
                'from_user_id' => $fromUserId,
                'destination_section_id' => $destinationSectionId,
                'destination_user_id' => $destinationUser,
                'action_id' => null,
                'resulting_status_id' => $document['status_id'],
                'remarks' => null,
                'routed_by' => $actorId,
                'is_reassigned' => 1,
            ])) {
                throw new RuntimeException('The assignment correction could not be saved.');
            }
            if (! $this->db->table('documents')->where('document_id', $documentId)->update([
                'current_section_id' => $destinationSectionId,
                'current_responsible_user_id' => $destinationUser,
                'updated_at' => new RawSql('SYSUTCDATETIME()'),
            ])) {
                throw new RuntimeException('The document current assignment could not be updated.');
            }
            $this->engagements->endActive($documentId, 'REASSIGNED');

            $liveAssignment = $this->db->table('documents')
                ->select('current_section_id, current_responsible_user_id')
                ->where('document_id', $documentId)->get()->getRowArray();
            $liveSection = strtolower((string) ($liveAssignment['current_section_id'] ?? ''));
            $liveUser = ($liveAssignment['current_responsible_user_id'] ?? null) === null
                ? null
                : strtolower((string) $liveAssignment['current_responsible_user_id']);
            $expectedUser = $destinationUser === null ? null : strtolower($destinationUser);
            if ($liveAssignment === null || ! hash_equals(strtolower($destinationSectionId), $liveSection) || $liveUser !== $expectedUser) {
                throw new RuntimeException('The document current assignment did not synchronize correctly.');
            }

            if (! $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => $documentId,
                'module_name' => 'Document Routing',
                'action_name' => 'REASSIGN',
                'description' => 'Corrected document assignment for ' . $document['document_control_number'],
                'old_value' => json_encode(['section_id' => $document['current_section_id'], 'responsible_user_id' => $fromUserId], JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode(['section_id' => $destinationSectionId, 'responsible_user_id' => $destinationUser], JSON_UNESCAPED_SLASHES),
                'ip_address' => $this->nullable($ipAddress),
                'browser' => $this->nullable($browser),
            ])) {
                throw new RuntimeException('The assignment correction audit record could not be saved.');
            }

            $this->db->transCommit();
            $notificationUser = $destinationUser;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $this->notifications->afterRouting($documentId, $routingId, $notificationUser, true);
    }

    public function recall(string $documentId, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $routingId = '';
        $notificationUser = null;
        $this->db->transBegin();
        try {
            $document = $this->concurrency->lockState($documentId);
            if ($document === null) {
                throw new RuntimeException('Document not found.');
            }
            if ((int) $document['is_terminal'] === 1) {
                throw new RuntimeException('Completed documents cannot be recalled.');
            }
            $latest = $this->routing->latestRoutingEvent($documentId);
            if (! $this->policy->canRecall($document, $actorId, $latest)) {
                throw new RuntimeException('This routing can no longer be recalled. The destination section has already acted, or you are not the authorized sender/Section Head.');
            }

            $returnSectionId = (string) $latest['from_section_id'];
            $currentSectionId = (string) $document['current_section_id'];
            $currentUserId = $document['current_responsible_user_id'] !== null ? (string) $document['current_responsible_user_id'] : null;
            $returnUserId = null;
            $priorUserId = trim((string) ($latest['from_user_id'] ?? ''));
            if ($priorUserId !== '' && $this->routing->activeUserBelongsToSection($priorUserId, $returnSectionId)) {
                $returnUserId = $priorUserId;
            } else {
                $senderId = trim((string) ($latest['routed_by'] ?? ''));
                if ($senderId !== '' && $this->routing->activeUserBelongsToSection($senderId, $returnSectionId)) {
                    $returnUserId = $senderId;
                }
            }

            $routingId = $this->uuidV4();
            if (! $this->routing->insertRecord([
                'routing_id' => $routingId,
                'document_id' => $documentId,
                'from_section_id' => $currentSectionId,
                'from_user_id' => $currentUserId,
                'destination_section_id' => $returnSectionId,
                'destination_user_id' => $returnUserId,
                'action_id' => null,
                'resulting_status_id' => $document['status_id'],
                'remarks' => 'Routing recalled before destination action.',
                'routed_by' => $actorId,
                'is_reassigned' => 1,
            ])) {
                throw new RuntimeException('The routing recall could not be saved.');
            }
            if (! $this->db->table('documents')->where('document_id', $documentId)->update([
                'current_section_id' => $returnSectionId,
                'current_responsible_user_id' => $returnUserId,
                'updated_at' => new RawSql('SYSUTCDATETIME()'),
            ])) {
                throw new RuntimeException('The document could not be returned to the sending section.');
            }
            $this->engagements->endActive($documentId, 'RECALLED');

            if (! $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => $documentId,
                'module_name' => 'Document Routing',
                'action_name' => 'RECALL',
                'description' => 'Recalled accidental routing for ' . $document['document_control_number'] . ' before destination action',
                'old_value' => json_encode(['section_id' => $currentSectionId, 'responsible_user_id' => $currentUserId], JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode(['section_id' => $returnSectionId, 'responsible_user_id' => $returnUserId], JSON_UNESCAPED_SLASHES),
                'ip_address' => $this->nullable($ipAddress),
                'browser' => $this->nullable($browser),
            ])) {
                throw new RuntimeException('The routing recall audit record could not be saved.');
            }

            $this->db->transCommit();
            $notificationUser = $returnUserId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $this->notifications->afterRouting($documentId, $routingId, $notificationUser, true);
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
