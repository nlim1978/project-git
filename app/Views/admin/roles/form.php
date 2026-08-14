<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$field = static fn (string $name, string $fallback = ''): string => (string) old($name, $role[$name] ?? $fallback, false);
$currentPermissions = (array) old('permission_ids', $role['permission_ids'] ?? [], false);
$isSystem = ! $creating && ($role['role_type'] ?? '') === 'System';
$isAdministrator = $isSystem && ($role['role_name'] ?? '') === 'Administrator';
$isSectionHead = $isSystem && ($role['role_name'] ?? '') === 'Section Head';
$currentActive = (string) old('active', isset($role['active']) ? (string) (int) $role['active'] : '1', false);
?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ROLES &amp; PERMISSIONS</div>
        <h1 class="page-title"><?= $creating ? 'Create Role' : 'Edit Role' ?></h1>
        <p class="lead compact"><?= $isSystem ? 'System role identity is protected; access permissions remain policy-controlled.' : 'Define a custom role and select its permitted system actions.' ?></p>
    </div>
    <?php if ($isSystem): ?><span class="badge badge-system">System role</span><?php endif ?>
</section>

<?php if ($errors): ?><div class="alert alert-error" role="alert"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if ($isAdministrator): ?><div class="alert alert-success">Administrator is a protected full-access role and cannot be deactivated or have permissions removed.</div><?php endif ?>

<form class="role-form" action="<?= $creating ? site_url('admin/roles') : site_url('admin/roles/' . $role['role_id']) ?>" method="post">
    <?= csrf_field() ?>
    <section class="panel form-section">
        <div class="detail-header"><h2>Role Profile</h2><span class="muted"><?= $creating ? 'Custom role' : esc($role['role_type']) ?></span></div>
        <div class="form-grid">
            <label class="field"><span>Role Name *</span><input name="role_name" maxlength="100" value="<?= esc($field('role_name')) ?>" <?= $isSystem ? 'readonly' : '' ?> required><?php if ($isSystem): ?><small class="field-help">System role names cannot be changed.</small><?php endif ?></label>
            <label class="field"><span>Status *</span><select name="active" required><?php if ($isAdministrator): ?><option value="1">Active — protected</option><?php else: ?><option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Inactive</option><?php endif ?></select></label>
            <label class="field field-wide"><span>Description *</span><textarea name="description" rows="3" maxlength="500" required><?= esc($field('description')) ?></textarea></label>
        </div>
    </section>

    <section class="panel form-section">
        <div class="permission-section-head">
            <div><h2>Permission Matrix</h2><p class="muted">Grant only the module actions required by this role.</p></div>
            <?php if (! $isAdministrator): ?><div class="heading-actions"><button id="selectAllPermissions" class="button button-small" type="button">Select all</button><button id="clearPermissions" class="button button-small" type="button">Clear</button></div><?php endif ?>
        </div>
        <div class="permission-groups">
            <?php foreach ($permissionGroups as $group): ?>
                <section class="permission-group">
                    <h3><?= esc($group['module']) ?></h3>
                    <?php if ($isSectionHead && $group['module'] === 'Receiving'): ?><p class="field-help">Incoming registration belongs to Receiving Personnel and is unavailable to Section Heads.</p><?php endif ?>
                    <div class="permission-options">
                        <?php foreach ($group['permissions'] as $permission): ?>
                            <?php $restricted = $isSectionHead && $group['module'] === 'Receiving'; ?>
                            <?php $checked = ! $restricted && ($isAdministrator || in_array($permission['permission_id'], $currentPermissions, true)); ?>
                            <label class="permission-option">
                                <input type="checkbox" name="permission_ids[]" value="<?= esc($permission['permission_id']) ?>" <?= $checked ? 'checked' : '' ?> <?= ($isAdministrator || $restricted) ? 'disabled' : '' ?>>
                                <span><strong><?= esc(ucwords(strtolower((string) $permission['action_name']))) ?></strong><small><?= esc($permission['page_name']) ?></small></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </section>
            <?php endforeach ?>
        </div>
    </section>

    <div class="form-actions"><a class="button" href="<?= site_url('admin/roles') ?>">Cancel</a><button class="button button-primary" type="submit"><?= $creating ? 'Create Role' : 'Save Changes' ?></button></div>
</form>

<?php if (! $isAdministrator): ?>
<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('input[name="permission_ids[]"]:not(:disabled)'));
    document.getElementById('selectAllPermissions')?.addEventListener('click', () => boxes.forEach(box => { box.checked = true; }));
    document.getElementById('clearPermissions')?.addEventListener('click', () => boxes.forEach(box => { box.checked = false; }));
}());
</script>
<?php endif ?>
<?= $this->endSection() ?>
