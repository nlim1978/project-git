<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$currentRoles = (array) old('role_ids', $user['role_ids'] ?? [], false);
$currentSections = (array) old('section_ids', $user['section_ids'] ?? [], false);
$currentPrimary = (string) old('primary_section_id', $user['primary_section_id'] ?? '', false);
$field = static fn (string $name, string $fallback = ''): string => (string) old($name, $user[$name] ?? $fallback, false);
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">USER MANAGEMENT</div>
        <h1 class="page-title"><?= $creating ? 'Add user' : 'Edit user' ?></h1>
        <p class="lead compact">Account identity, access roles, and organizational assignment.</p>
    </div>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<form class="user-form" action="<?= $creating ? site_url('admin/users') : site_url('admin/users/' . $user['user_id']) ?>" method="post">
    <?= csrf_field() ?>
    <section class="panel form-section">
        <h2>Account</h2>
        <div class="form-grid">
            <label class="field"><span>Employee ID</span><input name="employee_id" maxlength="20" value="<?= esc($field('employee_id')) ?>" required></label>
            <label class="field"><span>Username</span><input name="username" maxlength="50" value="<?= esc($field('username')) ?>" autocomplete="off" required></label>
            <label class="field"><span>First name</span><input name="first_name" maxlength="100" value="<?= esc($field('first_name')) ?>" required></label>
            <label class="field"><span>Middle name</span><input name="middle_name" maxlength="100" value="<?= esc($field('middle_name')) ?>"></label>
            <label class="field"><span>Last name</span><input name="last_name" maxlength="100" value="<?= esc($field('last_name')) ?>" required></label>
            <label class="field"><span>Email</span><input name="email" type="email" maxlength="150" value="<?= esc($field('email')) ?>" required></label>
            <label class="field"><span>Contact number</span><input name="contact_number" maxlength="20" value="<?= esc($field('contact_number')) ?>"></label>
            <label class="field"><span>Status</span><select name="account_status"><option value="Active" <?= $field('account_status', 'Active') === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $field('account_status') === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
            <label class="field"><span><?= $creating ? 'Password' : 'New password (optional)' ?></span><input name="password" type="password" minlength="12" maxlength="255" autocomplete="new-password" <?= $creating ? 'required' : '' ?>></label>
            <label class="field"><span>Confirm password</span><input name="password_confirm" type="password" minlength="12" maxlength="255" autocomplete="new-password" <?= $creating ? 'required' : '' ?>></label>
        </div>
    </section>

    <section class="panel form-section">
        <h2>Roles</h2>
        <p class="muted">A user may have more than one role.</p>
        <div class="choice-grid">
            <?php foreach ($roles as $role): ?>
                <label class="choice"><input type="checkbox" name="role_ids[]" value="<?= esc($role['role_id']) ?>" <?= in_array($role['role_id'], $currentRoles, true) ? 'checked' : '' ?>><span><strong><?= esc($role['role_name']) ?></strong><small><?= esc($role['description'] ?? '') ?></small></span></label>
            <?php endforeach ?>
        </div>
    </section>

    <section class="panel form-section">
        <h2>Telegram notification profile</h2>
        <p class="muted">Used only for internal iDocTrack assignment/routing notifications. The user must start the configured iDocTrack bot before it can message their private chat.</p>
        <div class="form-grid">
            <label class="field"><span>Telegram Username <small>(optional)</small></span><input name="telegram_username" maxlength="100" value="<?= esc($field('telegram_username')) ?>" placeholder="@username"></label>
            <label class="field"><span>Telegram Chat ID <small>(required when enabled)</small></span><input name="telegram_chat_id" maxlength="100" inputmode="numeric" value="<?= esc($field('telegram_chat_id')) ?>" placeholder="123456789"><small class="field-help">Private numeric Chat ID used by the Bot API. Do not enter the bot's own username here.</small></label>
        </div>
        <?php $telegramEnabled = (string) old('telegram_notification_enabled', (string) (int) ($user['telegram_notification_enabled'] ?? 0), false) === '1'; ?>
        <input type="hidden" name="telegram_notification_enabled" value="0">
        <label class="policy-toggle"><input type="checkbox" name="telegram_notification_enabled" value="1" <?= $telegramEnabled ? 'checked' : '' ?>><span><strong>Enable Telegram notifications for this user</strong><small>Global Telegram Configuration and its event switch must also be enabled.</small></span></label>
    </section>

    <section class="panel form-section">
        <h2>Sections</h2>
        <p class="muted">Check every assigned section and choose exactly one primary section.</p>
        <div class="section-list">
            <?php foreach ($sections as $section): ?>
                <div class="section-choice">
                    <label><input type="checkbox" name="section_ids[]" value="<?= esc($section['section_id']) ?>" <?= in_array($section['section_id'], $currentSections, true) ? 'checked' : '' ?>> <strong><?= esc($section['section_code']) ?></strong> — <?= esc($section['section_name']) ?></label>
                    <label class="primary-choice"><input type="radio" name="primary_section_id" value="<?= esc($section['section_id']) ?>" <?= $currentPrimary === $section['section_id'] ? 'checked' : '' ?>> Primary</label>
                    <small><?= esc($section['department_name']) ?> · <?= esc($section['office_name']) ?></small>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <div class="form-actions"><a class="button" href="<?= site_url('admin/users') ?>">Cancel</a><button class="button button-primary" type="submit"><?= $creating ? 'Create user' : 'Save changes' ?></button></div>
</form>
<?= $this->endSection() ?>
