<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AuditLogService;

class AuditLogController extends BaseController
{
    private AuditLogService $audit;

    public function __construct()
    {
        $this->audit = new AuditLogService();
    }

    public function index(): string
    {
        $actorId = (string) session()->get('auth_user_id');
        $filters = $this->filters();
        $result = $this->audit->page($filters, max(1, (int) $this->request->getGet('page')), $actorId);
        return view('admin/audit/index', [
            'title' => 'Audit Log', 'filters' => $filters, 'records' => $result['records'],
            'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages'],
            'perPage' => AuditLogService::PER_PAGE, 'references' => $this->audit->references($actorId),
            'queryString' => http_build_query(array_filter($filters, static fn (string $value): bool => $value !== '')),
        ]);
    }

    public function csv()
    {
        $filename = 'idoctrack-audit-log-' . date('Ymd-His') . '.csv';
        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($this->audit->csv($this->filters(), (string) session()->get('auth_user_id')));
    }

    /** @return array<string,string> */
    private function filters(): array
    {
        return $this->audit->normalizeFilters([
            'q' => $this->request->getGet('q'), 'user' => $this->request->getGet('user'),
            'module' => $this->request->getGet('module'), 'action' => $this->request->getGet('action'),
            'from' => $this->request->getGet('from'), 'to' => $this->request->getGet('to'),
        ]);
    }
}
