<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $selectedNow = (array) old('user_ids', $selected, false); ?>
<section class="page-heading">
    <div class="eyebrow">ORGANIZATION STRUCTURE · SECTIONS</div>
    <h1 class="page-title">Assign Users</h1>
    <p class="lead compact"><strong><?= esc($section['section_code']) ?> — <?= esc($section['section_name']) ?></strong></p>
</section>
<div class="alert alert-info">New memberships are added as secondary assignments. A user's primary section can only be changed in User Management.</div>
<form class="panel form-section" action="<?= site_url('admin/organization/sections/' . $section['section_id'] . '/assignments') ?>" method="post">
    <?= csrf_field() ?>
    <div class="permission-section-head"><div><h2>Section Membership</h2><p class="muted">Select active users who belong to this section.</p></div><div class="heading-actions"><button id="selectActiveUsers" class="button button-small" type="button">Select active</button><button id="clearUsers" class="button button-small" type="button">Clear</button></div></div>
    <div class="assignment-grid">
        <?php foreach ($users as $user): ?>
            <?php $isActive = $user['account_status'] === 'Active'; $checked = in_array($user['user_id'], $selectedNow, true); ?>
            <label class="assignment-option <?= ! $isActive ? 'is-disabled' : '' ?>">
                <input type="checkbox" name="user_ids[]" value="<?= esc($user['user_id']) ?>" <?= $checked ? 'checked' : '' ?> <?= ! $isActive ? 'disabled' : '' ?>>
                <span><strong><?= esc($user['last_name'] . ', ' . $user['first_name']) ?></strong><small><?= esc($user['employee_id']) ?> · <?= esc($user['account_status']) ?></small></span>
            </label>
        <?php endforeach ?>
    </div>
    <div class="form-actions"><a class="button" href="<?= site_url('admin/organization/sections') ?>">Cancel</a><button class="button button-primary" type="submit">Save Assignments</button></div>
</form>
<script>
(function () {
    const enabled = Array.from(document.querySelectorAll('.assignment-option input:not(:disabled)'));
    document.getElementById('selectActiveUsers')?.addEventListener('click', () => enabled.forEach(box => { box.checked = true; }));
    document.getElementById('clearUsers')?.addEventListener('click', () => enabled.forEach(box => { box.checked = false; }));
}());
</script>
<?= $this->endSection() ?>
