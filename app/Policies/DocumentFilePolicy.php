<?php

namespace App\Policies;

use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

final class DocumentFilePolicy
{
    public const MAX_BYTES = 10485760;

    /** @var array<string, array<int, string>> */
    private const MIME_BY_EXTENSION = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    private const EVIDENCE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function assertReceivingFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('One of the attachments could not be uploaded.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Each attachment must be 10 MB or smaller.');
        }

        $extension = strtolower($file->getClientExtension());
        if (! array_key_exists($extension, self::MIME_BY_EXTENSION)) {
            throw new RuntimeException('Allowed attachment types: PDF, Word, Excel, JPG, and PNG.');
        }
        if (! $this->isAllowedMime($extension, $this->trustedMimeType($file))) {
            throw new RuntimeException('The attachment content does not match its file extension.');
        }
        if (in_array($extension, ['doc', 'xls'], true)) {
            $this->assertLegacyOfficeContainer($file);
        }
        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $this->assertOpenXmlContainer($file, $extension);
        }
    }

    public function assertRoutingEvidence(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new RuntimeException('The evidence file could not be uploaded.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Evidence must be 10 MB or smaller.');
        }

        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, self::EVIDENCE_EXTENSIONS, true)) {
            throw new RuntimeException('Evidence must be a PDF, JPG, or PNG file.');
        }
        if (! $this->isAllowedMime($extension, $this->trustedMimeType($file))) {
            throw new RuntimeException('The evidence content does not match its file extension.');
        }
    }

    public function isAllowedMime(string $extension, string $mimeType): bool
    {
        $extension = strtolower(trim($extension));
        $mimeType = strtolower(trim(strtok($mimeType, ';') ?: $mimeType));
        return isset(self::MIME_BY_EXTENSION[$extension])
            && in_array($mimeType, self::MIME_BY_EXTENSION[$extension], true);
    }

    private function trustedMimeType(UploadedFile $file): string
    {
        // CodeIgniter falls back to the client-supplied MIME value when fileinfo
        // is unavailable. Upload authorization must fail closed instead.
        if (! function_exists('finfo_open')) {
            throw new RuntimeException('File content validation is unavailable. Contact the system administrator.');
        }
        return $file->getMimeType();
    }

    private function assertLegacyOfficeContainer(UploadedFile $file): void
    {
        $handle = @fopen($file->getTempName(), 'rb');
        $header = $handle === false ? false : fread($handle, 8);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($header === false || $header !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw new RuntimeException('The attachment content does not match its file extension.');
        }
    }

    private function assertOpenXmlContainer(UploadedFile $file, string $extension): void
    {
        $contents = @file_get_contents($file->getTempName());
        $folder = $extension === 'docx' ? 'word/' : 'xl/';
        if ($contents === false
            || ! str_starts_with($contents, 'PK')
            || ! str_contains($contents, '[Content_Types].xml')
            || ! str_contains($contents, $folder)
            || stripos($contents, 'vbaProject.bin') !== false) {
            throw new RuntimeException('The attachment content does not match its file extension.');
        }
    }
}
