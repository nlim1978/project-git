<?php

namespace App\Controllers;

class HomeController extends BaseController
{
    public function index()
    {
        return session()->has('auth_user_id')
            ? redirect()->to(site_url('dashboard'))
            : redirect()->to(site_url('track'));
    }
}
