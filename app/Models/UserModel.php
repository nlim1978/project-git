<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'employee_id', 'username', 'password_hash', 'first_name', 'middle_name',
        'last_name', 'email', 'contact_number', 'account_status', 'last_login',
        'password_changed_at', 'telegram_chat_id', 'telegram_username',
        'telegram_notification_enabled', 'created_by', 'updated_by', 'updated_at',
    ];

    /** @param array<string, mixed> $data */
    public function insertRecord(array $data): bool
    {
        return $this->db->table($this->table)->insert($data);
    }
}
