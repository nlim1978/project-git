<?php

namespace App\Controllers;

use App\Services\DocumentMonitoringService;

class MonitoringController extends BaseController
{
    public function index(): string
    {
        $service = new DocumentMonitoringService();
        $actorId = (string) session()->get('auth_user_id');
        $filters = $service->normalizeFilters([
            'q' => $this->request->getGet('q'),
            'section' => $this->request->getGet('section'),
            'person' => $this->request->getGet('person'),
            'status' => $this->request->getGet('status'),
            'type' => $this->request->getGet('type'),
            'from' => $this->request->getGet('from'),
            'to' => $this->request->getGet('to'),
        ]);

        return view('monitoring/index', [
            'title' => 'Document Monitoring',
            'filters' => $filters,
            'documents' => $service->search($filters, $actorId),
            'references' => $service->references($actorId),
        ]);
    }
}
