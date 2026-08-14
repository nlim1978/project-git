<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\ReportService;

class ReportsController extends BaseController
{
    private ReportService $reports;

    public function __construct()
    {
        $this->reports = new ReportService();
    }

    public function index(): string
    {
        $actorId = (string) session()->get('auth_user_id');
        $filters = $this->filters();
        $records = $this->reports->records($filters, $actorId);

        return view('reports/index', [
            'title' => 'Reports',
            'filters' => $filters,
            'records' => $records,
            'summary' => $this->reports->summary($records),
            'references' => $this->reports->references($actorId),
            'reportTypes' => $this->reports->reportTypes(),
            'canExport' => (new AuthorizationService())->hasPermission($actorId, 'Reports', 'Reports', 'EXPORT'),
            'queryString' => http_build_query(array_filter($filters, static fn (string $value): bool => $value !== '')),
        ]);
    }

    public function csv()
    {
        return $this->downloadDelimited('csv', 'text/csv; charset=UTF-8');
    }

    public function excel()
    {
        // Matches the approved prototype: spreadsheet-compatible CSV payload
        // with an Excel MIME type and .xls filename, requiring no extra package.
        return $this->downloadDelimited('xls', 'application/vnd.ms-excel; charset=UTF-8');
    }

    public function print(): string
    {
        $filters = $this->filters();
        $records = $this->reports->records($filters, (string) session()->get('auth_user_id'));

        return view('reports/print', [
            'title' => $filters['report_type'],
            'filters' => $filters,
            'records' => $records,
            'summary' => $this->reports->summary($records),
        ]);
    }

    private function downloadDelimited(string $extension, string $mime)
    {
        $filters = $this->filters();
        $content = $this->reports->csv($this->reports->records($filters, (string) session()->get('auth_user_id')));
        $filename = 'idoctrack-' . date('Ymd-His') . '.' . $extension;

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        return $this->reports->normalizeFilters([
            'report_type' => $this->request->getGet('report_type'),
            'from' => $this->request->getGet('from'),
            'to' => $this->request->getGet('to'),
            'section' => $this->request->getGet('section'),
            'user' => $this->request->getGet('user'),
            'status' => $this->request->getGet('status'),
            'type' => $this->request->getGet('type'),
            'action' => $this->request->getGet('action'),
        ]);
    }
}
