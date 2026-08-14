<?php

namespace App\Filters;

use App\Services\AuthenticationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthenticationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = (string) session()->get('auth_user_id');
        if ($userId !== '' && (new AuthenticationService())->isActiveUser($userId)) {
            return null;
        }

        session()->remove(['auth_user_id', 'auth_username', 'auth_name']);
        if (strtoupper($request->getMethod()) === 'GET') {
            $path = ltrim($request->getUri()->getPath(), '/');
            if ($path !== '' && $path !== 'login') {
                session()->set('auth_intended_path', $path);
            }
        }
        return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
