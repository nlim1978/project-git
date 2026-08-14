<?php

namespace App\Controllers;

use App\Services\DocumentRoutingService;

class GeneralInboxController extends BaseController
{
    public function index(): string
    {
        return view('general_inbox/index', [
            'title' => 'General Inbox',
            'documents' => (new DocumentRoutingService())->inbox((string) session()->get('auth_user_id')),
            'eventCursor' => time(),
        ]);
    }

    public function events()
    {
        $since = (int) ($this->request->getGet('since') ?? 0);
        $now = time();
        // Keep this endpoint a live-feed API, not an unbounded history export.
        if ($since <= 0 || $since > $now + 30 || $since < $now - 300) {
            $since = $now;
        }

        $events = (new DocumentRoutingService())->inboxEvents(
            (string) session()->get('auth_user_id'),
            $since
        );

        return $this->response
            ->setHeader('Cache-Control', 'no-store, private')
            ->setJSON(['events' => $events, 'cursor' => $now]);
    }
}
