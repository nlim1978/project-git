<?php

namespace App\Services;

use App\Models\AttachmentModel;
use App\Models\DocumentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentReceivingService extends BaseService
{
    private DocumentModel $documents;
    private AttachmentModel $attachments;
    private NotificationDeliveryService $notifications;
    private DocumentReceivingCommandService $commands;

    public function __construct()
    {
        parent::__construct();
        $this->documents = new DocumentModel();
        $this->attachments = new AttachmentModel();
        $this->notifications = new NotificationDeliveryService();
        $this->commands = new DocumentReceivingCommandService($this->db);
    }

    /** @return array<string, mixed> */
    public function formReferences(string $actorId): array
    {
        $dataScope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
        $sections = $this->documents->activeSections($dataScope->officeId());
        $sectionUsers = $this->documents->activeSectionUsers($dataScope->officeId());
        if ($dataScope->sectionIds() !== null) {
            $allowed = array_fill_keys(array_map('strtolower', $dataScope->sectionIds()), true);
            $sections = array_values(array_filter($sections, static fn (array $row): bool => isset($allowed[strtolower((string) $row['section_id'])])));
            $sectionUsers = array_values(array_filter($sectionUsers, static fn (array $row): bool => isset($allowed[strtolower((string) $row['section_id'])])));
        }
        return [
            'documentTypes' => $this->documents->activeTypes(),
            'sections' => $sections,
            'sectionUsers' => $sectionUsers,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listDocuments(string $actorId): array
    {
        $scope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
        return $this->documents->receivingList(100, $scope->officeId(), $scope->sectionIds());
    }

    /** @return array<string, mixed>|null */
    public function getDocument(string $documentId, string $actorId, bool $includeNotifications = false): ?array
    {
        $scope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
        $document = $this->documents->receivingDetail($documentId, $scope->officeId(), $scope->sectionIds());
        if ($document !== null) {
            $document['attachments'] = $this->attachments->forDocument($documentId);
            $document['notifications'] = $includeNotifications
                ? $this->notifications->logsForDocument($documentId)
                : [];
        }
        return $document;
    }

    /** @return array<string, mixed>|null */
    public function getAttachment(string $documentId, string $attachmentId): ?array
    {
        return $this->attachments->belongingToDocument($attachmentId, $documentId);
    }

    /** @param array<string, mixed> $input */
    public function updateDocument(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $this->commands->updateDocument($documentId, $input, $actorId, $ipAddress, $browser);
    }

    /** @param array<string, mixed> $input @param array<int, UploadedFile> $files */
    public function register(array $input, array $files, string $actorId, ?string $ipAddress = null, ?string $browser = null): string
    {
        return $this->commands->register($input, $files, $actorId, $ipAddress, $browser);
    }
}

