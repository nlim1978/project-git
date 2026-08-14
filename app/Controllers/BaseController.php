<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class BaseController extends Controller
{
    protected $request;

    protected $helpers = ['url'];

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);

        if ($request instanceof IncomingRequest && session()->has('auth_user_id')) {
            service('renderer')->setData([
                'navigation' => (new AuthorizationService())->navigationState((string) session()->get('auth_user_id')),
            ], 'raw');
        }
    }
}
