<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class DocumentTypeManagementService extends BaseService
{
    /** @return array<int, array<string, mixed>> */
    public function list(string $search = '', string $status = ''): array
    {
        $builder = $this->db->table('document_types dt')
            ->select('dt.document_type_id, dt.type_code, dt.type_name, dt.prefix, dt.description, dt.active, dt.created_at')
            ->select('(SELECT COUNT(*) FROM documents d WHERE d.document_type_id = dt.document_type_id) AS document_count', false);
        if ($search !== '') {
            $builder->groupStart()->like('dt.type_code', $search)->orLike('dt.type_name', $search)->orLike('dt.prefix', $search)->orLike('dt.description', $search)->groupEnd();
        }
        if ($status !== '') {
            $builder->where('dt.active', $status === 'Active' ? 1 : 0);
        }
        return $builder->orderBy('dt.type_name')->get()->getResultArray();
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->db->table('document_types')->where('document_type_id', $id)->get()->getRowArray();
    }

    public function documentCount(string $id): int
    {
        return $this->db->table('documents')->where('document_type_id', $id)->countAllResults();
    }

    /** @param array<string, mixed> $input @param array{ip:string,browser:string} $meta */
    public function create(array $input, string $actorId, array $meta): string
    {
        $data = $this->data($input);
        $this->assertUnique($data['type_code'], $data['type_name']);
        $this->db->transBegin();
        try {
            if (! $this->db->table('document_types')->insert($data)) {
                throw new RuntimeException('Document type could not be created.');
            }
            $row = $this->db->table('document_types')->select('document_type_id')->where('type_code', $data['type_code'])->get()->getRowArray();
            if ($row === null) {
                throw new RuntimeException('Document type could not be retrieved after creation.');
            }
            $id = (string) $row['document_type_id'];
            $this->audit($actorId, 'CREATE', 'Created document type ' . $data['type_name'], null, ['document_type_id' => $id] + $data, $meta);
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
            throw new RuntimeException('Document type not found.');
        }
        $data = $this->data($input);
        $this->assertUnique($data['type_code'], $data['type_name'], $id);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            if (! $this->db->table('document_types')->where('document_type_id', $id)->update($data)) {
                throw new RuntimeException('Document type could not be updated.');
            }
            $this->audit($actorId, 'UPDATE', 'Updated document type ' . $data['type_name'], $old, ['document_type_id' => $id] + $data, $meta);
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
            throw new RuntimeException('Document type not found.');
        }
        $active = (int) $row['active'] === 1 ? 0 : 1;
        $this->db->transBegin();
        try {
            if (! $this->db->table('document_types')->where('document_type_id', $id)->update(['active' => $active, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new RuntimeException('Document type status could not be changed.');
            }
            $verb = $active ? 'Activated' : 'Deactivated';
            $this->audit($actorId, $active ? 'ACTIVATE' : 'DEACTIVATE', $verb . ' document type ' . $row['type_name'], ['active' => (int) $row['active']], ['active' => $active], $meta);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function data(array $input): array
    {
        return [
            'type_code' => strtoupper(trim((string) $input['type_code'])),
            'type_name' => trim((string) $input['type_name']),
            'prefix' => strtoupper(trim((string) $input['prefix'])),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'active' => (string) $input['active'] === '0' ? 0 : 1,
        ];
    }

    private function assertUnique(string $code, string $name, ?string $exceptId = null): void
    {
        foreach (['type_code' => $code, 'type_name' => $name] as $field => $value) {
            $builder = $this->db->table('document_types')->where($field, $value);
            if ($exceptId !== null) {
                $builder->where('document_type_id !=', $exceptId);
            }
            if ($builder->countAllResults() > 0) {
                throw new RuntimeException(($field === 'type_code' ? 'Document type code' : 'Document type name') . ' is already in use.');
            }
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new @param array{ip:string,browser:string} $meta */
    private function audit(string $actorId, string $action, string $description, ?array $old, ?array $new, array $meta): void
    {
        if (! $this->db->table('audit_logs')->insert([
            'user_id' => $actorId, 'document_id' => null, 'module_name' => 'Document Types', 'action_name' => $action,
            'description' => $description,
            'old_value' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES),
            'new_value' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES),
            'ip_address' => $meta['ip'] !== '' ? $meta['ip'] : null,
            'browser' => $meta['browser'] !== '' ? $meta['browser'] : null,
        ])) {
            throw new RuntimeException('The document type audit record could not be saved.');
        }
    }
}
