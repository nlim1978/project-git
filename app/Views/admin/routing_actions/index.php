<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $canManage = ! empty(($navigation ?? [])['actionsManage']); ?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Routing Actions</h1>
        <p class="lead compact">Configure the actions users may select while routing a document.</p>
    </div>
    <?php if ($canManage): ?><a class="button button-primary" href="<?= site_url('admin/routing-actions/new') ?>">Create Action</a><?php endif ?>
</section>

<form class="panel admin-toolbar routing-action-toolbar" method="get" action="<?= site_url('admin/routing-actions') ?>">
    <label class="field"><span>Search</span><input type="search" name="q" maxlength="100" value="<?= esc($search) ?>" placeholder="Action name or description"></label>
    <label class="field"><span>Resulting Status</span><select name="resulting_status_id"><option value="">All resulting statuses</option><?php foreach ($statuses as $result): ?><option value="<?= esc($result['status_id']) ?>" <?= $resultingStatusId === $result['status_id'] ? 'selected' : '' ?>><?= esc($result['status_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Action Status</span><select name="status"><option value="">All statuses</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
    <div class="admin-toolbar-actions"><a class="button" href="<?= site_url('admin/routing-actions') ?>">Reset</a><button class="button button-primary" type="submit">Apply</button></div>
</form>

<div class="table-card admin-table-card">
    <?php if ($actions === []): ?><div class="empty-state"><strong>No routing actions match the selected filters.</strong></div>
    <?php else: ?><div class="table-scroll"><table class="routing-action-table">
        <thead><tr><th>Action Name</th><th>Description</th><th>Resulting Status</th><th>Allowed Roles</th><th>Remarks</th><th>Used</th><th>Status</th><th class="actions-heading">Actions</th></tr></thead>
        <tbody><?php foreach ($actions as $action): ?><tr>
            <td><strong><?= esc($action['action_name']) ?></strong></td>
            <td class="description-cell"><?= esc($action['description']) ?></td>
            <td><span class="badge badge-info"><?= esc($action['status_name']) ?></span></td>
            <td><div class="chip-row"><?php foreach ($action['roles'] as $role): ?><span class="role-chip"><?= esc($role) ?></span><?php endforeach ?></div></td>
            <td><span class="badge <?= (int) $action['requires_remarks'] === 1 ? 'badge-warning' : 'badge-muted' ?>"><?= (int) $action['requires_remarks'] === 1 ? 'Required' : 'Optional' ?></span></td>
            <td><?= number_format((int) $action['history_count']) ?> route<?= (int) $action['history_count'] === 1 ? '' : 's' ?></td>
            <td><span class="badge <?= (int) $action['active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $action['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <td class="row-actions"><a class="button button-small" href="<?= site_url('admin/routing-actions/' . $action['action_id']) ?>">View</a><?php if ($canManage): ?><a class="button button-small" href="<?= site_url('admin/routing-actions/' . $action['action_id'] . '/edit') ?>">Edit</a><form action="<?= site_url('admin/routing-actions/' . $action['action_id'] . '/status') ?>" method="post" onsubmit="return confirm('<?= (int) $action['active'] === 1 ? 'Deactivate' : 'Activate' ?> this routing action?');"><?= csrf_field() ?><button class="button button-small" type="submit"><?= (int) $action['active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form><?php endif ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div><?php endif ?>
    <div class="report-footer"><?= number_format(count($actions)) ?> routing action record<?= count($actions) === 1 ? '' : 's' ?></div>
</div>
<?= $this->endSection() ?>
