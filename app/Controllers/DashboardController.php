<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\DashboardService;
use App\Services\OrganizationScopeService;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $userId = (string) session()->get('auth_user_id');
        $authorization = new AuthorizationService();
        $dashboard = new DashboardService();
        $organizationScope = new OrganizationScopeService();
        $department = $dashboard->departmentForUser($userId);
        $sectionScope = $organizationScope->documentDataScope($userId)->sectionIds() ?? [];
        $period = $dashboard->period((string) $this->request->getGet('period'));

        $summary = ['needs_attention' => 0, 'received' => 0, 'in_progress' => 0, 'completed' => 0];
        $aging = ['fresh' => 0, 'watch' => 0, 'attention' => 0, 'critical' => 0];
        $topTypes = [];
        $attentionDocuments = [];

        if ($department !== null) {
            $departmentId = (string) $department['department_id'];
            $summary = $dashboard->departmentSummary($departmentId, $period, $sectionScope);
            $aging = $dashboard->agingBuckets($departmentId, $sectionScope);
            $topTypes = $dashboard->topDocumentTypes($departmentId, $period, 3, $sectionScope);
            $attentionDocuments = $dashboard->attentionDocuments($departmentId, 6, $sectionScope);
        }

        return view('dashboard/index', [
            'title' => 'Dashboard',
            'department' => $department,
            'isSectionScoped' => $sectionScope !== [],
            'period' => $period,
            'attentionDays' => DashboardService::ATTENTION_DAYS,
            'summary' => $summary,
            'aging' => $aging,
            'topTypes' => $topTypes,
            'attentionDocuments' => $attentionDocuments,
            'canManageUsers' => $authorization->hasPermission($userId, 'User Management', 'Users', 'VIEW'),
            'canViewReceiving' => $authorization->hasPermission($userId, 'Receiving', 'Receiving', 'VIEW'),
            'canCreateReceiving' => $authorization->hasPermission($userId, 'Receiving', 'Receiving', 'CREATE'),
            'canViewInbox' => $authorization->hasPermission($userId, 'General Inbox', 'Inbox', 'VIEW'),
            'canViewMonitoring' => $authorization->hasPermission($userId, 'Monitoring', 'Monitoring', 'VIEW'),
            'canViewReports' => $authorization->hasPermission($userId, 'Reports', 'Reports', 'VIEW'),
        ]);
    }
}
