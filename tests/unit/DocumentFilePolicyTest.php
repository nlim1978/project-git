<?php

use App\Policies\DocumentFilePolicy;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

final class DocumentFilePolicyTest extends CIUnitTestCase
{
    public function testReceivingRejectsDisguisedPdfContent(): void
    {
        $file = $this->uploadedFile('invoice.pdf', 'text/plain');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('content does not match');

        (new DocumentFilePolicy())->assertReceivingFile($file);
    }

    public function testEvidenceRejectsOfficeDocumentsEvenWithTrustedOfficeMime(): void
    {
        $file = $this->uploadedFile('evidence.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF, JPG, or PNG');

        (new DocumentFilePolicy())->assertRoutingEvidence($file);
    }

    public function testReceivingAcceptsMatchingServerDetectedMime(): void
    {
        $file = $this->uploadedFile('scan.png', 'image/png');

        (new DocumentFilePolicy())->assertReceivingFile($file);
        $this->addToAssertionCount(1);
    }

    public function testMimeComparisonIgnoresCaseAndParameters(): void
    {
        $policy = new DocumentFilePolicy();

        $this->assertTrue($policy->isAllowedMime('JPG', 'IMAGE/JPEG; charset=binary'));
        $this->assertFalse($policy->isAllowedMime('jpg', 'text/html'));
    }

    private function uploadedFile(string $name, string $detectedMime): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('isValid')->willReturn(true);
        $file->method('getSize')->willReturn(1024);
        $file->method('getClientExtension')->willReturn(pathinfo($name, PATHINFO_EXTENSION));
        $file->method('getMimeType')->willReturn($detectedMime);
        return $file;
    }
}