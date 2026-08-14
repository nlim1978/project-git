<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $name): string => (string) old($name, (string) ($document[$name] ?? ''), false);
?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">RECEIVING · RECORD CORRECTION</div>
        <h1 class="page-title">Edit received document</h1>
        <p class="lead compact" title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></p>
    </div>
    <a class="button" href="<?= site_url('receiving/' . $document['document_id']) ?>">Cancel</a>
</section>

<?php if ($errors): ?><div class="alert alert-error" role="alert"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>

<div class="alert alert-info">Document details can be corrected here. Tracking identifiers, document type, received date, and status remain protected. If you are eligible to correct the current assignment, use the Assignment section below.</div>

<form class="panel form-section" action="<?= site_url('receiving/' . $document['document_id']) ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="document_version" value="<?= esc((string) old('document_version', $document['updated_at'], false), 'attr') ?>">
    <div class="settings-section-head"><h2>Protected tracking fields</h2><p>Shown for reference and not editable from Receiving.</p></div>
    <dl class="definition-grid edit-reference-grid">
        <div><dt>Control number</dt><dd title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></dd></div>
        <div><dt>Receiving number</dt><dd><?= esc($document['receiving_number']) ?></dd></div>
        <div><dt>Document type</dt><dd><?= esc($document['type_name']) ?></dd></div>
        <div><dt>Status</dt><dd><?= esc($document['status_name']) ?></dd></div>
        <div><dt>Current section</dt><dd><?= esc($document['section_name']) ?></dd></div>
        <div><dt>Date received</dt><dd><?= esc($document['date_received']) ?> UTC</dd></div>
    </dl>

    <div class="settings-section-head form-subsection"><h2>Document information</h2><p>Corrections are written to the Audit Log.</p></div>
    <div class="form-grid">
        <label class="field field-wide"><span>Subject *</span><input name="subject" maxlength="255" required value="<?= esc($value('subject')) ?>"></label>
        <label class="field field-wide"><span>Description / Particulars *</span><textarea name="description" rows="6" maxlength="5000" required><?= esc($value('description')) ?></textarea></label>
        <label class="field"><span>Sender Name *</span><input name="sender_name" maxlength="255" required value="<?= esc($value('sender_name')) ?>"></label>
        <label class="field"><span>Sender Organization</span><input name="sender_organization" maxlength="255" value="<?= esc($value('sender_organization')) ?>"></label>
        <label class="field"><span>Sender Email *</span><input type="email" name="sender_email" maxlength="254" required value="<?= esc($value('sender_email')) ?>"></label>
        <label class="field"><span>Sender Contact Number</span><input name="sender_contact_number" maxlength="20" value="<?= esc($value('sender_contact_number')) ?>"></label>
        <label class="field field-wide"><span>Receiving Remarks</span><textarea name="remarks" rows="4" maxlength="5000"><?= esc($value('remarks')) ?></textarea></label>
    </div>
    <div class="form-actions"><a class="button" href="<?= site_url('receiving/' . $document['document_id']) ?>">Cancel</a><button class="button button-primary" type="submit">Save changes</button></div>
</form>

<?php if (($assignment['can_reassign'] ?? false) === true): ?>
<?php
$oldAssignmentSection = (string) old('destination_section_id', (string) $assignment['current_section_id'], false);
$oldAssignmentUser = (string) old('destination_user_id', (string) ($assignment['current_responsible_user_id'] ?? ''), false);
?>
<section class="assignment-correction-trigger">
    <button class="button" type="button" id="open-receiving-correction">Correct Assignment</button>
</section>
<dialog class="correction-dialog" id="receiving-correction-dialog">
<div class="dialog-card">
    <div class="detail-header"><div><div class="eyebrow">REASSIGN</div><h2>Correct Assignment</h2><p class="muted compact">Correct the current section or responsible person. The original receipt remains in the audit trail.</p></div><button class="dialog-close" type="button" id="close-receiving-correction" aria-label="Close">×</button></div>
    <form class="assignment-correction-form" action="<?= site_url('documents/' . $document['document_id'] . '/reassign') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="document_version" value="<?= esc((string) old('document_version', $document['updated_at'], false), 'attr') ?>">
        <div class="form-grid">
            <label class="field"><span>Section *</span><select id="receiving_reassign_section" name="destination_section_id" required><?php foreach ($assignment['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $oldAssignmentSection === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code']) ?> - <?= esc($section['section_name']) ?></option><?php endforeach ?></select></label>
            <label class="field"><span>Responsible Person <small>(optional)</small></span><select id="receiving_reassign_user" name="destination_user_id"><option value="">Assign to section only</option><?php foreach ($assignment['section_users'] as $member): ?><option value="<?= esc($member['user_id']) ?>" data-section="<?= esc($member['section_id']) ?>" <?= $oldAssignmentUser === $member['user_id'] ? 'selected' : '' ?>><?= esc($member['last_name'] . ', ' . $member['first_name'] . ' - ' . $member['employee_id']) ?></option><?php endforeach ?></select></label>
        </div>
        <div class="form-actions"><button class="button" type="button" id="cancel-receiving-correction">Cancel</button><button class="button button-primary" type="submit">Update Assignment</button></div>
    </form>
</div>
</dialog>

<script>
(() => {
    const dialog = document.getElementById('receiving-correction-dialog');
    document.getElementById('open-receiving-correction')?.addEventListener('click', () => dialog?.showModal());
    document.getElementById('close-receiving-correction')?.addEventListener('click', () => dialog?.close());
    document.getElementById('cancel-receiving-correction')?.addEventListener('click', () => dialog?.close());
    const section = document.getElementById('receiving_reassign_section');
    const user = document.getElementById('receiving_reassign_user');
    if (!section || !user) return;
    const refresh = () => {
        for (const option of user.options) {
            if (!option.value) continue;
            option.hidden = option.dataset.section !== section.value;
            option.disabled = option.hidden;
        }
        const selected = user.options[user.selectedIndex];
        if (selected && selected.disabled) user.value = '';
    };
    section.addEventListener('change', refresh);
    refresh();
})();
</script>
<?php endif ?>
<?= $this->endSection() ?>
