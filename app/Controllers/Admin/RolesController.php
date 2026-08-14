<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AuthorizationService;
use App\Services\RoleManagementService;
use RuntimeException;
use Throwable;

class RolesController extends BaseController
{
    private RoleManagementService $roles;

    public function __construct()
    {
        $this->roles = new RoleManagementService();
    }

    public function index(): string
    {
        $search = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, ['Active', 'Inactive'], true)) {
            $status = '';
        }

        $canManage = (new AuthorizationService())->hasPermission((string) session()->get('auth_user_id'), 'Roles & Permissions', 'Roles', 'MANAGE');
        return view('admin/roles/index', [
            'title' => 'Roles & Permissions',
            'roles' => $this->roles->listRoles($search, $status),
            'search' => $search,
            'status' => $status,
            'canManage' => $canManage,
        ]);
    }

    public function show(string $id)
    {
        $role = $this->roles->getRole($id);
        if ($role === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        return view('admin/roles/show', [
            'title' => $role['role_name'],
            'role' => $role,
            'permissionGroups' => $this->roles->permissionGroups(),
            'canManage' => (new AuthorizationService())->hasPermission((string) session()->get('auth_user_id'), 'Roles & Permissions', 'Roles', 'MANAGE'),
        ]);
    }

    public function new(): string
    {
        return view('admin/roles/form', $this->formData(null, true));
    }

    public function create()
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->roles->createRole($input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/roles'))->with('success', 'Role created.');
    }

    public function edit(string $id)
    {
        $role = $this->roles->getRole($id);
        if ($role === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        return view('admin/roles/form', $this->formData($role, false));
    }

    public function update(string $id)
    {
        $input = $this->input();
        if (! $this->valid($input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->roles->updateRole($id, $input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/roles/' . $id))->with('success', 'Role updated.');
    }

    public function delete(string $id)
    {
        try {
            $this->roles->deleteRole($id, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/roles'))->with('success', 'Custom role deleted.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'role_name' => $this->request->getPost('role_name'),
            'description' => $this->request->getPost('description'),
            'active' => $this->request->getPost('active'),
            'permission_ids' => (array) $this->request->getPost('permission_ids'),
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input): bool
    {
        return $this->validateData($input, [
            'role_name' => 'required|max_length[100]',
            'description' => 'required|max_length[500]',
            'active' => 'required|in_list[0,1]',
        ]);
    }

    /** @param array<string, mixed>|null $role @return array<string, mixed> */
    private function formData(?array $role, bool $creating): array
    {
        return [
            'title' => $creating ? 'Create Role' : 'Edit Role',
            'role' => $role,
            'creating' => $creating,
            'permissionGroups' => $this->roles->permissionGroups(),
        ];
    }

    private function actorId(): string
    {
        return (string) session()->get('auth_user_id');
    }

    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array
    {
        return [
            'ip' => (string) $this->request->getIPAddress(),
            'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000),
        ];
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof RuntimeException ? $e->getMessage() : 'The role could not be saved. Please review the data and try again.';
    }
}
