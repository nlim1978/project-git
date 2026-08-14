<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading">
    <div>
        <div class="eyebrow">REPORTING</div>
        <h1 class="page-title">Reports</h1>
        <p class="lead compact">Generate and export receiving, routing, status, user activity, section, document type, and action taken reports.</p>
    </div>
</section>

<form class="panel report-filters" method="get" action="<?= site_url('reports') ?>">
    <label class="field"><span>Report Type *</span><select name="report_type" required><?php foreach ($reportTypes as $reportType): ?><option value="<?= esc($reportType) ?>" <?= $filters['report_type'] === $reportType ? 'selected' : '' ?>><?= esc($reportType) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Date From</span><input type="date" name="from" value="<?= esc($filters['from']) ?>"></label>
    <label class="field"><span>Date To</span><input type="date" name="to" value="<?= esc($filters['to']) ?>"></label>
    <label class="field"><span>Section</span><select name="section"><option value="">All Sections</option><?php foreach ($references['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $filters['section'] === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code'] . ' — ' . $section['section_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>User</span><select name="user"><option value="">All Users</option><?php foreach ($references['users'] as $user): ?><option value="<?= esc($user['user_id']) ?>" <?= $filters['user'] === $user['user_id'] ? 'selected' : '' ?>><?= esc($user['last_name'] . ', ' . $user['first_name'] . ' · ' . $user['employee_id']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Status</span><select name="status"><option value="">All Statuses</option><?php foreach ($references['statuses'] as $status): ?><option value="<?= esc($status['status_id']) ?>" <?= $filters['status'] === $status['status_id'] ? 'selected' : '' ?>><?= esc($status['status_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Document Type</span><select name="type"><option value="">All Document Types</option><?php foreach ($references['types'] as $type): ?><option value="<?= esc($type['document_type_id']) ?>" <?= $filters['type'] === $type['document_type_id'] ? 'selected' : '' ?>><?= esc($type['type_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Action Taken</span><select name="action"><option value="">All Actions</option><option value="RECEIVED" <?= $filters['action'] === 'RECEIVED' ? 'selected' : '' ?>>Received / not yet routed</option><option value="ROUTED" <?= $filters['action'] === 'ROUTED' ? 'selected' : '' ?>>Routed / no action taken</option><?php foreach ($references['actions'] as $action): ?><option value="<?= esc($action['action_id']) ?>" <?= $filters['action'] === $action['action_id'] ? 'selected' : '' ?>><?= esc($action['action_name']) ?></option><?php endforeach ?></select></label>
    <div class="report-filter-actions"><a class="button" href="<?= site_url('reports') ?>">Reset</a><button class="button button-primary" type="submit">Generate Report</button></div>
</form>

<div class="report-summary">
    <article class="panel"><small>Total Records</small><strong><?= number_format($summary['total']) ?></strong></article>
    <article class="panel"><small>Received</small><strong><?= number_format($summary['received']) ?></strong></article>
    <article class="panel"><small>In Progress</small><strong><?= number_format($summary['in_progress']) ?></strong></article>
    <article class="panel"><small>Completed</small><strong><?= number_format($summary['completed']) ?></strong></article>
</div>

<section class="panel report-results">
    <div class="report-results-head">
        <div><h2><?= esc($filters['report_type']) ?></h2><p class="muted">Date range: <?= esc($filters['from'] ?: 'Any') ?> to <?= esc($filters['to'] ?: 'Any') ?></p></div>
        <?php if ($canExport): ?>
            <div class="heading-actions">
                <a class="button button-small" href="<?= site_url('reports/export/csv' . ($queryString ? '?' . $queryString : '')) ?>">Export CSV</a>
                <a class="button button-small" href="<?= site_url('reports/export/excel' . ($queryString ? '?' . $queryString : '')) ?>">Export Excel</a>
                <a class="button button-small" target="_blank" rel="noopener" href="<?= site_url('reports/print' . ($queryString ? '?' . $queryString : '')) ?>">Export PDF</a>
            </div>
        <?php endif ?>
    </div>

    <?php if ($records === []): ?>
        <div class="empty-state"><strong>No records match the selected report criteria.</strong></div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="report-table">
                <thead><tr><th>Document Number</th><th>Receiving Number</th><th>Date Received</th><th>Type</th><th>Subject / Sender</th><th>Current Section</th><th>Responsible</th><th>Status</th><th>Latest Action</th><th>Last Updated</th></tr></thead>
                <tbody><?php foreach ($records as $record): ?><tr>
                    <td><strong title="<?= esc($record['document_control_number'], 'attr') ?>"><?= esc(short_control_number($record['document_control_number'])) ?></strong></td>
                    <td><?= esc($record['receiving_number']) ?></td>
                    <td><?= esc($record['date_received']) ?></td>
                    <td><?= esc($record['type_name']) ?></td>
                    <td><strong><?= esc($record['subject']) ?></strong><small><?= esc($record['sender_name']) ?></small></td>
                    <td><?= esc($record['section_name']) ?></td>
                    <td><?= $record['responsible_first_name'] ? esc($record['responsible_first_name'] . ' ' . $record['responsible_last_name']) : 'Section inbox / unassigned' ?></td>
                    <td><span class="badge badge-info"><?= esc($record['status_name']) ?></span></td>
                    <td><?= esc($record['latest_action']) ?></td>
                    <td><?= esc($record['updated_at']) ?></td>
                </tr><?php endforeach ?></tbody>
            </table>
        </div>
    <?php endif ?>
    <div class="report-footer"><?= number_format(count($records)) ?> report record<?= count($records) === 1 ? '' : 's' ?> · maximum 5,000 rows per report</div>
</section>
<?= $this->endSection() ?>
