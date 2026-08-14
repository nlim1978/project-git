<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $name, string $fallback = ''): string => (string) old($name, $fallback, false);
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">RECEIVING</div>
        <h1 class="page-title">Register document</h1>
        <p class="lead compact">Record the incoming document and its initial iDocTrack assignment.</p>
    </div>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<form class="user-form" action="<?= site_url('receiving') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <section class="panel form-section">
        <h2>Document information</h2>
        <div class="form-grid">
            <label class="field"><span>Document type</span><select name="document_type_id" required><option value="">Select document type</option><?php foreach ($documentTypes as $type): ?><option value="<?= esc($type['document_type_id']) ?>" <?= $value('document_type_id') === $type['document_type_id'] ? 'selected' : '' ?>><?= esc($type['type_name']) ?> (<?= esc($type['prefix']) ?>)</option><?php endforeach ?></select></label>
            <label class="field field-wide"><span>Subject</span><input name="subject" maxlength="255" value="<?= esc($value('subject')) ?>" required></label>
            <label class="field field-wide"><span>Description</span><textarea name="description" rows="5" maxlength="5000" required><?= esc($value('description')) ?></textarea></label>
            <label class="field field-wide"><span>Remarks <small>(optional)</small></span><textarea name="remarks" rows="3" maxlength="5000"><?= esc($value('remarks')) ?></textarea></label>
        </div>
    </section>

    <section class="panel form-section">
        <h2>Sender</h2>
        <div class="form-grid">
            <label class="field"><span>Sender name</span><input name="sender_name" maxlength="255" value="<?= esc($value('sender_name')) ?>" required></label>
            <label class="field"><span>Organization <small>(optional)</small></span><input name="sender_organization" maxlength="255" value="<?= esc($value('sender_organization')) ?>"></label>
            <label class="field"><span>Email</span><input name="sender_email" type="email" maxlength="254" value="<?= esc($value('sender_email')) ?>" required></label>
            <label class="field"><span>Contact number <small>(optional)</small></span><input name="sender_contact_number" maxlength="20" value="<?= esc($value('sender_contact_number')) ?>"></label>
        </div>
    </section>

    <section class="panel form-section">
        <h2>Initial assignment</h2>
        <div class="form-grid">
            <label class="field"><span>Receiving section</span><select id="initial_section_id" name="initial_section_id" required><option value="">Select section</option><?php foreach ($sections as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $value('initial_section_id') === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code']) ?> — <?= esc($section['section_name']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span>Responsible user <small>(optional)</small></span><select id="initial_responsible_user_id" name="initial_responsible_user_id"><option value="">Unassigned / section inbox</option><?php foreach ($sectionUsers as $member): ?><option data-section="<?= esc($member['section_id']) ?>" value="<?= esc($member['user_id']) ?>" <?= $value('initial_responsible_user_id') === $member['user_id'] ? 'selected' : '' ?>><?= esc($member['last_name'] . ', ' . $member['first_name'] . ' · ' . $member['employee_id']) ?></option><?php endforeach ?></select><small class="field-help">Only users assigned to the selected section can be responsible.</small></label>
            <label class="field field-wide"><span>Email notification</span><select name="send_email_notification"><option value="1" <?= $value('send_email_notification', '1') === '1' ? 'selected' : '' ?>>Send after successful registration</option><option value="0" <?= $value('send_email_notification', '1') === '0' ? 'selected' : '' ?>>Do not send</option></select><small class="field-help">Registration email goes to the Sender Email above only after the document is successfully saved.</small></label>
        </div>
    </section>

    <section class="panel form-section">
        <h2>Attachments</h2>
        <label class="field"><span>Files <small>(optional)</small></span><input name="attachments[]" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"><small class="field-help">PDF, Word, Excel, JPG, or PNG. Maximum 10 MB per file. Stored privately and downloaded through authorized iDocTrack access.</small></label>
    </section>

    <div class="form-actions"><a class="button" href="<?= site_url('receiving') ?>">Cancel</a><button class="button button-primary" type="submit">Register document</button></div>
</form>

<script>
(function () {
    const section = document.getElementById('initial_section_id');
    const user = document.getElementById('initial_responsible_user_id');
    const refreshUsers = () => {
        const selectedSection = section.value;
        for (const option of user.options) {
            if (!option.dataset.section) continue;
            option.hidden = selectedSection === '' || option.dataset.section !== selectedSection;
            option.disabled = option.hidden;
        }
        const selected = user.options[user.selectedIndex];
        if (selected && selected.disabled) user.value = '';
    };
    section.addEventListener('change', refreshUsers);
    refreshUsers();
}());
</script>
<?= $this->endSection() ?>
