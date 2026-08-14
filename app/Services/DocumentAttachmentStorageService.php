<?php

namespace App\Services;

use App\Models\AttachmentModel;
use App\Policies\DocumentFilePolicy;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;
use Throwable;

class DocumentAttachmentStorageService extends BaseService
{
    private AttachmentModel $attachments;
    private DocumentFilePolicy $filePolicy;

    public function __construct(?BaseConnection $db = null)
    {
        parent::__construct($db);
        $this->attachments = new AttachmentModel();
        $this->filePolicy = new DocumentFilePolicy();
    }

    /** @param array<int, UploadedFile> $files @return array<int, UploadedFile> */
    public function validateReceivingFiles(array $files): array
    {
        $valid = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $this->filePolicy->assertReceivingFile($file);
            $valid[] = $file;
        }
        return $valid;
    }

    public function storeReceivingAttachment(UploadedFile $file, string $documentId, string $actorId): string
    {
        $this->filePolicy->assertReceivingFile($file);
        [$absolutePath] = $this->store($file, $documentId, $actorId, '', 'Attachment metadata could not be saved.');
        return $absolutePath;
    }

    /** @return array{0:string,1:string} */
    public function storeRoutingEvidence(UploadedFile $file, string $documentId, string $actorId): array
    {
        $this->filePolicy->assertRoutingEvidence($file);
        [$absolutePath, $originalName] = $this->store($file, $documentId, $actorId, 'evidence-', 'Evidence metadata could not be saved.');
        return [$absolutePath, $originalName];
    }

    /** @return array{0:string,1:string} */
    private function store(UploadedFile $file, string $documentId, string $actorId, string $namePrefix, string $metadataError): array
    {
        $extension = strtolower($file->getClientExtension());
        $originalName = mb_substr($file->getClientName(), 0, 255);
        $mimeType = mb_substr((string) $file->getMimeType(), 0, 150);
        $storedName = $namePrefix . bin2hex(random_bytes(16)) . '.' . $extension;
        $relativeDirectory = 'documents/' . $documentId;
        $directory = WRITEPATH . 'uploads/' . $relativeDirectory;
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException($namePrefix === 'evidence-'
                ? 'The evidence storage directory could not be created.'
                : 'The attachment storage directory could not be created.');
        }

        $file->move($directory, $storedName, true);
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $storedName;
        $relativePath = $relativeDirectory . '/' . $storedName;

        try {
            if (! $this->attachments->insertRecord([
                'document_id' => $documentId,
                'file_name' => $storedName,
                'original_file_name' => $originalName,
                'file_extension' => $extension,
                'file_size_bytes' => filesize($absolutePath),
                'file_path' => $relativePath,
                'mime_type' => $mimeType,
                'converted_to_pdf' => 0,
                'uploaded_by' => $actorId,
            ])) {
                throw new RuntimeException($metadataError);
            }
        } catch (Throwable $e) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            throw $e;
        }

        return [$absolutePath, $originalName];
    }
}
