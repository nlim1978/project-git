<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AuthorizationService;
use App\Services\UserManagementService;
use RuntimeException;
use Throwable;

class UsersController extends BaseController
{
    private UserManagementService $users;

    public function __construct()
    {
        $this->users = new UserManagementService();
    }

    public function index(): string
    {
        $authorization = new AuthorizationService();
        $actorId = (string) session()->get('auth_user_id');
        return view('admin/users/index', [
            'title' => 'Users', 'users' => $this->users->listUsers($actorId),
            'canCreate' => $authorization->hasPermission($actorId, 'User Management', 'Users', 'CREATE'),
            'canUpdate' => $authorization->hasPermission($actorId, 'User Management', 'Users', 'UPDATE'),
            'canDeactivate' => $authorization->hasPermission($actorId, 'User Management', 'Users', 'DELETE'),
        ]);
    }

    public function show(string $id)
    {
        $actorId = (string) session()->get('auth_user_id');
        if (! (new \App\Services\OrganizationScopeService())->canAccessUser($actorId, $id)) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        $user = $this->users->getUser($id);
        if ($user === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        $authorization = new AuthorizationService();
        return view('admin/users/show', [
            'title' => $user['first_name'] . ' ' . $user['last_name'],
            'user' => $user,
            'canUpdate' => $authorization->hasPermission($actorId, 'User Management', 'Users', 'UPDATE'),
            'canDeactivate' => $authorization->hasPermission($actorId, 'User Management', 'Users', 'DELETE'),
        ]);
    }

    public function new(): string
    {
        return view('admin/users/form', $this->formData(null, true));
    }

    public function create()
    {
        $input = $this->input();
        if (! $this->valid($input, true)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->users->createUser($input, (string) session()->get('auth_user_id'));
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'User created.');
    }

    public function edit(string $id)
    {
        $actorId = (string) session()->get('auth_user_id');
        if (! (new \App\Services\OrganizationScopeService())->canAccessUser($actorId, $id)) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        $user = $this->users->getUser($id);
        if ($user === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }

        return view('admin/users/form', $this->formData($user, false));
    }

    public function update(string $id)
    {
        $input = $this->input();
        if (! $this->valid($input, false)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $this->users->updateUser($id, $input, (string) session()->get('auth_user_id'));
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('admin/users/' . $id))->with('success', 'User updated.');
    }

    public function deactivate(string $id)
    {
        try {
            $this->users->setAccountStatus($id, 'Inactive', (string) session()->get('auth_user_id'), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'User deactivated.');
    }

    public function status(string $id)
    {
        $user = $this->users->getUser($id);
        if ($user === null) {
            return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404'));
        }
        $status = $user['account_status'] === 'Active' ? 'Inactive' : 'Active';
        try {
            $this->users->setAccountStatus($id, $status, (string) session()->get('auth_user_id'), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }

        return redirect()->back()->with('success', $status === 'Active' ? 'User reactivated.' : 'User deactivated.');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'employee_id' => $this->request->getPost('employee_id'),
            'username' => $this->request->getPost('username'),
            'first_name' => $this->request->getPost('first_name'),
            'middle_name' => $this->request->getPost('middle_name'),
            'last_name' => $this->request->getPost('last_name'),
            'email' => $this->request->getPost('email'),
            'contact_number' => $this->request->getPost('contact_number'),
            'telegram_username' => $this->request->getPost('telegram_username'),
            'telegram_chat_id' => $this->request->getPost('telegram_chat_id'),
            'telegram_notification_enabled' => $this->request->getPost('telegram_notification_enabled') === '1' ? '1' : '0',
            'account_status' => $this->request->getPost('account_status'),
            'password' => $this->request->getPost('password'),
            'password_confirm' => $this->request->getPost('password_confirm'),
            'role_ids' => (array) $this->request->getPost('role_ids'),
            'section_ids' => (array) $this->request->getPost('section_ids'),
            'primary_section_id' => $this->request->getPost('primary_section_id'),
        ];
    }

    /** @param array<string, mixed> $input */
    private function valid(array $input, bool $creating): bool
    {
        $passwordRule = $creating ? 'required|min_length[12]|max_length[255]' : 'permit_empty|min_length[12]|max_length[255]';
        return $this->validateData($input, [
            'employee_id' => 'required|max_length[20]',
            'username' => 'required|alpha_numeric_punct|max_length[50]',
            'first_name' => 'required|max_length[100]',
            'middle_name' => 'permit_empty|max_length[100]',
            'last_name' => 'required|max_length[100]',
            'email' => 'required|valid_email|max_length[150]',
            'contact_number' => 'permit_empty|max_length[20]',
            'telegram_username' => 'permit_empty|max_length[100]',
            'telegram_chat_id' => 'permit_empty|regex_match[/^-?[0-9]{1,20}$/]|max_length[100]',
            'telegram_notification_enabled' => 'required|in_list[0,1]',
            'account_status' => 'required|in_list[Active,Inactive]',
            'password' => $passwordRule,
            'password_confirm' => $creating || ($input['password'] ?? '') !== '' ? 'required|matches[password]' : 'permit_empty',
        ]);
    }

    /** @param array<string, mixed>|null $user @return array<string, mixed> */
    private function formData(?array $user, bool $creating): array
    {
        $actorId = (string) session()->get('auth_user_id');
        return [
            'title' => $creating ? 'Add user' : 'Edit user',
            'user' => $user,
            'roles' => $this->users->roles($actorId),
            'sections' => $this->users->sections($actorId),
            'creating' => $creating,
        ];
    }

    private function safeMessage(Throwable $e): string
    {
        return $e instanceof RuntimeException ? $e->getMessage() : 'The user could not be saved. Please review the data and try again.';
    }

    /** @return array{ip:string,browser:string} */
    private function requestMeta(): array
    {
        return [
            'ip' => (string) $this->request->getIPAddress(),
            'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000),
        ];
    }
}
