<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\RoutingActionManagementService;
use RuntimeException;
use Throwable;

class RoutingActionsController extends BaseController
{
    private RoutingActionManagementService $actions;

    public function __construct()
    {
        $this->actions = new RoutingActionManagementService();
    }

    public function index(): string
    {
        $search = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, ['Active', 'Inactive'], true)) {
            $status = '';
        }
        $resultingStatusId = mb_substr(trim((string) $this->request->getGet('resulting_status_id')), 0, 50);
        return view('admin/routing_actions/index', [
            'title' => 'Routing Actions',
            'actions' => $this->actions->list($search, $status, $resultingStatusId),
            'statuses' => $this->actions->statuses(),
            'search' => $search, 'status' => $status, 'resultingStatusId' => $resultingStatusId,
        ]);
    }

    public function new(): string
    {
        return view('admin/routing_actions/form', $this->formData(null, true));
    }

    public function show(string $id)
    {
        $action = $this->actions->find($id);
        if ($action === null) {
            return $this->notFound();
        }
        $statusMap = [];
        foreach ($this->actions->statuses() as $status) {
            $statusMap[(string) $status['status_id']] = $status;
        }
        $roleMap = [];
        foreach ($this->actions->roles() as $role) {
            $roleMap[(string) $role['role_id']] = (string) $role['role_name'];
        }
        $action['resulting_status'] = $statusMap[(string) $action['resulting_status_id']] ?? null;
        $action['role_names'] = ['Administrator (override)'];
        foreach ($action['role_ids'] as $roleId) {
            if (isset($roleMap[$roleId])) {
                $action['role_names'][] = $roleMap[$roleId];
            }
        }
        return view('admin/routing_actions/show', ['title' => $action['action_name'], 'action' => $action]);
    }

    public function create()
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->actions->create($input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/routing-actions'))->with('success', 'Routing action created.');
    }

    public function edit(string $id)
    {
        $action = $this->actions->find($id);
        if ($action === null) {
            return $this->notFound();
        }
        return view('admin/routing_actions/form', $this->formData($action, false));
    }

    public function update(string $id)
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->actions->update($id, $input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/routing-actions/' . $id))->with('success', 'Routing action updated.');
    }

    public function status(string $id)
    {
        try {
            $this->actions->toggleStatus($id, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/routing-actions'))->with('success', 'Routing action status updated.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'action_name' => $this->request->getPost('action_name'),
            'description' => $this->request->getPost('description'),
            'resulting_status_id' => $this->request->getPost('resulting_status_id'),
            'role_ids' => (array) $this->request->getPost('role_ids'),
            'requires_remarks' => $this->request->getPost('requires_remarks') === '1',
            'active' => $this->request->getPost('active'),
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input): bool
    {
        return $this->validateData($input, [
            'action_name' => 'required|max_length[100]',
            'description' => 'required|max_length[500]',
            'resulting_status_id' => 'required|max_length[50]',
            'active' => 'required|in_list[0,1]',
        ]);
    }

    /** @param array<string, mixed>|null $action @return array<string, mixed> */
    private function formData(?array $action, bool $creating): array
    {
        return [
            'title' => $creating ? 'Create Routing Action' : 'Edit Routing Action',
            'action' => $action, 'creating' => $creating,
            'statuses' => $this->actions->statuses(), 'roles' => $this->actions->roles(),
        ];
    }

    private function actorId(): string { return (string) session()->get('auth_user_id'); }
    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array { return ['ip' => (string) $this->request->getIPAddress(), 'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000)]; }
    private function safeMessage(Throwable $e): string { return $e instanceof RuntimeException ? $e->getMessage() : 'The routing action could not be saved. Please review the data and try again.'; }
    private function notFound() { return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404')); }
}
