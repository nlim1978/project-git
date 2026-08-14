<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading">
    <div class="eyebrow">ADMINISTRATION</div>
    <h1 class="page-title">Email Configuration</h1>
    <p class="lead compact">Configure SMTP delivery, sender identity, templates, and retry policy.</p>
</section>

<?php if ($loadError): ?><div class="alert alert-error"><?= esc($loadError) ?></div>
<?php else: ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $name, string $fallback = ''): string => (string) old($name, $settings[$name] ?? $fallback, false);
$enabled = (string) old('enabled', (string) (int) $settings['enabled'], false) === '1';
$passwordConfigured = (bool) $settings['password_configured'];
?>
<?php if ($errors): ?><div class="alert alert-error"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<div class="alert alert-info"><strong>Live notification channel:</strong> when enabled, iDocTrack uses these saved settings for sender registration confirmations after a document is committed. Use Send Test Email to verify SMTP independently.</div>

<form class="settings-panel" action="<?= site_url('admin/email-settings') ?>" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <section class="settings-section">
        <div class="service-status-card"><div><strong>Email Notification Service</strong><small>Controls automatic sender confirmations after successful document registration.</small></div><span id="emailServiceBadge" class="badge <?= $enabled ? 'badge-success' : 'badge-muted' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></div>
        <label class="settings-toggle"><span><strong>Enable Email Notification Configuration</strong><small>Turn off to keep the channel disabled without deleting its SMTP settings.</small></span><span class="toggle-control"><input id="emailEnabled" type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>><span></span></span></label>
    </section>

    <section class="settings-section">
        <div class="settings-section-head"><h2>SMTP Server</h2><p>Connection settings used to send email.</p></div>
        <div class="form-grid">
            <label class="field"><span>SMTP Server *</span><input name="smtp_server" maxlength="255" value="<?= esc($value('smtp_server')) ?>" placeholder="smtp.example.gov" required></label>
            <label class="field"><span>SMTP Port *</span><input type="number" name="smtp_port" min="1" max="65535" value="<?= esc($value('smtp_port', '587')) ?>" required></label>
            <label class="field"><span>Encryption Type *</span><select name="encryption_type" required><?php foreach (['STARTTLS', 'SSL/TLS', 'None'] as $crypto): ?><option value="<?= esc($crypto) ?>" <?= $value('encryption_type') === $crypto ? 'selected' : '' ?>><?= esc($crypto) ?></option><?php endforeach ?></select></label>
            <label class="field"><span>Retry Attempts *</span><input type="number" name="retry_attempts" min="0" max="10" value="<?= esc($value('retry_attempts', '3')) ?>" required></label>
            <label class="field"><span>SMTP Username</span><input name="smtp_username" maxlength="255" value="<?= esc($value('smtp_username')) ?>" autocomplete="off"></label>
            <label class="field"><span>SMTP Password</span><input type="password" name="smtp_password" maxlength="512" value="" autocomplete="new-password" placeholder="<?= $passwordConfigured ? 'Saved — leave blank to keep current password' : 'No password saved' ?>"><small class="field-help"><?= $passwordConfigured ? 'A protected password is stored. Enter a new one only to replace it.' : 'Leave blank for SMTP servers that do not require authentication.' ?></small></label>
        </div>
        <?php if (! $settings['encryption_key_configured']): ?><div class="inline-notice warning"><strong>Encryption key not configured.</strong> Run <code>php spark key:generate</code> once before saving an SMTP password.</div><?php else: ?><div class="inline-notice success">Application encryption key is configured; new SMTP passwords will be encrypted before database storage.</div><?php endif ?>
        <?php if ($passwordConfigured): ?><label class="compact-check"><input type="checkbox" name="clear_password" value="1"><span>Clear the saved SMTP password when configuration is saved</span></label><?php endif ?>
    </section>

    <section class="settings-section">
        <div class="settings-section-head"><h2>Sender Information</h2><p>Identity shown to recipients of system-generated email.</p></div>
        <div class="form-grid"><label class="field"><span>Sender Email Address *</span><input type="email" name="sender_email" maxlength="254" value="<?= esc($value('sender_email')) ?>" required></label><label class="field"><span>Sender Name *</span><input name="sender_name" maxlength="255" value="<?= esc($value('sender_name')) ?>" required></label></div>
    </section>

    <section class="settings-section">
        <div class="settings-section-head"><h2>Email Templates</h2><p>Supported placeholders: <code>{{sender_name}}</code>, <code>{{receiving_number}}</code>, <code>{{document_control_number}}</code>, <code>{{subject}}</code>.</p></div>
        <div class="form-grid"><label class="field field-wide"><span>Email Subject Template *</span><input id="subjectTemplate" name="subject_template" maxlength="1000" value="<?= esc($value('subject_template')) ?>" required></label><label class="field field-wide"><span>Email Body Template *</span><textarea id="bodyTemplate" name="body_template" rows="9" maxlength="10000" required><?= esc($value('body_template')) ?></textarea></label></div>
        <div class="template-preview"><strong>Preview</strong><h3 id="previewSubject"></h3><pre id="previewBody"></pre></div>
    </section>

    <div class="settings-actions"><button id="refreshPreview" class="button" type="button">Refresh Preview</button><button class="button button-primary" type="submit">Save Configuration</button></div>
</form>

<form class="settings-test-action" action="<?= site_url('admin/email-settings/test') ?>" method="post" onsubmit="return confirm('Send a real SMTP test message to <?= esc($settings['sender_email']) ?> using the currently SAVED settings?');">
    <?= csrf_field() ?><div><strong>SMTP Test</strong><small>Save any changes above first. This sends a real test message to the configured sender address.</small></div><button class="button" type="submit">Send Test Email</button>
</form>

<script>
(function () {
    const enabled = document.getElementById('emailEnabled');
    const badge = document.getElementById('emailServiceBadge');
    const subject = document.getElementById('subjectTemplate');
    const body = document.getElementById('bodyTemplate');
    const sample = {
        '{{sender_name}}': 'Juan Dela Cruz', '{{receiving_number}}': 'RCV-20260807-DEMO001',
        '{{document_control_number}}': 'MEM-20260807-DEMO001', '{{subject}}': 'Request for Records Verification'
    };
    const replace = text => Object.entries(sample).reduce((result, entry) => result.split(entry[0]).join(entry[1]), text || '');
    const preview = () => {
        document.getElementById('previewSubject').textContent = replace(subject.value);
        document.getElementById('previewBody').textContent = replace(body.value);
    };
    enabled.addEventListener('change', () => { badge.textContent = enabled.checked ? 'Enabled' : 'Disabled'; badge.className = 'badge ' + (enabled.checked ? 'badge-success' : 'badge-muted'); });
    document.getElementById('refreshPreview').addEventListener('click', preview);
    subject.addEventListener('input', preview); body.addEventListener('input', preview); preview();
}());
</script>
<?php endif ?>
<?= $this->endSection() ?>
