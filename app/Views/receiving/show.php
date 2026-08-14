<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading receiving-page-heading">
    <a class="document-back-link" href="<?= site_url('receiving') ?>">← Receiving</a>
    <div class="receiving-heading-row">
        <div>
            <div class="document-title-row">
                <h1 class="page-title document-number" title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></h1>
                <span class="badge badge-info"><?= esc($document['status_name']) ?></span>
            </div>
            <p class="lead compact document-subject"><?= esc($document['subject']) ?></p>
        </div>
    <div class="heading-actions">
        <?php if ($canUpdate): ?><a class="button" href="<?= site_url('receiving/' . $document['document_id'] . '/edit') ?>">Edit record</a><?php endif ?>
        <?php if ($canOpenDocument): ?><a class="button button-primary" href="<?= site_url('documents/' . $document['document_id']) ?>">Open document details</a><?php endif ?>
    </div>
    </div>
</section>

<?php if (! empty($document['client_tracking_token'])): ?>
<section class="panel client-handoff-card">
    <?php $clientReference = (string) ($document['client_tracking_reference'] ?? \App\Services\ClientTrackingService::displayToken((string) $document['client_tracking_token'])); ?>
    <div class="client-handoff-copy">
        <div class="eyebrow">CLIENT HANDOFF</div>
        <h2>Give tracking details to the client</h2>
        <p>The client can use this reference or scan the QR code to view limited document status.</p>
        <div class="client-reference-row">
            <div><span>Tracking reference</span><code id="clientTrackingToken"><?= esc($clientReference) ?></code></div>
            <button class="button" type="button" data-copy-client-token>Copy reference</button>
        </div>
        <p class="copy-status" data-copy-status role="status" aria-live="polite"></p>
        <a class="client-portal-utility" href="<?= site_url('track') ?>" target="_blank" rel="noopener">Open client tracking portal ↗</a>
    </div>
    <figure class="client-handoff-qr">
        <div><img src="<?= site_url('receiving/' . $document['document_id'] . '/client-tracking-qr') ?>" alt="QR code for client document tracking"></div>
        <figcaption>Scan to track</figcaption>
    </figure>
</section>
<script>
document.querySelector('[data-copy-client-token]')?.addEventListener('click', async function () {
    const token = document.getElementById('clientTrackingToken')?.textContent?.trim();
    const status = document.querySelector('[data-copy-status]');
    if (!token) return;
    try {
        await navigator.clipboard.writeText(token);
        this.textContent = 'Copied';
        if (status) { status.classList.remove('is-error'); status.textContent = 'Tracking reference copied.'; }
        window.setTimeout(() => { this.textContent = 'Copy reference'; if (status) status.textContent = ''; }, 2000);
    } catch (_) {
        if (status) { status.classList.add('is-error'); status.textContent = 'Copy was unavailable. Select and copy the reference manually.'; }
    }
});
</script>
<?php endif ?>

<div class="detail-grid">
    <section class="panel form-section detail-main">
        <div class="detail-header"><h2>Document information</h2></div>
        <dl class="definition-grid">
            <div><dt>Receiving number</dt><dd><?= esc($document['receiving_number']) ?></dd></div>
            <div><dt>Document type</dt><dd><?= esc($document['type_name']) ?></dd></div>
            <div><dt>Date received</dt><dd><time datetime="<?= esc($document['date_received'], 'attr') ?>"><?= esc(gmdate('M j, Y · g:i A', strtotime($document['date_received'] . ' UTC'))) ?></time><small class="value-note">UTC</small></dd></div>
            <div><dt>Received by</dt><dd><?= esc($document['receiver_first_name'] . ' ' . $document['receiver_last_name']) ?></dd></div>
        </dl>
        <div class="detail-copy"><h3>Description</h3><p><?= nl2br(esc($document['description'])) ?></p></div>
        <?php if ($document['remarks']): ?><div class="detail-copy"><h3>Remarks</h3><p><?= nl2br(esc($document['remarks'])) ?></p></div><?php endif ?>
    </section>

    <aside class="detail-side">
        <section class="panel form-section">
            <h2>Sender</h2>
            <dl class="definition-list">
                <div><dt>Name</dt><dd><?= esc($document['sender_name']) ?></dd></div>
                <div><dt>Organization</dt><dd><?= esc($document['sender_organization'] ?: '—') ?></dd></div>
                <div><dt>Email</dt><dd><a href="mailto:<?= esc($document['sender_email'], 'attr') ?>"><?= esc($document['sender_email']) ?></a></dd></div>
                <div><dt>Contact</dt><dd><?php if ($document['sender_contact_number']): ?><a href="tel:<?= esc((string) preg_replace('/[^+0-9]/', '', $document['sender_contact_number']), 'attr') ?>"><?= esc($document['sender_contact_number']) ?></a><?php else: ?>—<?php endif ?></dd></div>
            </dl>
        </section>
    </aside>
</div>

<section class="panel form-section attachments-panel">
    <div class="detail-header"><h2>Attachments</h2><span class="badge badge-muted"><?= count($document['attachments']) ?></span></div>
    <?php if ($document['attachments'] === []): ?>
        <p class="muted">No attachments were uploaded with this document.</p>
    <?php else: ?>
        <div class="attachment-list">
            <?php foreach ($document['attachments'] as $attachment): ?>
                <?php if ($canDownload): ?>
                    <a class="attachment-item" href="<?= site_url('receiving/' . $document['document_id'] . '/attachments/' . $attachment['attachment_id']) ?>">
                        <span><strong><?= esc($attachment['original_file_name']) ?></strong><small><?= esc(strtoupper($attachment['file_extension'] ?? 'FILE')) ?> · <?= number_format(((int) $attachment['file_size_bytes']) / 1024, 1) ?> KB</small></span>
                        <span class="button button-small">Download</span>
                    </a>
                <?php else: ?>
                    <div class="attachment-item attachment-item-readonly"><span><strong><?= esc($attachment['original_file_name']) ?></strong><small><?= esc(strtoupper($attachment['file_extension'] ?? 'FILE')) ?> · <?= number_format(((int) $attachment['file_size_bytes']) / 1024, 1) ?> KB</small></span><span class="muted">Restricted</span></div>
                <?php endif ?>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>

<?php if ($isAdministrator): ?>
<section class="panel form-section attachments-panel admin-delivery-panel">
    <h2>Notification Delivery</h2>
    <p class="muted admin-only-note">Administrator diagnostics</p>
    <?php if ($document['notifications'] === []): ?><p class="muted">No notification attempt was recorded. The channel may be disabled, the message may not have been requested, or no individual Telegram recipient was assigned.</p>
    <?php else: ?><div class="notification-list"><?php foreach ($document['notifications'] as $notice): ?><div class="notification-item">
        <div><strong><?= esc($notice['notification_channel']) ?> · <?= esc($notice['notification_type']) ?></strong><small><?= esc($notice['created_at']) ?> UTC · <?= (int) $notice['attempt_count'] ?> attempt<?= (int) $notice['attempt_count'] === 1 ? '' : 's' ?></small></div>
        <div class="notification-result"><span class="badge <?= $notice['status'] === 'Sent' ? 'badge-success' : ($notice['status'] === 'Failed' ? 'badge-warning' : 'badge-muted') ?>"><?= esc($notice['status']) ?></span><?php if ($notice['error_message']): ?><small><?= esc($notice['error_message']) ?></small><?php endif ?></div>
    </div><?php endforeach ?></div><?php endif ?>
</section>
<?php endif ?>
<?= $this->endSection() ?>
