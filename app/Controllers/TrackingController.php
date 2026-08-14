<?php

namespace App\Controllers;

use App\Services\ClientTrackingService;
use CodeIgniter\Config\Services;

class TrackingController extends BaseController
{
    private ClientTrackingService $tracking;

    public function __construct()
    {
        $this->tracking = new ClientTrackingService();
    }

    public function index(): string
    {
        return view('tracking/index', ['title' => 'Track a Document']);
    }

    public function status()
    {
        $rawToken = (string) $this->request->getPost('tracking_token');
        $lookupKey = ClientTrackingService::normalizeToken($rawToken)
            ?? ClientTrackingService::normalizeReference($rawToken)
            ?? '';
        $requestedRefresh = (string) $this->request->getPost('refresh') === '1';
        $storedRefreshKey = (string) session()->get('client_tracking_refresh_key');
        $isRefresh = $requestedRefresh
            && $lookupKey !== ''
            && $storedRefreshKey !== ''
            && hash_equals($storedRefreshKey, hash('sha256', $lookupKey));

        if (! $this->allowRequest($isRefresh)) {
            return $this->response->setStatusCode(429)->setJSON([
                'error' => 'Too many tracking requests. Please wait a moment and try again.',
                'csrf' => csrf_hash(),
            ]);
        }
        if (! $this->tracking->available()) {
            return $this->response->setStatusCode(503)->setJSON([
                'error' => 'Client tracking is being prepared. Please try again shortly.',
                'csrf' => csrf_hash(),
            ]);
        }

        $result = $this->tracking->status($rawToken);
        if (! $isRefresh) {
            $documentId = null;
            if ($result !== null) {
                $documentId = $this->tracking->documentIdForInput($rawToken);
            }
            $this->tracking->auditLookup($documentId, $result !== null, (string) $this->request->getIPAddress(), mb_substr((string) $this->request->getUserAgent(), 0, 1000));
        }

        if ($result === null) {
            if (! $isRefresh) {
                session()->remove('client_tracking_refresh_key');
            }
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Tracking reference not found. Check the code and try again.',
                'csrf' => csrf_hash(),
            ]);
        }

        if (! $isRefresh && $lookupKey !== '') {
            session()->set('client_tracking_refresh_key', hash('sha256', $lookupKey));
        }

        return $this->response->setHeader('Cache-Control', 'no-store, private')->setJSON([
            'document' => $result,
            'csrf' => csrf_hash(),
        ]);
    }

    private function allowRequest(bool $isRefresh): bool
    {
        $bucket = $isRefresh ? 'refresh' : 'lookup';
        $limit = $isRefresh ? 30 : 10;
        $key = 'client-tracking-' . $bucket . '-' . sha1((string) $this->request->getIPAddress());
        return Services::throttler()->check($key, $limit, MINUTE);
    }
}
