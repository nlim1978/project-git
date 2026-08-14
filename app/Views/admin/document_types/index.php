<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $canManage = ! empty(($navigation ?? [])['documentTypesManage']); ?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Document Types</h1>
        <p class="lead compact">Maintain document classifications and their configurable control-number prefixes.</p>
    </div>
    <?php if ($canManage): ?><a class="button button-primary" href="<?= site_url('admin/document-types/new') ?>">Create Document Type</a><?php endif ?>
</section>

<form class="panel admin-toolbar" method="get" action="<?= site_url('admin/document-types') ?>">
    <label class="field"><span>Search</span><input type="search" name="q" maxlength="100" value="<?= esc($search) ?>" placeholder="Code, name, prefix, or description"></label>
    <label class="field"><span>Status</span><select name="status"><option value="">All statuses</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
    <div class="admin-toolbar-actions"><a class="button" href="<?= site_url('admin/document-types') ?>">Reset</a><button class="button button-primary" type="submit">Apply</button></div>
</form>

<div class="table-card admin-table-card">
    <?php if ($documentTypes === []): ?><div class="empty-state"><strong>No document types match the selected filters.</strong></div>
    <?php else: ?><div class="table-scroll"><table class="document-type-table">
        <thead><tr><th>Code</th><th>Document Type Name</th><th>Prefix</th><th>Description</th><th>Documents</th><th>Status</th><th>Created</th><th class="actions-heading">Actions</th></tr></thead>
        <tbody><?php foreach ($documentTypes as $type): ?><tr>
            <td><span class="reference-code"><?= esc($type['type_code']) ?></span></td>
            <td><strong><?= esc($type['type_name']) ?></strong></td>
            <td><strong><?= esc($type['prefix']) ?></strong></td>
            <td class="description-cell"><?= $type['description'] ? esc($type['description']) : '<span class="muted">No description</span>' ?></td>
            <td><?= number_format((int) $type['document_count']) ?></td>
            <td><span class="badge <?= (int) $type['active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $type['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <td><?= esc(substr((string) $type['created_at'], 0, 10)) ?></td>
            <td class="row-actions"><a class="button button-small" href="<?= site_url('admin/document-types/' . $type['document_type_id']) ?>">View</a><?php if ($canManage): ?><a class="button button-small" href="<?= site_url('admin/document-types/' . $type['document_type_id'] . '/edit') ?>">Edit</a><form action="<?= site_url('admin/document-types/' . $type['document_type_id'] . '/status') ?>" method="post" onsubmit="return confirm('<?= (int) $type['active'] === 1 ? 'Deactivate' : 'Activate' ?> this document type?');"><?= csrf_field() ?><button class="button button-small" type="submit"><?= (int) $type['active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form><?php endif ?></td>
        </tr><?php endforeach ?></tbody>
    </table></div><?php endif ?>
    <div class="report-footer"><?= number_format(count($documentTypes)) ?> document type record<?= count($documentTypes) === 1 ? '' : 's' ?></div>
</div>
<?= $this->endSection() ?>
