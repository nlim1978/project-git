<?php

namespace App\Services;

use App\Models\DocumentModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\RawSql;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class DocumentReceivingCommandService extends BaseService
{
    private DocumentModel $documents;
    private NotificationDeliveryService $notifications;
    private DocumentAttachmentStorageService $storage;
    private DocumentConcurrencyService $concurrency;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->documents = new DocumentModel();
        $this->notifications = new NotificationDeliveryService();
        $this->storage = new DocumentAttachmentStorageService($this->db);
        $this->concurrency = new DocumentConcurrencyService($this->db);
    }

    /** @param array<string, mixed> $input */
    public function updateDocument(string $documentId, array $input, string $actorId, ?string $ipAddress = null, ?string $browser = null): void
    {
        $this->db->transBegin();
        try {
            $locked = $this->concurrency->lockState($documentId);
            if ($locked === null) {
                throw new RuntimeException('Document not found.');
            }
            $this->concurrency->assertExpectedVersion($locked, $input['document_version'] ?? null);

            $scope = (new OrganizationScopeService($this->db))->documentDataScope($actorId);
            $existing = $this->documents->receivingDetail($documentId, $scope->officeId(), $scope->sectionIds());
            if ($existing === null) {
                throw new RuntimeException('Document not found.');
            }

            $updated = [
                'subject' => trim((string) $input['subject']),
                'description' => trim((string) $input['description']),
                'sender_name' => trim((string) $input['sender_name']),
                'sender_organization' => $this->nullable($input['sender_organization'] ?? null),
                'sender_email' => trim((string) $input['sender_email']),
                'sender_contact_number' => $this->nullable($input['sender_contact_number'] ?? null),
                'remarks' => $this->nullable($input['remarks'] ?? null),
                'updated_at' => new RawSql('SYSUTCDATETIME()'),
            ];
            $oldSnapshot = [
                'subject' => $existing['subject'],
                'description' => $existing['description'],
                'sender_name' => $existing['sender_name'],
                'sender_organization' => $existing['sender_organization'],
                'sender_email' => $existing['sender_email'],
                'sender_contact_number' => $existing['sender_contact_number'],
                'remarks' => $existing['remarks'],
            ];
            $newSnapshot = $updated;
            unset($newSnapshot['updated_at']);

            if (! $this->documents->updateRecord($documentId, $updated)) {
                throw new RuntimeException('The document changes could not be saved.');
            }

            $auditInserted = $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => $documentId,
                'module_name' => 'Receiving',
                'action_name' => 'UPDATE',
                'description' => 'Corrected received document details for ' . $existing['document_control_number'],
                'old_value' => json_encode($oldSnapshot, JSON_UNESCAPED_SLASHES),
                'new_value' => json_encode($newSnapshot, JSON_UNESCAPED_SLASHES),
                'ip_address' => $this->nullable($ipAddress),
                'browser' => $this->nullable($browser),
            ]);
            if (! $auditInserted) {
                throw new RuntimeException('The document update audit record could not be saved.');
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array<int, UploadedFile> $files */
    public function register(array $input, array $files, string $actorId, ?string $ipAddress = null, ?string $browser = null): string
    {
        if (! (new OrganizationScopeService($this->db))->canManageDocumentsInSection($actorId, (string) ($input['initial_section_id'] ?? ''))) {
            throw new RuntimeException('The receiving section is outside your office scope.');
        }
        $type = $this->documents->activeType((string) $input['document_type_id']);
        if ($type === null) {
            throw new RuntimeException('Select an active document type.');
        }

        $sectionId = (string) $input['initial_section_id'];
        if (! $this->documents->activeSectionExists($sectionId)) {
            throw new RuntimeException('Select an active receiving section.');
        }

        $responsibleUserId = trim((string) ($input['initial_responsible_user_id'] ?? ''));
        if ($responsibleUserId !== '' && ! $this->documents->activeUserBelongsToSection($responsibleUserId, $sectionId)) {
            throw new RuntimeException('The responsible user must be active and assigned to the selected section.');
        }

        $validFiles = $this->storage->validateReceivingFiles($files);
        $receivedStatusId = $this->documents->receivedStatusId();
        if ($receivedStatusId === null) {
            throw new RuntimeException('The RECEIVED document status is missing or inactive.');
        }

        [$receivingNumber, $controlNumber] = $this->generateNumbers((string) $type['prefix']);
        $qrToken = bin2hex(random_bytes(32));
        $clientTrackingToken = bin2hex(random_bytes(16));
        $movedFiles = [];
        $registeredDocument = null;
        $documentId = '';
        $this->db->transBegin();

        try {
            $clientTrackingReference = $this->nextClientTrackingReference();
            $inserted = $this->documents->insertRecord([
                'receiving_number' => $receivingNumber,
                'document_control_number' => $controlNumber,
                'qr_token' => $qrToken,
                'client_tracking_token' => $clientTrackingToken,
                'client_tracking_reference' => $clientTrackingReference,
                'document_type_id' => $type['document_type_id'],
                'subject' => trim((string) $input['subject']),
                'description' => trim((string) $input['description']),
                'sender_name' => trim((string) $input['sender_name']),
                'sender_organization' => $this->nullable($input['sender_organization'] ?? null),
                'sender_email' => trim((string) $input['sender_email']),
                'sender_contact_number' => $this->nullable($input['sender_contact_number'] ?? null),
                'date_received' => gmdate('Y-m-d H:i:s'),
                'receiving_personnel_id' => $actorId,
                'initial_section_id' => $sectionId,
                'initial_responsible_user_id' => $responsibleUserId !== '' ? $responsibleUserId : null,
                'current_section_id' => $sectionId,
                'current_responsible_user_id' => $responsibleUserId !== '' ? $responsibleUserId : null,
                'status_id' => $receivedStatusId,
                'remarks' => $this->nullable($input['remarks'] ?? null),
                'created_by' => $actorId,
            ]);
            if ($inserted === false) {
                throw new RuntimeException('The document could not be saved.');
            }

            $registeredDocument = $this->documents->byQrToken($qrToken);
            if ($registeredDocument === null) {
                throw new RuntimeException('The document could not be retrieved after registration.');
            }

            $documentId = (string) $registeredDocument['document_id'];
            foreach ($validFiles as $file) {
                $movedFiles[] = $this->storage->storeReceivingAttachment($file, $documentId, $actorId);
            }

            $auditInserted = $this->db->table('audit_logs')->insert([
                'user_id' => $actorId,
                'document_id' => $documentId,
                'module_name' => 'Receiving',
                'action_name' => 'CREATE',
                'description' => 'Registered received document ' . $controlNumber,
                'new_value' => json_encode([
                    'receiving_number' => $receivingNumber,
                    'document_control_number' => $controlNumber,
                    'subject' => trim((string) $input['subject']),
                    'initial_section_id' => $sectionId,
                    'email_notification_requested' => (string) ($input['send_email_notification'] ?? '1') === '1',
                ], JSON_UNESCAPED_SLASHES),
                'ip_address' => $this->nullable($ipAddress),
                'browser' => $this->nullable($browser),
            ]);
            if (! $auditInserted) {
                throw new RuntimeException('The document audit record could not be saved.');
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            foreach ($movedFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            throw $e;
        }

        // Delivery happens only after the business transaction is durable. Notification failure
        // is recorded separately and must never undo successful registration.
        if ($registeredDocument !== null) {
            $this->notifications->afterRegistration($registeredDocument, (string) ($input['send_email_notification'] ?? '1') === '1');
        }
        return $documentId;
    }

    /** @return array{0:string,1:string} */
    private function generateNumbers(string $prefix): array
    {
        $date = gmdate('Ymd');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = strtoupper(bin2hex(random_bytes(4)));
            $receiving = 'RCV-' . $date . '-' . $suffix;
            $control = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'DOC', 0, 12)) . '-' . $date . '-' . $suffix;

            if ($this->db->table('documents')->groupStart()->where('receiving_number', $receiving)->orWhere('document_control_number', $control)->groupEnd()->countAllResults() === 0) {
                return [$receiving, $control];
            }
        }
        throw new RuntimeException('A unique document number could not be generated. Please try again.');
    }

    private function nextClientTrackingReference(): string
    {
        $year = (int) gmdate('Y');

        $query = $this->db->query(
            '
            SELECT last_number
            FROM dbo.client_tracking_sequences
            WHERE tracking_year = ?
            ',
            [$year]
        );

        if ($query === false) {
            $error = $this->db->error();

            throw new RuntimeException(
                'Tracking sequence query failed: '
                . ($error['message'] ?? 'Unknown SQL Server error.')
            );
        }

        $row = $query->getRowArray();

        if ($row === null) {
            $number = 1;

            $ok = $this->db
                ->table('client_tracking_sequences')
                ->insert([
                    'tracking_year' => $year,
                    'last_number'   => $number,
                ]);

            if (! $ok) {
                throw new RuntimeException(
                    'Unable to create the tracking sequence.'
                );
            }
        } else {
            $number = ((int) $row['last_number']) + 1;

            $ok = $this->db
                ->table('client_tracking_sequences')
                ->where('tracking_year', $year)
                ->update([
                    'last_number' => $number,
                ]);

            if (! $ok) {
                throw new RuntimeException(
                    'Unable to update the tracking sequence.'
                );
            }
        }

        return sprintf(
            'TRK-%s-%04d',
            gmdate('my'),
            $number
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
