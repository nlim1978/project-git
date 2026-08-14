<?php

namespace App\Models;

use CodeIgniter\Model;

class AttachmentModel extends Model
{
    protected $table = 'attachments';
    protected $primaryKey = 'attachment_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'document_id', 'file_name', 'original_file_name', 'file_extension', 'file_size_bytes',
        'file_path', 'mime_type', 'converted_to_pdf', 'uploaded_by',
    ];

    /** @param array<string, mixed> $data */
    public function insertRecord(array $data): bool
    {
        return $this->db->table($this->table)->insert($data);
    }

    /** @return array<int, array<string, mixed>> */
    public function forDocument(string $documentId): array
    {
        return $this->where('document_id', $documentId)->orderBy('uploaded_at', 'ASC')->findAll();
    }

    /** @return array<string, mixed>|null */
    public function belongingToDocument(string $attachmentId, string $documentId): ?array
    {
        return $this->where('attachment_id', $attachmentId)->where('document_id', $documentId)->first();
    }
}
