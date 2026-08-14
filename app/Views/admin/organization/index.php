<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$nav = $navigation ?? [];
$plural = $type . 's';
$label = ucfirst($plural);
?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Organization Structure</h1>
        <p class="lead compact">Maintain offices, departments, sections, section heads, and user assignments.</p>
    </div>
    <?php if ($type !== 'office' || ! empty($isSuperAdmin)): ?><a class="button button-primary" href="<?= site_url('admin/organization/' . $plural . '/new') ?>">Create <?= esc(ucfirst($type)) ?></a><?php endif ?>
</section>

<nav class="entity-tabs" aria-label="Organization levels">
    <?php if (! empty($nav['organizationOffices'])): ?><a class="entity-tab <?= $type === 'office' ? 'active' : '' ?>" href="<?= site_url('admin/organization/offices') ?>">Offices</a><?php endif ?>
    <?php if (! empty($nav['organizationDepartments'])): ?><a class="entity-tab <?= $type === 'department' ? 'active' : '' ?>" href="<?= site_url('admin/organization/departments') ?>">Departments</a><?php endif ?>
    <?php if (! empty($nav['organizationSections'])): ?><a class="entity-tab <?= $type === 'section' ? 'active' : '' ?>" href="<?= site_url('admin/organization/sections') ?>">Sections</a><?php endif ?>
</nav>

<form class="panel admin-toolbar organization-toolbar" method="get" action="<?= site_url('admin/organization/' . $plural) ?>">
    <label class="field"><span>Search</span><input type="search" name="q" maxlength="100" value="<?= esc($search) ?>" placeholder="Search code or name"></label>
    <?php if ($type === 'department'): ?>
        <label class="field"><span>Office</span><select name="parent"><option value="">All offices</option><?php foreach ($offices as $office): ?><option value="<?= esc($office['office_id']) ?>" <?= $parent === $office['office_id'] ? 'selected' : '' ?>><?= esc($office['office_code'] . ' — ' . $office['office_name']) ?></option><?php endforeach ?></select></label>
    <?php elseif ($type === 'section'): ?>
        <label class="field"><span>Department</span><select name="parent"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= esc($department['department_id']) ?>" <?= $parent === $department['department_id'] ? 'selected' : '' ?>><?= esc($department['department_code'] . ' — ' . $department['department_name']) ?></option><?php endforeach ?></select></label>
    <?php else: ?><div></div><?php endif ?>
    <label class="field"><span>Status</span><select name="status"><option value="">All statuses</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
    <div class="admin-toolbar-actions"><a class="button" href="<?= site_url('admin/organization/' . $plural) ?>">Reset</a><button class="button button-primary" type="submit">Apply</button></div>
</form>

<div class="table-card admin-table-card">
    <?php if ($records === []): ?><div class="empty-state"><strong>No <?= esc(strtolower($label)) ?> match the selected filters.</strong></div>
    <?php else: ?>
    <div class="table-scroll"><table class="organization-table">
        <thead><tr>
            <th><?= esc(ucfirst($type)) ?> Code</th><th><?= esc(ucfirst($type)) ?> Name</th>
            <?php if ($type === 'office'): ?><th>Departments</th><?php endif ?>
            <?php if ($type === 'department'): ?><th>Office</th><th>Sections</th><?php endif ?>
            <?php if ($type === 'section'): ?><th>Department</th><th>Section Head</th><th>Assigned Users</th><?php endif ?>
            <th>Status</th><th class="actions-heading">Actions</th>
        </tr></thead>
        <tbody><?php foreach ($records as $record): ?>
            <?php $id = $record[$type . '_id']; ?>
            <tr>
                <td><strong><?= esc($record[$type . '_code']) ?></strong></td>
                <td><?= esc($record[$type . '_name']) ?></td>
                <?php if ($type === 'office'): ?><td><?= number_format((int) $record['department_count']) ?></td><?php endif ?>
                <?php if ($type === 'department'): ?><td><strong><?= esc($record['office_code']) ?></strong><small><?= esc($record['office_name']) ?></small></td><td><?= number_format((int) $record['section_count']) ?></td><?php endif ?>
                <?php if ($type === 'section'): ?><td><strong><?= esc($record['department_code']) ?></strong><small><?= esc($record['department_name']) ?> · <?= esc($record['office_name']) ?></small></td><td><?= $record['head_name'] !== '' ? esc($record['head_name']) : '<span class="muted">Not assigned</span>' ?></td><td><?= number_format((int) $record['user_count']) ?></td><?php endif ?>
                <td><span class="badge <?= (int) $record['active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $record['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                <td class="row-actions">
                    <a class="button button-small" href="<?= site_url('admin/organization/' . $plural . '/' . $id) ?>">View</a>
                    <a class="button button-small" href="<?= site_url('admin/organization/' . $plural . '/' . $id . '/edit') ?>">Edit</a>
                    <?php if ($type === 'section'): ?><a class="button button-small" href="<?= site_url('admin/organization/sections/' . $id . '/assignments') ?>">Assign Users</a><?php endif ?>
                    <form action="<?= site_url('admin/organization/' . $plural . '/' . $id . '/status') ?>" method="post" onsubmit="return confirm('<?= (int) $record['active'] === 1 ? 'Deactivate' : 'Activate' ?> this <?= esc($type) ?>?');"><?= csrf_field() ?><button class="button button-small" type="submit"><?= (int) $record['active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form>
                </td>
            </tr>
        <?php endforeach ?></tbody>
    </table></div>
    <?php endif ?>
    <div class="report-footer"><?= number_format(count($records)) ?> <?= esc(strtolower($type)) ?> record<?= count($records) === 1 ? '' : 's' ?></div>
</div>
<?= $this->endSection() ?>
