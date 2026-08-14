<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AuthorizationService;
use App\Services\OrganizationManagementService;
use RuntimeException;
use Throwable;

class OrganizationController extends BaseController
{
    private OrganizationManagementService $organization;

    public function __construct()
    {
        $this->organization = new OrganizationManagementService();
    }

    public function landing()
    {
        $auth = new AuthorizationService();
        $userId = $this->actorId();
        foreach ([['Offices', 'offices'], ['Departments', 'departments'], ['Sections', 'sections']] as [$page, $path]) {
            if ($auth->hasPermission($userId, 'Organization', $page, 'MANAGE')) {
                return redirect()->to(site_url('admin/organization/' . $path));
            }
        }
        return service('response')->setStatusCode(403)->setBody(view('errors/html/error_403'));
    }

    public function offices(): string
    {
        [$search, $status] = $this->filters();
        $actorId = $this->actorId();
        return view('admin/organization/index', $this->indexData('office', $this->organization->offices($actorId, $search, $status), $search, $status, '', $actorId));
    }

    public function departments(): string
    {
        [$search, $status] = $this->filters();
        $parent = mb_substr(trim((string) $this->request->getGet('parent')), 0, 50);
        $actorId = $this->actorId();
        return view('admin/organization/index', $this->indexData('department', $this->organization->departments($actorId, $search, $status, $parent), $search, $status, $parent, $actorId));
    }

    public function sections(): string
    {
        [$search, $status] = $this->filters();
        $parent = mb_substr(trim((string) $this->request->getGet('parent')), 0, 50);
        $actorId = $this->actorId();
        return view('admin/organization/index', $this->indexData('section', $this->organization->sections($actorId, $search, $status, $parent), $search, $status, $parent, $actorId));
    }

    public function newOffice()
    {
        if (! $this->organization->isSuperAdmin($this->actorId())) {
            return service('response')->setStatusCode(403)->setBody(view('errors/html/error_403'));
        }
        return view('admin/organization/form', $this->formData('office', null, true));
    }
    public function newDepartment(): string { return view('admin/organization/form', $this->formData('department', null, true)); }
    public function newSection(): string { return view('admin/organization/form', $this->formData('section', null, true)); }

    public function editOffice(string $id) { return $this->edit('office', $id); }
    public function editDepartment(string $id) { return $this->edit('department', $id); }
    public function editSection(string $id) { return $this->edit('section', $id); }

    public function showOffice(string $id) { return $this->show('office', $id); }
    public function showDepartment(string $id) { return $this->show('department', $id); }
    public function showSection(string $id) { return $this->show('section', $id); }

    public function createOffice() { return $this->create('office'); }
    public function createDepartment() { return $this->create('department'); }
    public function createSection() { return $this->create('section'); }

    public function updateOffice(string $id) { return $this->update('office', $id); }
    public function updateDepartment(string $id) { return $this->update('department', $id); }
    public function updateSection(string $id) { return $this->update('section', $id); }

    public function officeStatus(string $id) { return $this->status('office', $id); }
    public function departmentStatus(string $id) { return $this->status('department', $id); }
    public function sectionStatus(string $id) { return $this->status('section', $id); }

    public function assignments(string $id)
    {
        if (! $this->organization->canAccess('section', $id, $this->actorId())) {
            return $this->notFound();
        }
        $section = $this->organization->find('section', $id);
        if ($section === null) {
            return $this->notFound();
        }
        return view('admin/organization/assignments', [
            'title' => 'Section User Assignments',
            'section' => $section,
            'users' => $this->organization->userOptions($this->actorId()),
            'selected' => $this->organization->sectionUserIds($id),
        ]);
    }

    public function saveAssignments(string $id)
    {
        try {
            $this->organization->saveSectionAssignments($id, (array) $this->request->getPost('user_ids'), $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to(site_url('admin/organization/sections'))->with('success', 'Section user assignments updated.');
    }

    private function show(string $type, string $id)
    {
        if (! $this->organization->canAccess($type, $id, $this->actorId())) {
            return $this->notFound();
        }
        $record = $this->organization->detail($type, $id);
        if ($record === null) {
            return $this->notFound();
        }
        return view('admin/organization/show', [
            'title' => $record[$type . '_name'],
            'type' => $type,
            'record' => $record,
        ]);
    }

    private function edit(string $type, string $id)
    {
        if (! $this->organization->canAccess($type, $id, $this->actorId())) {
            return $this->notFound();
        }
        $record = $this->organization->find($type, $id);
        return $record === null ? $this->notFound() : view('admin/organization/form', $this->formData($type, $record, false));
    }

    private function create(string $type)
    {
        $input = $this->input($type);
        if (! $this->valid($type, $input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->organization->create($type, $input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to($this->listUrl($type))->with('success', ucfirst($type) . ' created.');
    }

    private function update(string $type, string $id)
    {
        $input = $this->input($type);
        if (! $this->valid($type, $input)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $this->organization->update($type, $id, $input, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeMessage($e));
        }
        return redirect()->to($this->listUrl($type) . '/' . $id)->with('success', ucfirst($type) . ' updated.');
    }

    private function status(string $type, string $id)
    {
        try {
            $this->organization->toggleStatus($type, $id, $this->actorId(), $this->requestMeta());
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $this->safeMessage($e));
        }
        return redirect()->to($this->listUrl($type))->with('success', ucfirst($type) . ' status updated.');
    }

    /** @return array<string, mixed> */
    private function indexData(string $type, array $records, string $search, string $status, string $parent, string $actorId): array
    {
        return [
            'title' => 'Organization Structure', 'type' => $type, 'records' => $records,
            'search' => $search, 'status' => $status, 'parent' => $parent,
            'offices' => $this->organization->officeOptions($actorId),
            'departments' => $this->organization->departmentOptions($actorId),
            'isSuperAdmin' => $this->organization->isSuperAdmin($actorId),
        ];
    }

    /** @param array<string, mixed>|null $record @return array<string, mixed> */
    private function formData(string $type, ?array $record, bool $creating): array
    {
        return [
            'title' => ($creating ? 'Create ' : 'Edit ') . ucfirst($type), 'type' => $type,
            'record' => $record, 'creating' => $creating,
            'offices' => $this->organization->officeOptions($this->actorId()),
            'departments' => $this->organization->departmentOptions($this->actorId()),
            'users' => $this->organization->userOptions($this->actorId()),
        ];
    }

    /** @return array<string, mixed> */
    private function input(string $type): array
    {
        $input = [
            'code' => $this->request->getPost('code'), 'name' => $this->request->getPost('name'),
            'active' => $this->request->getPost('active'),
        ];
        if ($type !== 'office') {
            $input['parent_id'] = $this->request->getPost('parent_id');
        }
        if ($type === 'section') {
            $input['head_user_id'] = $this->request->getPost('head_user_id');
        }
        return $input;
    }

    /** @param array<string, mixed> $input */
    private function valid(string $type, array $input): bool
    {
        $rules = ['code' => 'required|max_length[20]', 'name' => 'required|max_length[150]', 'active' => 'required|in_list[0,1]'];
        if ($type !== 'office') {
            $rules['parent_id'] = 'required|max_length[50]';
        }
        if ($type === 'section') {
            $rules['head_user_id'] = 'permit_empty|max_length[50]';
        }
        return $this->validateData($input, $rules);
    }

    /** @return array{string,string} */
    private function filters(): array
    {
        $search = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $status = (string) $this->request->getGet('status');
        return [$search, in_array($status, ['Active', 'Inactive'], true) ? $status : ''];
    }

    private function listUrl(string $type): string { return site_url('admin/organization/' . $type . 's'); }
    private function actorId(): string { return (string) session()->get('auth_user_id'); }
    private function requestMeta(): array { return ['ip' => (string) $this->request->getIPAddress(), 'browser' => mb_substr((string) $this->request->getUserAgent(), 0, 1000)]; }
    private function safeMessage(Throwable $e): string { return $e instanceof RuntimeException ? $e->getMessage() : 'The organization record could not be saved. Please review the data and try again.'; }
    private function notFound() { return service('response')->setStatusCode(404)->setBody(view('errors/html/error_404')); }
}
