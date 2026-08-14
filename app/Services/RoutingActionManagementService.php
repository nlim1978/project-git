<?php

namespace App\Services;

use App\Policies\SystemRole;
use RuntimeException;
use Throwable;

class RoutingActionManagementService extends BaseService
{
    /** @return array<int, array<string, mixed>> */
    public function list(string $search = '', string $status = '', string $resultingStatusId = ''): array
    {
        $builder = $this->db->table('routing_actions a')
            ->select('a.action_id, a.action_name, a.description, a.resulting_status_id, a.requires_remarks, a.active, a.created_at, ds.status_code, ds.status_name')
            ->join('document_statuses ds', 'ds.status_id = a.resulting_status_id');
        if ($search !== '') {
            $builder->groupStart()->like('a.action_name', $search)->orLike('a.description', $search)->groupEnd();
        }
        if ($status !== '') {
            $builder->where('a.active', $status === 'Active' ? 1 : 0);
        }
        if ($resultingStatusId !== '') {
            $builder->where('a.resulting_status_id', $resultingStatusId);
        }
        $actions = $builder->orderBy('a.action_name')->get()->getResultArray();
        foreach ($actions as &$action) {
            $action['roles'] = $this->roleNames((string) $action['action_id']);
            $action['history_count'] = $this->db->table('routing_history')->where('action_id', $action['action_id'])->countAllResults();
        }
        unset($action);
        return $actions;
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $action = $this->db->table('routing_actions')->where('action_id', $id)->get()->getRowArray();
        if ($action === null) {
            return null;
        }
        $action['role_ids'] = array_map('strval', array_column($this->db->table('routing_action_roles')->select('role_id')->where('action_id', $id)->get()->getResultArray(), 'role_id'));
        $action['history_count'] = $this->db->table('routing_history')->where('action_id', $id)->countAllResults();
        return $action;
    }

    /** @return array<int, array<string, mixed>> */
    public function statuses(): array
    {
        return $this->db->table('document_statuses')->select('status_id, status_code, status_name, is_terminal, active')
            ->where('active', 1)->orderBy('status_name')->get()->getResultArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function roles(): array
    {
        return $this->db->table('roles')->select('role_id, role_name, role_type, active')
            ->where('active', 1)->where('role_name !=', SystemRole::ADMINISTRATOR)->orderBy('role_name')->get()->getResultArray();
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function create(array $input, string $actorId, array $meta): string
    {
        $data = $this->data($input);
        $this->assertUniqueName($data['action_name']);
        $roleIds = $this->validRoleIds((array) ($input['role_ids'] ?? []));
        if ($roleIds === []) {
            throw new RuntimeException('Select at least one allowed role. Administrator access is already provided by system override.');
        }

        $this->db->transBegin();
        try {
            if (! $this->db->table('routing_actions')->insert($data)) {
                throw new RuntimeException('Routing action could not be created.');
            }
            $row = $this->db->table('routing_actions')->select('action_id')->where('action_name', $data['action_name'])->get()->getRowArray();
            if ($row === null) {
                throw new RuntimeException('Routing action could not be retrieved after creation.');
            }
            $id = (string) $row['action_id'];
            $this->syncRoles($id, $roleIds);
            $this->audit($actorId, 'CREATE', 'Created routing action ' . $data['action_name'], null, ['action_id' => $id, 'role_ids' => $roleIds] + $data, $meta);
            $this->db->transCommit();
            return $id;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function update(string $id, array $input, string $actorId, array $meta): void
    {
        $old = $this->find($id);
        if ($old === null) {
            throw new RuntimeException('Routing action not found.');
        }
        $data = $this->data($input);
        if ((int) $old['history_count'] > 0) {
            $data['action_name'] = (string) $old['action_name'];
        } else {
            $this->assertUniqueName($data['action_name'], $id);
        }
        $roleIds = $this->validRoleIds((array) ($input['role_ids'] ?? []));
        if ($roleIds === []) {
            throw new RuntimeException('Select at least one allowed role. Administrator access is already provided by system override.');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            if (! $this->db->table('routing_actions')->where('action_id', $id)->update($data)) {
                throw new RuntimeException('Routing action could not be updated.');
            }
            $this->syncRoles($id, $roleIds);
            $this->audit($actorId, 'UPDATE', 'Updated routing action ' . $data['action_name'], $old, ['action_id' => $id, 'role_ids' => $roleIds] + $data, $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array{ip:string,browser:string} $meta */
    public function toggleStatus(string $id, string $actorId, array $meta): void
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new RuntimeException('Routing action not found.');
        }
        $active = (int) $row['active'] === 1 ? 0 : 1;
        $this->db->transBegin();
        try {
            if (! $this->db->table('routing_actions')->where('action_id', $id)->update(['active' => $active, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new RuntimeException('Routing action status could not be changed.');
            }
            $verb = $active ? 'Activated' : 'Deactivated';
            $this->audit($actorId, $active ? 'ACTIVATE' : 'DEACTIVATE', $verb . ' routing action ' . $row['action_name'], ['active' => (int) $row['active']], ['active' => $active], $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function data(array $input): array
    {
        $statusId = trim((string) $input['resulting_status_id']);
        if ($this->db->table('document_statuses')->where('status_id', $statusId)->where('active', 1)->countAllResults() === 0) {
            throw new RuntimeException('Select an active resulting document status.');
        }
        return [
            'action_name' => trim((string) $input['action_name']),
            'description' => trim((string) $input['description']),
            'resulting_status_id' => $statusId,
            'requires_remarks' => ! empty($input['requires_remarks']) ? 1 : 0,
            'active' => (string) $input['active'] === '0' ? 0 : 1,
        ];
    }

    /** @param array<int, mixed> $requested @return array<int, string> */
    private function validRoleIds(array $requested): array
    {
        $requested = array_values(array_unique(array_filter(array_map('strval', $requested))));
        if ($requested === []) {
            return [];
        }
        $valid = array_map('strval', array_column($this->db->table('roles')->select('role_id')->where('active', 1)->where('role_name !=', SystemRole::ADMINISTRATOR)->whereIn('role_id', $requested)->get()->getResultArray(), 'role_id'));
        if (count($valid) !== count($requested)) {
            throw new RuntimeException('One or more selected roles are invalid or inactive.');
        }
        return $valid;
    }

    /** @param array<int, string> $roleIds */
    private function syncRoles(string $actionId, array $roleIds): void
    {
        $this->db->table('routing_action_roles')->where('action_id', $actionId)->delete();
        foreach ($roleIds as $roleId) {
            $this->db->table('routing_action_roles')->insert(['action_id' => $actionId, 'role_id' => $roleId]);
        }
    }

    /** @return array<int, string> */
    private function roleNames(string $actionId): array
    {
        $rows = $this->db->table('routing_action_roles ar')->select('r.role_name')->join('roles r', 'r.role_id = ar.role_id')
            ->where('ar.action_id', $actionId)->orderBy('r.role_name')->get()->getResultArray();
        $names = array_map('strval', array_column($rows, 'role_name'));
        array_unshift($names, 'Administrator (override)');
        return $names;
    }

    private function assertUniqueName(string $name, ?string $exceptId = null): void
    {
        $builder = $this->db->table('routing_actions')->where('action_name', $name);
        if ($exceptId !== null) {
            $builder->where('action_id !=', $exceptId);
        }
        if ($builder->countAllResults() > 0) {
            throw new RuntimeException('Action name is already in use.');
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new @param array{ip:string,browser:string} $meta */
    private function audit(string $actorId, string $action, string $description, ?array $old, ?array $new, array $meta): void
    {
        if (! $this->db->table('audit_logs')->insert([
            'user_id' => $actorId, 'document_id' => null, 'module_name' => 'Routing Actions', 'action_name' => $action,
            'description' => $description,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
            'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
        ])) {
            throw new RuntimeException('The routing action audit record could not be saved.');
        }
    }
}
