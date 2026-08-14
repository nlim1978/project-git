<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

abstract class BaseService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }
}
