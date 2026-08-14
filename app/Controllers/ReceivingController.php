<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\ClientTrackingService;
use App\Services\DocumentReceivingService;
use App\Services\DocumentRoutingService;
use App\Services\QrCodeService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class ReceivingController extends BaseController
{
    private DocumentReceivingService $receiving;

    public function __construct()
    {
        $this->receiving = new DocumentReceivingService();
    }

    /**
     * Display the receiving document list.
     */
    public function index(): string
    {
        $actorId = $this->actorId();

        $authorization = new AuthorizationService();
        $documentAccess = new DocumentRoutingService();

        $canUpdate = $authorization->hasPermission(
            $actorId,
            'Receiving',
            'Receiving',
            'UPDATE'
        );

        $documents = $this->receiving->listDocuments($actorId);

        foreach ($documents as &$document) {
            $documentId = (string) ($document['document_id'] ?? '');

            $document['can_update'] = $canUpdate
                && $documentId !== ''
                && $documentAccess->canOperateOnDocument($documentId, $actorId);
        }

        unset($document);

        return view('receiving/index', [
            'title'     => 'Receiving',
            'documents' => $documents,
            'canCreate' => $authorization->hasPermission(
                $actorId,
                'Receiving',
                'Receiving',
                'CREATE'
            ),
        ]);
    }

    /**
     * Display the document registration form.
     */
    public function new(): string
    {
        return view(
            'receiving/form',
            array_merge(
                [
                    'title' => 'Register document',
                ],
                $this->receiving->formReferences($this->actorId())
            )
        );
    }

    /**
     * Register a newly received document.
     */
    public function create(): RedirectResponse
    {
        $input = $this->createInput();

        if (! $this->validateData($input, $this->createValidationRules())) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        try {
            $documentId = $this->receiving->register(
                $input,
                (array) $this->request->getFileMultiple('attachments'),
                $this->actorId(),
                $this->clientIp(),
                $this->userAgent()
            );
        } catch (Throwable $e) {
            return $this->handleDocumentException(
                $e,
                'DOCUMENT CREATE FAILED'
            );
        }

        return redirect()
            ->to(site_url('receiving/' . $documentId))
            ->with('success', 'Document registered successfully.');
    }

    /**
     * Display one received document.
     */
    public function show(string $id)
    {
        $actorId = $this->actorId();

        $authorization = new AuthorizationService();
        $routing = new DocumentRoutingService();

        $roles = $authorization->roleNames($actorId);

        $isAdministrator =
            in_array('Administrator', $roles, true)
            || in_array('Super Administrator', $roles, true);

        $document = $this->receiving->getDocument(
            $id,
            $actorId,
            $isAdministrator
        );

        if ($document === null) {
            return $this->notFound();
        }

        $canOperate = $routing->canOperateOnDocument($id, $actorId);

        return view('receiving/show', [
            'title'             => 'Received document',
            'document'          => $document,
            'canOpenDocument'   => $canOperate
                && $authorization->hasPermission(
                    $actorId,
                    'Document Details',
                    'Document Details',
                    'VIEW'
                ),
            'canUpdate'         => $canOperate
                && $authorization->hasPermission(
                    $actorId,
                    'Receiving',
                    'Receiving',
                    'UPDATE'
                ),
            'canDownload'       => $canOperate,
            'isAdministrator'   => $isAdministrator,
        ]);
    }

    /**
     * Display the document edit form.
     */
    public function edit(string $id)
    {
        $actorId = $this->actorId();
        $routing = new DocumentRoutingService();

        $document = $this->receiving->getDocument($id, $actorId);

        if (
            $document === null
            || ! $routing->canOperateOnDocument($id, $actorId)
        ) {
            return $this->notFound();
        }

        return view('receiving/edit', [
            'title'      => 'Edit ' . $document['document_control_number'],
            'document'   => $document,
            'assignment' => $routing->reassignmentContext($id, $actorId),
        ]);
    }

    /**
     * Update document details.
     */
    public function update(string $id): RedirectResponse|ResponseInterface
    {
        $actorId = $this->actorId();

        if (
            ! (new DocumentRoutingService())
                ->canOperateOnDocument($id, $actorId)
        ) {
            return $this->notFound();
        }

        $input = $this->updateInput();

        if (! $this->validateData($input, $this->updateValidationRules())) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        try {
            $this->receiving->updateDocument(
                $id,
                $input,
                $actorId,
                $this->clientIp(),
                $this->userAgent()
            );
        } catch (Throwable $e) {
            return $this->handleDocumentException(
                $e,
                'DOCUMENT UPDATE FAILED'
            );
        }

        return redirect()
            ->to(site_url('receiving/' . $id))
            ->with('success', 'Document details updated successfully.');
    }

    /**
     * Download a document attachment.
     */
    public function attachment(
        string $documentId,
        string $attachmentId
    ): ResponseInterface {
        $actorId = $this->actorId();

        $routing = new DocumentRoutingService();

        if (! $routing->canDownloadAttachment($documentId, $actorId)) {
            return $this->notFound();
        }

        $attachment = $this->receiving->getAttachment(
            $documentId,
            $attachmentId
        );

        if ($attachment === null) {
            return $this->notFound();
        }

        $uploadsRoot = realpath(WRITEPATH . 'uploads');

        $relativePath = (string) ($attachment['file_path'] ?? '');

        $path = realpath(
            WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $relativePath
        );

        if (
            $uploadsRoot === false
            || $path === false
            || ! str_starts_with(
                $path,
                $uploadsRoot . DIRECTORY_SEPARATOR
            )
            || ! is_file($path)
        ) {
            return $this->notFound();
        }

        $downloadName = preg_replace(
            '/[\r\n"\\\\\/]+/',
            '_',
            (string) ($attachment['original_file_name'] ?? '')
        );

        if (! is_string($downloadName) || trim($downloadName) === '') {
            $downloadName = 'attachment';
        }

        return $this->response
            ->download($path, null)
            ->setFileName($downloadName)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store');
    }

    /**
     * Generate the client tracking QR code.
     */
    public function clientTrackingQr(string $id): ResponseInterface
    {
        $document = $this->receiving->getDocument(
            $id,
            $this->actorId()
        );

        if (
            $document === null
            || empty($document['client_tracking_token'])
        ) {
            return $this->notFound();
        }

        $displayToken = ClientTrackingService::displayToken(
            (string) $document['client_tracking_token']
        );

        $target = site_url('track')
            . '#'
            . rawurlencode($displayToken);

        return $this->response
            ->setHeader(
                'Content-Type',
                'image/svg+xml; charset=UTF-8'
            )
            ->setHeader(
                'Cache-Control',
                'private, max-age=300'
            )
            ->setBody(
                (new QrCodeService())->svg($target)
            );
    }

    /**
     * Build input for document registration.
     *
     * @return array<string, mixed>
     */
    private function createInput(): array
    {
        return [
            'document_type_id' =>
                $this->request->getPost('document_type_id'),

            'subject' =>
                $this->request->getPost('subject'),

            'description' =>
                $this->request->getPost('description'),

            'sender_name' =>
                $this->request->getPost('sender_name'),

            'sender_organization' =>
                $this->request->getPost('sender_organization'),

            'sender_email' =>
                $this->request->getPost('sender_email'),

            'sender_contact_number' =>
                $this->request->getPost('sender_contact_number'),

            'initial_section_id' =>
                $this->request->getPost('initial_section_id'),

            'initial_responsible_user_id' =>
                $this->request->getPost('initial_responsible_user_id'),

            'remarks' =>
                $this->request->getPost('remarks'),

            'send_email_notification' =>
                $this->request->getPost('send_email_notification'),
        ];
    }

    /**
     * Build input for document update.
     *
     * @return array<string, mixed>
     */
    private function updateInput(): array
    {
        return [
            'document_version' =>
                $this->request->getPost('document_version'),

            'subject' =>
                $this->request->getPost('subject'),

            'description' =>
                $this->request->getPost('description'),

            'sender_name' =>
                $this->request->getPost('sender_name'),

            'sender_organization' =>
                $this->request->getPost('sender_organization'),

            'sender_email' =>
                $this->request->getPost('sender_email'),

            'sender_contact_number' =>
                $this->request->getPost('sender_contact_number'),

            'remarks' =>
                $this->request->getPost('remarks'),
        ];
    }

    /**
     * Validation rules for registration.
     *
     * @return array<string, string>
     */
    private function createValidationRules(): array
    {
        return [
            'document_type_id' =>
                'required|max_length[36]',

            'subject' =>
                'required|max_length[255]',

            'description' =>
                'required|max_length[5000]',

            'sender_name' =>
                'required|max_length[255]',

            'sender_organization' =>
                'permit_empty|max_length[255]',

            'sender_email' =>
                'required|valid_email|max_length[254]',

            'sender_contact_number' =>
                'permit_empty|max_length[20]',

            'initial_section_id' =>
                'required|max_length[36]',

            'initial_responsible_user_id' =>
                'permit_empty|max_length[36]',

            'remarks' =>
                'permit_empty|max_length[5000]',

            'send_email_notification' =>
                'required|in_list[0,1]',
        ];
    }

    /**
     * Validation rules for document updates.
     *
     * @return array<string, string>
     */
    private function updateValidationRules(): array
    {
        return [
            'document_version' =>
                'required|max_length[40]',

            'subject' =>
                'required|max_length[255]',

            'description' =>
                'required|max_length[5000]',

            'sender_name' =>
                'required|max_length[255]',

            'sender_organization' =>
                'permit_empty|max_length[255]',

            'sender_email' =>
                'required|valid_email|max_length[254]',

            'sender_contact_number' =>
                'permit_empty|max_length[20]',

            'remarks' =>
                'permit_empty|max_length[5000]',
        ];
    }

    /**
     * Handle and log document-related exceptions.
     */
    private function handleDocumentException(
        Throwable $e,
        string $context
    ): RedirectResponse {
        log_message(
            'error',
            implode(PHP_EOL, [
                $context,
                'Type: {type}',
                'Message: {message}',
                'File: {file}',
                'Line: {line}',
                'Actor: {actor}',
                'IP: {ip}',
                'Trace:',
                '{trace}',
            ]),
            [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'actor'   => $this->actorId(),
                'ip'      => $this->clientIp(),
                'trace'   => $e->getTraceAsString(),
            ]
        );

        if (ENVIRONMENT === 'development') {
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    'error',
                    sprintf(
                        '%s: %s',
                        get_class($e),
                        $e->getMessage()
                    )
                );
        }

        return redirect()
            ->back()
            ->withInput()
            ->with(
                'error',
                $this->safeMessage($e)
            );
    }

    /**
     * Return an appropriate user-facing exception message.
     */
    private function safeMessage(Throwable $e): string
    {
        if ($e instanceof RuntimeException) {
            return $e->getMessage();
        }

        return 'The document could not be saved. Please review the data and try again.';
    }

    /**
     * Get the authenticated actor ID.
     */
    private function actorId(): string
    {
        return (string) session()->get('auth_user_id');
    }

    /**
     * Get the requesting client's IP address.
     */
    private function clientIp(): string
    {
        return (string) $this->request->getIPAddress();
    }

    /**
     * Get a safely bounded browser user-agent string.
     */
    private function userAgent(): string
    {
        return mb_substr(
            (string) $this->request->getUserAgent(),
            0,
            1000
        );
    }

    /**
     * Generate the standard application 404 response.
     */
    private function notFound(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(404)
            ->setBody(
                view('errors/html/error_404')
            );
    }
}