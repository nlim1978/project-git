<?php

namespace App\Filters;

use App\Services\AuthorizationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $arguments ??= [];
        if (count($arguments) !== 3) {
            return service('response')->setStatusCode(403)->setBody(view('errors/html/error_403'));
        }

        $userId = (string) session()->get('auth_user_id');
        if ($userId === '' || ! (new AuthorizationService())->hasPermission($userId, $arguments[0], $arguments[1], $arguments[2])) {
            return service('response')->setStatusCode(403)->setBody(view('errors/html/error_403'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
