<?php

namespace App\Controllers;

use App\Services\DocumentRoutingService;
use App\Services\AuthorizationService;
use App\Services\QrCodeService;
use RuntimeException;
use Throwable;

class DocumentController extends BaseController
{
    private DocumentRoutingService $documents;

    public function __construct()
    {
        $this->documents = new DocumentRoutingService();
    }

    public function show(string $id)
    {
        $actorId = (string) session()->get('auth_user_id');
        $document = $this->documents->document($id, $actorId);
        if ($document === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $document['can_route'] = $document['can_route_from_assignment'] && (int) $document['is_terminal'] === 0;
        $archiveContext = (int) $document['is_terminal'] === 1
            && (new AuthorizationService())->hasPermission($actorId, 'Document Archive', 'Archive', 'VIEW');
        // Assignment correction is its own controlled capability. The service
        // has already verified receiver/Section Head/Super Admin authority.

        return view('documents/show', [
            'title' => $archiveContext ? 'Archived document' : 'Document details',
            'document' => $document,
            'documentContext' => $archiveContext ? 'archive' : 'inbox',
        ]);
    }

    public function route(string $id)
    {
        $input = [
            'document_version' => $this->request->getPost('document_version'),
            'action_id' => $this->request->getPost('action_id'),
            'destination_section_id' => $this->request->getPost('destination_section_id'),
            'destination_user_id' => $this->request->getPost('destination_user_id'),
            'remarks' => $this->request->getPost('remarks'),
        ];

        if (! $this->validateData($input, [
            'document_version' => 'required|max_length[40]',
            'action_id' => 'required|max_length[36]',
            'destination_section_id' => 'permit_empty|max_length[36]',
            'destination_user_id' => 'permit_empty|max_length[36]',
            'remarks' => 'permit_empty|max_length[5000]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->documents->route(
                $id,
                $input,
                (string) session()->get('auth_user_id'),
                (string) $this->request->getIPAddress(),
                mb_substr((string) $this->request->getUserAgent(), 0, 1000),
                $this->request->getFile('evidence')
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('documents/' . $id))->with('success', 'Document routed successfully.');
    }

    public function reassign(string $id)
    {
        $input = [
            'document_version' => $this->request->getPost('document_version'),
            'destination_section_id' => $this->request->getPost('destination_section_id'),
            'destination_user_id' => $this->request->getPost('destination_user_id'),
        ];

        if (! $this->validateData($input, [
            'document_version' => 'required|max_length[40]',
            'destination_section_id' => 'required|max_length[36]',
            'destination_user_id' => 'permit_empty|max_length[36]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->documents->reassign(
                $id,
                $input,
                (string) session()->get('auth_user_id'),
                (string) $this->request->getIPAddress(),
                mb_substr((string) $this->request->getUserAgent(), 0, 1000)
            );
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('documents/' . $id))->with('success', 'Assignment corrected successfully.');
    }

    public function recall(string $id)
    {
        try {
            $this->documents->recall(
                $id,
                (string) session()->get('auth_user_id'),
                (string) $this->request->getIPAddress(),
                mb_substr((string) $this->request->getUserAgent(), 0, 1000)
            );
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('documents/' . $id))->with('success', 'Routing recalled successfully. You can now send the document to the correct section or person.');
    }

    public function engagement(string $id)
    {
        $state = $this->documents->engagementState($id, (string) session()->get('auth_user_id'));
        if ($state === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Document not found.']);
        }
        return $this->response->setJSON(['engagement' => $state, 'csrf' => csrf_hash()]);
    }

    public function confirm(string $id)
    {
        try {
            $state = $this->documents->confirmEngagement(
                $id, (string) session()->get('auth_user_id'), (string) $this->request->getIPAddress(),
                mb_substr((string) $this->request->getUserAgent(), 0, 1000)
            );
            return $this->response->setJSON(['engagement' => $state, 'csrf' => csrf_hash()]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(409)->setJSON(['error' => $this->safeMessage($e), 'csrf' => csrf_hash()]);
        }
    }

    public function heartbeat(string $id)
    {
        $state = $this->documents->engagementState($id, (string) session()->get('auth_user_id'));
        if ($state === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Document not found.']);
        }
        return $this->response->setJSON(['engagement' => $this->documents->heartbeatEngagement($id, (string) session()->get('auth_user_id')), 'csrf' => csrf_hash()]);
    }

    public function attachment(string $documentId, string $attachmentId)
    {
        $actorId = (string) session()->get('auth_user_id');
        if (! $this->documents->canDownloadAttachment($documentId, $actorId)) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $attachment = $this->documents->attachment($documentId, $attachmentId);
        if ($attachment === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $uploadsRoot = realpath(WRITEPATH . 'uploads');
        $path = realpath(WRITEPATH . 'uploads/' . $attachment['file_path']);
        if ($uploadsRoot === false || $path === false || ! str_starts_with($path, $uploadsRoot . DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $downloadName = preg_replace('/[\r\n"\\\\\/]+/', '_', (string) $attachment['original_file_name']) ?: 'attachment';
        return $this->response->download($path, null)
            ->setFileName($downloadName)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store');
    }

    public function scan(string $token)
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $document = $this->documents->documentByQrToken($token, (string) session()->get('auth_user_id'));
        if ($document === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        return redirect()->to(site_url('documents/' . $document['document_id']));
    }

    public function qr(string $id)
    {
        $document = $this->documents->document($id, (string) session()->get('auth_user_id'));
        if ($document === null || ! $document['can_print_qr']) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        $target = site_url('documents/scan/' . $document['qr_token']);
        $svg = (new QrCodeService())->svg($target);

        return $this->response
            ->setHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody($svg);
    }

    public function printQr(string $id)
    {
        $document = $this->documents->document($id, (string) session()->get('auth_user_id'));
        if ($document === null || ! $document['can_print_qr']) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        return view('documents/qr_print', [
            'title' => 'Print QR Code',
            'document' => $document,
        ]);
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof RuntimeException ? $e->getMessage() : 'The document could not be routed. Please review the data and try again.';
    }
}
