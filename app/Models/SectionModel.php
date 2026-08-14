<?php

namespace App\Models;

use CodeIgniter\Model;

class SectionModel extends Model
{
    protected $table = 'sections';
    protected $primaryKey = 'section_id';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['department_id', 'section_code', 'section_name', 'head_user_id', 'active'];
}
