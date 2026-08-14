<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $name, string $fallback = ''): string => (string) old($name, $action[$name] ?? $fallback, false);
$active = (string) old('active', isset($action['active']) ? (string) (int) $action['active'] : '1', false);
$remarksRequired = (string) old('requires_remarks', isset($action['requires_remarks']) ? (string) (int) $action['requires_remarks'] : '0', false) === '1';
$selectedRoles = (array) old('role_ids', $action['role_ids'] ?? [], false);
$historyLocked = ! $creating && (int) ($action['history_count'] ?? 0) > 0;
?>
<section class="page-heading">
    <div class="eyebrow">ROUTING ACTIONS</div>
    <h1 class="page-title"><?= $creating ? 'Create Routing Action' : 'Edit Routing Action' ?></h1>
    <p class="lead compact">This configuration controls the action choices and resulting status for future routing events.</p>
</section>
<?php if ($errors): ?><div class="alert alert-error"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if ($historyLocked): ?><div class="alert alert-info">This action already appears in routing history, so its name is protected to keep historical event labels stable. Other settings apply to future routing events.</div><?php endif ?>

<form class="panel form-section routing-action-form" action="<?= $creating ? site_url('admin/routing-actions') : site_url('admin/routing-actions/' . $action['action_id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="detail-header"><h2>Action Policy</h2><span class="muted"><?= $creating ? 'New routing choice' : number_format((int) $action['history_count']) . ' recorded uses' ?></span></div>
    <div class="form-grid">
        <label class="field field-wide"><span>Action Name *</span><input name="action_name" maxlength="100" value="<?= esc($value('action_name')) ?>" <?= $historyLocked ? 'readonly' : '' ?> required><?php if ($historyLocked): ?><small class="field-help">Protected because this action is already referenced by routing history.</small><?php endif ?></label>
        <label class="field field-wide"><span>Description *</span><textarea name="description" rows="3" maxlength="500" required><?= esc($value('description')) ?></textarea></label>
        <label class="field"><span>Resulting Document Status *</span><select name="resulting_status_id" required><option value="">Select resulting status</option><?php $selectedStatus = $value('resulting_status_id'); foreach ($statuses as $status): ?><option value="<?= esc($status['status_id']) ?>" <?= $selectedStatus === $status['status_id'] ? 'selected' : '' ?>><?= esc($status['status_name']) ?><?= (int) $status['is_terminal'] === 1 ? ' — Terminal' : '' ?></option><?php endforeach ?></select><small class="field-help">Terminal statuses complete the document and stop further routing.</small></label>
        <label class="field"><span>Status *</span><select name="active" required><option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option></select></label>
    </div>

    <div class="policy-block">
        <div><strong>Allowed Roles *</strong><small>Administrator is always allowed by the system override. Select at least one additional role. This controls action choices; the user must also have the normal Document Routing / ROUTE permission.</small></div>
        <div class="role-option-grid">
            <label class="role-option is-protected"><input type="checkbox" checked disabled><span><strong>Administrator</strong><small>System override</small></span></label>
            <?php foreach ($roles as $role): ?><label class="role-option"><input type="checkbox" name="role_ids[]" value="<?= esc($role['role_id']) ?>" <?= in_array($role['role_id'], $selectedRoles, true) ? 'checked' : '' ?>><span><strong><?= esc($role['role_name']) ?></strong><small><?= esc($role['role_type']) ?> role</small></span></label><?php endforeach ?>
        </div>
    </div>

    <label class="policy-toggle"><input type="checkbox" name="requires_remarks" value="1" <?= $remarksRequired ? 'checked' : '' ?>><span><strong>Require Remarks</strong><small>When enabled, routing is rejected until the user enters remarks for this action.</small></span></label>

    <div class="form-actions"><a class="button" href="<?= site_url('admin/routing-actions') ?>">Cancel</a><button class="button button-primary" type="submit"><?= $creating ? 'Create Action' : 'Save Changes' ?></button></div>
</form>
<?= $this->endSection() ?>
