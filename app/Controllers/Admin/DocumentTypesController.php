<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\DocumentTypeManagementService;
use RuntimeException;
use Throwable;

class DocumentTypesController extends BaseController
{
    private DocumentTypeManagementService $types;

    public function __construct()
    {
        $this->types = new DocumentTypeManagementService();
    }

    public function index(): string
    {
        $search = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, ['Active', 'Inactive'], true)) {
            $status = '';
        }
        return view('admin/document_types/index', [
            'title' => 'Document Types', 'documentTypes' => $this->types->list($search, $status),
            'search' => $search, 'status' => $status,
        ]);
    }

    public function new(): string
    {
        return view('admin/document_types/form', ['title' => 'Create Document Type', 'documentType' => null, 'creating' => true]);
    }

    public function show(string $id)
    {
        $documentType = $this->types->find($id);
        if ($documentType === null) {
            return $this->notFound();
        }
        $documentType['document_count'] = $this->types->documentCount($id);
        return view('admin/document_types/show', ['title' => $documentType['type_name'], 'documentType' => $documentType]);
    }

    public function create()
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->types->create($input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/document-types'))->with('success', 'Document type created.');
    }

    public function edit(string $id)
    {
        $documentType = $this->types->find($id);
        if ($documentType === null) {
            return $this->notFound();
        }
        return view('admin/document_types/form', ['title' => 'Edit Document Type', 'documentType' => $documentType, 'creating' => false]);
    }

    public function update(string $id)
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->types->update($id, $input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/document-types/' . $id))->with('success', 'Document type updated.');
    }

    public function status(string $id)
    {
        try {
            $this->types->toggleStatus($id, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/document-types'))->with('success', 'Document type status updated.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'type_code' => $this->request->getPost('type_code'), 'type_name' => $this->request->getPost('type_name'),
            'prefix' => $this->request->getPost('prefix'), 'description' => $this->request->getPost('description'),
            'active' => $this->request->getPost('active'),
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input): bool
    {
        return $this->validateData($input, [
            'type_code' => 'required|max_length[20]', 'type_name' => 'required|max_length[100]',
            'prefix' => 'required|max_length[20]', 'description' => 'permit_empty|max_length[500]',
            'active' => 'required|in_list[0,1]',
        ]);
    }

    private function actorId(): string { return (string) session()->get('auth_user_id'); }
    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array { return ['ip' => (string) $this->request->getIPAddress(), 'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000)]; }
    private function safeMessage(Throwable $e): string { return $e instanceof RuntimeException ? $e->getMessage() : 'The document type could not be saved. Please review the data and try again.'; }
    private function notFound() { return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404')); }
}
