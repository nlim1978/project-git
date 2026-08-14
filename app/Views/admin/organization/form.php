<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$plural = $type . 's';
$codeField = $type . '_code';
$nameField = $type . '_name';
$code = (string) old('code', $record[$codeField] ?? '', false);
$name = (string) old('name', $record[$nameField] ?? '', false);
$active = (string) old('active', isset($record['active']) ? (string) (int) $record['active'] : '1', false);
$parentId = (string) old('parent_id', $type === 'department' ? ($record['office_id'] ?? '') : ($record['department_id'] ?? ''), false);
$headId = (string) old('head_user_id', $record['head_user_id'] ?? '', false);
?>
<section class="page-heading">
    <div class="eyebrow">ORGANIZATION STRUCTURE</div>
    <h1 class="page-title"><?= $creating ? 'Create' : 'Edit' ?> <?= esc(ucfirst($type)) ?></h1>
    <p class="lead compact">Keep codes concise and use the official organizational name.</p>
</section>
<?php if ($errors): ?><div class="alert alert-error"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>

<form class="panel form-section organization-form" action="<?= $creating ? site_url('admin/organization/' . $plural) : site_url('admin/organization/' . $plural . '/' . $record[$type . '_id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="detail-header"><h2><?= esc(ucfirst($type)) ?> Profile</h2><span class="muted"><?= $creating ? 'New record' : 'Existing record' ?></span></div>
    <div class="form-grid">
        <label class="field"><span><?= esc(ucfirst($type)) ?> Code *</span><input name="code" maxlength="20" value="<?= esc($code) ?>" autocomplete="off" required><small class="field-help">Maximum 20 characters. Saved in uppercase.</small></label>
        <label class="field"><span>Status *</span><select name="active" required><option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option></select></label>
        <label class="field field-wide"><span><?= esc(ucfirst($type)) ?> Name *</span><input name="name" maxlength="150" value="<?= esc($name) ?>" required></label>
        <?php if ($type === 'department'): ?>
            <label class="field field-wide"><span>Office *</span><select name="parent_id" required><option value="">Select active office</option><?php foreach ($offices as $office): ?><?php if ((int) $office['active'] === 1 || $parentId === $office['office_id']): ?><option value="<?= esc($office['office_id']) ?>" <?= $parentId === $office['office_id'] ? 'selected' : '' ?>><?= esc($office['office_code'] . ' — ' . $office['office_name']) ?><?= (int) $office['active'] === 0 ? ' (Inactive)' : '' ?></option><?php endif ?><?php endforeach ?></select></label>
        <?php elseif ($type === 'section'): ?>
            <label class="field"><span>Department *</span><select name="parent_id" required><option value="">Select active department</option><?php foreach ($departments as $department): ?><?php if (((int) $department['active'] === 1 && (int) $department['office_active'] === 1) || $parentId === $department['department_id']): ?><option value="<?= esc($department['department_id']) ?>" <?= $parentId === $department['department_id'] ? 'selected' : '' ?>><?= esc($department['department_code'] . ' — ' . $department['department_name']) ?><?= (int) $department['active'] === 0 ? ' (Inactive)' : '' ?></option><?php endif ?><?php endforeach ?></select></label>
            <label class="field"><span>Section Head</span><select name="head_user_id"><option value="">Not assigned</option><?php foreach ($users as $user): ?><?php if ($user['account_status'] === 'Active' || $headId === $user['user_id']): ?><option value="<?= esc($user['user_id']) ?>" <?= $headId === $user['user_id'] ? 'selected' : '' ?>><?= esc($user['last_name'] . ', ' . $user['first_name'] . ' (' . $user['employee_id'] . ')') ?><?= $user['account_status'] !== 'Active' ? ' — Inactive' : '' ?></option><?php endif ?><?php endforeach ?></select><small class="field-help">Section membership is maintained separately through Assign Users.</small></label>
        <?php endif ?>
    </div>
    <div class="form-actions"><a class="button" href="<?= site_url('admin/organization/' . $plural) ?>">Cancel</a><button class="button button-primary" type="submit"><?= $creating ? 'Create ' . ucfirst($type) : 'Save Changes' ?></button></div>
</form>
<?= $this->endSection() ?>
