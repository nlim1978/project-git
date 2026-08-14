<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$userName = static function (array $record): string {
    $name = trim((string) (($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')));
    return $name !== '' ? $name : (($record['username'] ?? '') !== '' ? (string) $record['username'] : 'System');
};
$prettyValue = static function (?string $value): string {
    if ($value === null || trim($value) === '') return '—';
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $pretty !== false ? $pretty : $value;
    }
    return $value;
};
$pageUrl = static function (int $target) use ($filters): string {
    $query = array_filter($filters, static fn (string $value): bool => $value !== '');
    $query['page'] = $target;
    return site_url('admin/audit') . '?' . http_build_query($query);
};
?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Audit Log</h1>
        <p class="lead compact">Read-only history of user activity, administrative changes, and system configuration events.</p>
    </div>
    <a class="button" href="<?= site_url('admin/audit/export/csv' . ($queryString ? '?' . $queryString : '')) ?>">Export Filtered CSV</a>
</section>

<div class="alert alert-info"><strong>Read-only audit trail:</strong> records cannot be edited or deleted. Date/time values shown here are stored in UTC.</div>

<form class="panel audit-toolbar" method="get" action="<?= site_url('admin/audit') ?>">
    <label class="field audit-search"><span>Search</span><input type="search" name="q" maxlength="100" value="<?= esc($filters['q']) ?>" placeholder="Description, user, IP address, or browser"></label>
    <label class="field"><span>User</span><select name="user"><option value="">All users</option><option value="__system__" <?= $filters['user'] === '__system__' ? 'selected' : '' ?>>System / automated</option><?php foreach ($references['users'] as $user): ?><?php $label = trim((string) ($user['first_name'] . ' ' . $user['last_name'])); ?><option value="<?= esc($user['user_id']) ?>" <?= $filters['user'] === $user['user_id'] ? 'selected' : '' ?>><?= esc($label !== '' ? $label : $user['username']) ?><?= $user['employee_id'] ? ' · ' . esc($user['employee_id']) : '' ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Module</span><select name="module"><option value="">All modules</option><?php foreach ($references['modules'] as $item): ?><option value="<?= esc($item['module_name']) ?>" <?= $filters['module'] === $item['module_name'] ? 'selected' : '' ?>><?= esc($item['module_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Action</span><select name="action"><option value="">All actions</option><?php foreach ($references['actions'] as $item): ?><option value="<?= esc($item['action_name']) ?>" <?= $filters['action'] === $item['action_name'] ? 'selected' : '' ?>><?= esc($item['action_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Date From</span><input type="date" name="from" value="<?= esc($filters['from']) ?>"></label>
    <label class="field"><span>Date To</span><input type="date" name="to" value="<?= esc($filters['to']) ?>"></label>
    <div class="admin-toolbar-actions audit-toolbar-actions"><a class="button" href="<?= site_url('admin/audit') ?>">Reset</a><button class="button button-primary" type="submit">Apply Filters</button></div>
</form>

<div class="table-card admin-table-card">
    <?php if ($records === []): ?><div class="empty-state"><strong>No audit records match the selected filters.</strong></div>
    <?php else: ?><div class="table-scroll"><table class="audit-table">
        <thead><tr><th>Date / Time</th><th>User</th><th>Module</th><th>Action</th><th>Description</th><th>IP Address</th><th>Details</th></tr></thead>
        <tbody><?php foreach ($records as $i => $record): ?><tr>
            <td class="audit-date"><strong><?= esc(substr((string) $record['occurred_at'], 0, 10)) ?></strong><small><?= esc(substr((string) $record['occurred_at'], 11, 8)) ?> UTC</small></td>
            <td><strong><?= esc($userName($record)) ?></strong><?php if ($record['username']): ?><small><?= esc('@' . $record['username']) ?></small><?php endif ?></td>
            <td><?= esc($record['module_name']) ?></td>
            <td><span class="badge badge-info"><?= esc($record['action_name']) ?></span></td>
            <td class="description-cell"><?= esc($record['description']) ?></td>
            <td><?= $record['ip_address'] ? esc($record['ip_address']) : '<span class="muted">—</span>' ?></td>
            <td><button class="button button-small" type="button" data-audit-open="auditDetail<?= $i ?>">View</button></td>
        </tr><?php endforeach ?></tbody>
    </table></div><?php endif ?>
    <div class="report-footer audit-footer">
        <span><?= number_format($total) ?> audit record<?= $total === 1 ? '' : 's' ?><?php if ($total > 0): ?> · showing <?= number_format((($page - 1) * $perPage) + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?><?php endif ?></span>
        <?php if ($pages > 1): ?><nav class="audit-pagination" aria-label="Audit log pages">
            <?php if ($page > 1): ?><a class="button button-small" href="<?= esc($pageUrl($page - 1)) ?>">Previous</a><?php endif ?>
            <span>Page <?= number_format($page) ?> of <?= number_format($pages) ?></span>
            <?php if ($page < $pages): ?><a class="button button-small" href="<?= esc($pageUrl($page + 1)) ?>">Next</a><?php endif ?>
        </nav><?php endif ?>
    </div>
</div>

<?php foreach ($records as $i => $record): ?><dialog id="auditDetail<?= $i ?>" class="audit-dialog">
    <div class="audit-dialog-head"><div><span class="eyebrow">AUDIT RECORD DETAILS</span><h2><?= esc($record['module_name']) ?> · <?= esc($record['action_name']) ?></h2></div><button class="icon-button" type="button" data-audit-close aria-label="Close audit details">×</button></div>
    <div class="audit-detail-grid">
        <div><span>Audit ID</span><strong class="mono-value"><?= esc($record['audit_id']) ?></strong></div>
        <div><span>Date / Time</span><strong><?= esc((string) $record['occurred_at']) ?> UTC</strong></div>
        <div><span>User</span><strong><?= esc($userName($record)) ?></strong></div>
        <div><span>Document ID</span><strong class="mono-value"><?= $record['document_id'] ? esc($record['document_id']) : '—' ?></strong></div>
        <div><span>IP Address</span><strong><?= $record['ip_address'] ? esc($record['ip_address']) : '—' ?></strong></div>
        <div><span>Browser</span><strong><?= $record['browser'] ? esc($record['browser']) : '—' ?></strong></div>
        <div class="audit-detail-wide"><span>Description</span><p><?= esc($record['description']) ?></p></div>
        <div class="audit-detail-wide"><span>Old Value</span><pre><?= esc($prettyValue($record['old_value'])) ?></pre></div>
        <div class="audit-detail-wide"><span>New Value</span><pre><?= esc($prettyValue($record['new_value'])) ?></pre></div>
    </div>
    <div class="audit-dialog-foot"><button class="button" type="button" data-audit-close>Close</button></div>
</dialog><?php endforeach ?>

<script>
(function () {
    document.querySelectorAll('[data-audit-open]').forEach(button => button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.auditOpen);
        if (dialog) dialog.showModal();
    }));
    document.querySelectorAll('.audit-dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-audit-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    });
}());
</script>
<?= $this->endSection() ?>
