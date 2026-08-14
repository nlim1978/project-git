<?php

namespace App\Controllers;

use App\Services\SystemHealthService;
use CodeIgniter\HTTP\ResponseInterface;

class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        $result = (new SystemHealthService())->check();

        return $this->response
            ->setStatusCode($result['ok'] ? 200 : 503)
            ->setJSON($result);
    }
}
