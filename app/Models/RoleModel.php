<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['role_name', 'description', 'role_type', 'active'];
}
