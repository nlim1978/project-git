<?php

namespace App\Controllers;

use App\Services\DocumentArchiveService;

class ArchiveController extends BaseController
{
    public function index(): string
    {
        $service = new DocumentArchiveService();
        $actorId = (string) session()->get('auth_user_id');
        $filters = $service->normalizeFilters([
            'q' => $this->request->getGet('q'),
            'state' => $this->request->getGet('state'),
            'section' => $this->request->getGet('section'),
            'type' => $this->request->getGet('type'),
            'from' => $this->request->getGet('from'),
            'to' => $this->request->getGet('to'),
        ]);
        $documents = $service->search($filters, $actorId);

        return view('archive/index', [
            'title' => 'Document Archive',
            'filters' => $filters,
            'documents' => $documents,
            'references' => $service->references($actorId),
            'filedCount' => count(array_filter($documents, static fn (array $row): bool => $row['archive_state'] === 'Filed')),
            'releasedCount' => count(array_filter($documents, static fn (array $row): bool => $row['archive_state'] === 'Released')),
        ]);
    }
}
