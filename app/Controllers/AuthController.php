<?php

namespace App\Controllers;

use App\Services\AuthenticationService;

class AuthController extends BaseController
{
    public function login(): string
    {
        return view('auth/login', ['title' => 'Sign in']);
    }

    public function attempt()
    {
        $data = [
            'username' => (string) $this->request->getPost('username'),
            'password' => (string) $this->request->getPost('password'),
        ];

        if (! $this->validateData($data, [
            'username' => 'required|max_length[50]',
            'password' => 'required|max_length[255]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Cache keys cannot contain reserved characters such as the colons in IPv6 (::1).
        // Hashing keeps the throttle scoped to the client IP while producing a safe key.
        $ipKey = hash('sha256', (string) $this->request->getIPAddress());
        if (! service('throttler')->check('idoctrack-login-' . $ipKey, 5, MINUTE)) {
            return redirect()->back()->withInput()->with('error', 'Too many sign-in attempts. Please wait one minute.');
        }

        if (! (new AuthenticationService())->attempt($data['username'], $data['password'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid username or password.');
        }

        $intendedPath = trim((string) session()->get('auth_intended_path'), '/');
        session()->remove('auth_intended_path');
        if ($intendedPath !== '' && ! str_starts_with($intendedPath, 'login')) {
            return redirect()->to(site_url($intendedPath))->with('success', 'Welcome back.');
        }

        return redirect()->to(site_url('dashboard'))->with('success', 'Welcome back.');
    }

    public function logout()
    {
        (new AuthenticationService())->logout();
        return redirect()->to(site_url('track'))->with('success', 'You have been signed out.');
    }
}
