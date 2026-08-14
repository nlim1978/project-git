<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$oldAction = (string) old('action_id', '', false);
$oldSection = (string) old('destination_section_id', $document['current_section_id'], false);
$oldUser = (string) old('destination_user_id', $document['current_responsible_user_id'] ?? '', false);
$oldRemarks = (string) old('remarks', '', false);
$oldReassignSection = (string) old('destination_section_id', $document['current_section_id'], false);
$oldReassignUser = (string) old('destination_user_id', $document['current_responsible_user_id'] ?? '', false);
$isArchived = ($documentContext ?? 'inbox') === 'archive';
$terminalEvent = $isArchived && $document['timeline'] !== [] ? $document['timeline'][count($document['timeline']) - 1] : null;
?>
<section class="page-heading document-page-heading">
    <a class="document-back-link" href="<?= site_url($isArchived ? 'archive' : 'inbox') ?>">← <?= $isArchived ? 'Document Archive' : 'General Inbox' ?></a>
    <div class="document-title-row">
        <h1 class="page-title document-number" title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></h1>
        <span class="badge <?= (int) $document['is_terminal'] === 1 ? 'badge-muted' : 'badge-info' ?>"><?= esc($document['status_name']) ?></span>
    </div>
    <p class="lead compact document-subject"><?= esc($document['subject']) ?></p>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><strong>Please review the routing form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<div class="document-detail-stack">
    <?php if ($isArchived): ?>
        <section class="panel engagement-detail archive-detail-state">
            <div class="engagement-strip"><span class="engagement-dot is-active"></span><span><strong>Archived record · <?= esc($terminalEvent['action_name'] ?? $document['status_name']) ?></strong><small>Completed<?= $terminalEvent ? ' by ' . esc(trim($terminalEvent['routed_by_first_name'] . ' ' . $terminalEvent['routed_by_last_name'])) . ' on ' . esc(gmdate('M j, Y · g:i A', strtotime($terminalEvent['routed_at'] . ' UTC'))) : '' ?>. This record is read-only.</small></span></div>
        </section>
    <?php else: ?>
        <section class="panel engagement-detail" data-document-id="<?= esc($document['document_id'], 'attr') ?>" data-engagement-url="<?= esc(site_url('documents/' . $document['document_id'] . '/engagement'), 'attr') ?>" data-confirm-url="<?= esc(site_url('documents/' . $document['document_id'] . '/confirm'), 'attr') ?>" data-heartbeat-url="<?= esc(site_url('documents/' . $document['document_id'] . '/heartbeat'), 'attr') ?>">
            <div class="engagement-strip" data-engagement-state></div>
            <?php if ($document['can_confirm_engagement'] && ! $document['engagement']): ?><button class="button button-confirm" type="button" data-confirm-document>Verify / Confirm</button><?php endif ?>
        </section>
    <?php endif ?>
    <div class="document-summary-layout">
        <section class="panel form-section">
            <div class="detail-header"><h2>Document information</h2></div>
            <dl class="definition-grid">
                <div><dt>Receiving number</dt><dd><?= esc($document['receiving_number']) ?></dd></div>
                <div><dt>Document type</dt><dd><?= esc($document['type_name']) ?></dd></div>
                <div><dt>Date received</dt><dd><?= esc($document['date_received']) ?> UTC</dd></div>
                <div><dt>Receiving personnel</dt><dd><?= esc($document['receiver_first_name'] . ' ' . $document['receiver_last_name']) ?></dd></div>
            </dl>
            <div class="detail-copy"><h3>Description / Particulars</h3><p><?= nl2br(esc($document['description'])) ?></p></div>
            <?php if ($document['remarks']): ?><div class="detail-copy"><h3>Receiving remarks</h3><p><?= nl2br(esc($document['remarks'])) ?></p></div><?php endif ?>
            <?php if ($document['latest_sender_remark']): ?>
                <div class="detail-copy sender-remark-callout">
                    <h3>Latest sender remark</h3>
                    <p><?= nl2br(esc($document['latest_sender_remark']['remarks'])) ?></p>
                    <small>From <?= esc(trim($document['latest_sender_remark']['routed_by_first_name'] . ' ' . $document['latest_sender_remark']['routed_by_last_name'])) ?> · <?= esc(gmdate('M j, Y · g:i A', strtotime($document['latest_sender_remark']['routed_at'] . ' UTC'))) ?></small>
                </div>
            <?php endif ?>
        </section>

        <aside class="document-summary-side">
            <section class="panel form-section">
                <h2>Sender</h2>
                <dl class="definition-list">
                    <div><dt>Name</dt><dd><?= esc($document['sender_name']) ?></dd></div>
                    <div><dt>Organization</dt><dd><?= esc($document['sender_organization'] ?: '—') ?></dd></div>
                    <div><dt>Email</dt><dd><?= esc($document['sender_email']) ?></dd></div>
                    <div><dt>Contact</dt><dd><?= esc($document['sender_contact_number'] ?: '—') ?></dd></div>
                </dl>
            </section>
        </aside>
    </div>

    <?php if ($document['can_route']): ?>
        <section class="panel form-section route-document-panel">
            <div class="route-workflow-heading">
                <div><div class="eyebrow">NEXT REQUIRED STEP</div><h2>Take action and route document</h2><p>Record what you did first, then choose where the document should go next.</p></div>
                <span class="route-step-badge">Action required</span>
            </div>
            <form class="route-form" action="<?= site_url('documents/' . $document['document_id'] . '/route') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="document_version" value="<?= esc((string) old('document_version', $document['updated_at'], false), 'attr') ?>">

                <label class="field routing-action-priority">
                    <span><strong>1. Action taken *</strong><small>Choose the work completed before routing this document.</small></span>
                    <select id="routing_action" name="action_id" required aria-describedby="routing_action_help">
                        <option value="" <?= $oldAction === '' ? 'selected' : '' ?> disabled>Select the action taken</option>
                        <option value="route_only" data-requires-remarks="0" data-requires-evidence="0" <?= $oldAction === 'route_only' ? 'selected' : '' ?>>No action taken — routing only</option>
                        <?php foreach ($document['actions'] as $action): ?><option value="<?= esc($action['action_id']) ?>" data-requires-remarks="<?= (int) $action['requires_remarks'] ?>" data-requires-evidence="<?= (int) ($action['requires_evidence'] ?? 0) ?>" data-terminal="<?= (int) ($action['is_terminal'] ?? 0) ?>" <?= $oldAction === $action['action_id'] ? 'selected' : '' ?>><?= esc($action['action_name']) ?><?= (int) $action['requires_remarks'] === 1 ? ' · remarks required' : '' ?><?= (int) ($action['requires_evidence'] ?? 0) === 1 ? ' · evidence required' : '' ?></option><?php endforeach ?>
                    </select>
                    <small id="routing_action_help">Select “routing only” explicitly when no processing action was performed.</small>
                </label>

                <fieldset class="routing-dependent-fields" <?= $oldAction === '' ? 'disabled' : '' ?>>
                    <legend class="sr-only">Routing destination and remarks</legend>
                    <div class="routing-destination-step">
                        <div class="route-step-heading"><strong>2. Send next</strong><small>Choose the destination after recording the action.</small></div>
                        <div class="form-grid">
                        <label class="field"><span>Destination Section *</span><select id="destination_section" name="destination_section_id" required><?php foreach ($document['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $oldSection === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code']) ?> — <?= esc($section['section_name']) ?></option><?php endforeach ?></select></label>
                        <label class="field"><span>Responsible Person <small>(optional)</small></span><select id="destination_user" name="destination_user_id"><option value="">Assign to section only</option><?php foreach ($document['section_users'] as $member): ?><option value="<?= esc($member['user_id']) ?>" data-section="<?= esc($member['section_id']) ?>" <?= $oldUser === $member['user_id'] ? 'selected' : '' ?>><?= esc($member['last_name'] . ', ' . $member['first_name'] . ' · ' . $member['employee_id']) ?></option><?php endforeach ?></select></label>
                        </div>
                    </div>
                    <div class="form-grid">
                        <label class="field field-wide"><span>Remarks <small id="remarks_hint">(optional)</small></span><textarea id="routing_remarks" name="remarks" rows="3" maxlength="5000"><?= esc($oldRemarks) ?></textarea></label>
                        <label class="field field-wide evidence-field" id="routing_evidence_field" hidden><span>Evidence * <small>PDF, JPG, or PNG · max 10 MB</small></span><input id="routing_evidence" name="evidence" type="file" accept=".pdf,.jpg,.jpeg,.png"></label>
                    </div>
                </fieldset>
                <div class="form-actions route-actions"><button class="button button-primary" type="submit" <?= $oldAction === '' ? 'disabled' : '' ?>>Route Document</button></div>
            </form>
        </section>
    <?php endif ?>

    <div class="document-utility-layout">
        <section class="panel form-section attachments-card">
            <h2>Attachments</h2>
            <?php if ($document['attachments'] === []): ?>
                <p class="muted utility-empty">No attachments uploaded.</p>
            <?php else: ?>
                <div class="attachment-list">
                    <?php foreach ($document['attachments'] as $attachment): ?>
                        <?php $isEvidence = str_starts_with((string) $attachment['file_name'], 'evidence-'); ?>
                        <?php if ($document['can_download_attachments']): ?>
                            <a class="attachment-item" href="<?= site_url('documents/' . $document['document_id'] . '/attachments/' . $attachment['attachment_id']) ?>">
                                <span><strong><?= esc($attachment['original_file_name']) ?></strong><small><?= $isEvidence ? 'EVIDENCE · ' : '' ?><?= esc(strtoupper($attachment['file_extension'] ?? 'FILE')) ?> · <?= number_format(((int) $attachment['file_size_bytes']) / 1024, 1) ?> KB</small></span>
                                <strong>Download</strong>
                            </a>
                        <?php else: ?>
                            <div class="attachment-item attachment-item-readonly">
                                <span><strong><?= esc($attachment['original_file_name']) ?></strong><small><?= $isEvidence ? 'EVIDENCE · ' : '' ?><?= esc(strtoupper($attachment['file_extension'] ?? 'FILE')) ?> · <?= number_format(((int) $attachment['file_size_bytes']) / 1024, 1) ?> KB</small></span>
                                <span class="muted">Restricted</span>
                            </div>
                        <?php endif ?>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
        <?php if ($document['can_print_qr']): ?><section class="panel document-tools-panel">
            <div class="document-tools-copy">
                <h2>Document QR</h2>
                <p class="muted">Quick access</p>
                <a class="button button-compact" target="_blank" rel="noopener" href="<?= site_url('documents/' . $document['document_id'] . '/qr/print') ?>">Print QR</a>
            </div>
            <div class="qr-image-wrap qr-image-compact"><img src="<?= site_url('documents/' . $document['document_id'] . '/qr') ?>" alt="QR code for <?= esc($document['document_control_number'], 'attr') ?>"></div>
        </section><?php endif ?>
    </div>

    <?php if ($document['can_recall_routing']): ?>
        <section class="panel form-section assignment-correction-panel">
            <div class="detail-header"><div><h2>Recall Incorrect Routing</h2><p class="muted compact">Return this document to the sending section before the destination takes a routing action. The recall remains in Routing History.</p></div><span class="eyebrow">RECALL</span></div>
            <form action="<?= site_url('documents/' . $document['document_id'] . '/recall') ?>" method="post" onsubmit="return confirm('Recall this routing and return the document to the sending section?');">
                <?= csrf_field() ?>
                <div class="form-actions route-actions"><button class="button" type="submit">Recall Routing</button></div>
            </form>
        </section>
    <?php endif ?>

    <?php if ($document['can_reassign_assignment']): ?>
        <section class="assignment-correction-trigger">
            <button class="button" type="button" data-open-dialog="assignment-correction-dialog">Correct Assignment</button>
        </section>
        <dialog class="correction-dialog" id="assignment-correction-dialog">
            <div class="dialog-card">
                <div class="detail-header"><div><div class="eyebrow">REASSIGN</div><h2>Correct Assignment</h2><p class="muted compact">Change the current section or responsible person without altering the original receipt.</p></div><button class="dialog-close" type="button" data-close-dialog aria-label="Close">×</button></div>
            <form class="assignment-correction-form" action="<?= site_url('documents/' . $document['document_id'] . '/reassign') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="document_version" value="<?= esc((string) old('document_version', $document['updated_at'], false), 'attr') ?>">
                <div class="form-grid">
                    <label class="field"><span>Section *</span><select id="reassign_section" name="destination_section_id" required><?php foreach ($document['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $oldReassignSection === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code']) ?> — <?= esc($section['section_name']) ?></option><?php endforeach ?></select></label>
                    <label class="field"><span>Responsible Person <small>(optional)</small></span><select id="reassign_user" name="destination_user_id"><option value="">Assign to section only</option><?php foreach ($document['section_users'] as $member): ?><option value="<?= esc($member['user_id']) ?>" data-section="<?= esc($member['section_id']) ?>" <?= $oldReassignUser === $member['user_id'] ? 'selected' : '' ?>><?= esc($member['last_name'] . ', ' . $member['first_name'] . ' · ' . $member['employee_id']) ?></option><?php endforeach ?></select></label>
                </div>
                <div class="form-actions route-actions"><button class="button" type="button" data-close-dialog>Cancel</button><button class="button button-primary" type="submit">Update Assignment</button></div>
            </form>
            </div>
        </dialog>
    <?php endif ?>

    <section class="panel form-section routing-history-panel">
        <div class="history-heading"><h2>Routing History</h2></div>
        <div class="routing-trail" aria-label="Routing history">
            <div class="routing-trail-item">
                <time datetime="<?= esc($document['date_received'], 'attr') ?>"><?= esc(gmdate('M j, Y · g:i A', strtotime($document['date_received'] . ' UTC'))) ?></time>
                <div class="routing-trail-body">
                    <strong>Received</strong>
                    <span><?= esc($document['initial_section_name']) ?></span>
                    <?php if ($document['remarks']): ?><p class="routing-remark"><strong>Receiving remark:</strong> <?= nl2br(esc($document['remarks'])) ?></p><?php endif ?>
                </div>
            </div>
            <?php foreach ($document['timeline'] as $event): ?>
                <div class="routing-trail-item">
                    <time datetime="<?= esc($event['routed_at'], 'attr') ?>"><?= esc(gmdate('M j, Y · g:i A', strtotime($event['routed_at'] . ' UTC'))) ?></time>
                    <div class="routing-trail-body">
                        <strong><?= esc((int) ($event['is_recall'] ?? 0) === 1 ? 'Recalled' : ($event['action_name'] ?: ((int) $event['is_reassigned'] === 1 ? 'Reassigned' : 'Routed'))) ?></strong>
                        <span><?= esc($event['destination_section_name']) ?></span>
                        <small class="routing-sender">by <?= esc(trim($event['routed_by_first_name'] . ' ' . $event['routed_by_last_name'])) ?></small>
                        <?php if (trim((string) ($event['remarks'] ?? '')) !== ''): ?><p class="routing-remark"><strong>Remark:</strong> <?= nl2br(esc($event['remarks'])) ?></p><?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </section>
</div>

<script>
(function () {
    const engagementPanel = document.querySelector('.engagement-detail[data-document-id]');
    const csrfName = <?= json_encode(csrf_token()) ?>;
    let csrfHash = <?= json_encode(csrf_hash()) ?>;
    const synchronizeCsrf = (nextHash) => {
        if (!nextHash) return;
        csrfHash = nextHash;
        document.querySelectorAll('input[name="' + CSS.escape(csrfName) + '"]').forEach((input) => {
            input.value = nextHash;
        });
    };
    const renderEngagement = (state) => {
        if (!engagementPanel || !state) return;
        const strip = engagementPanel.querySelector('[data-engagement-state]');
        strip.innerHTML = state.active
            ? '<span class="engagement-dot is-active"></span><span><strong>Active work lock</strong><small></small></span>'
            : '<span class="engagement-dot"></span><span><strong>Awaiting confirmation</strong><small>No one is actively handling this document.</small></span>';
        if (state.active) strip.querySelector('small').textContent = (state.confirmed_by_name || 'Assigned user') + ' confirmed this document.';
        const existing = engagementPanel.querySelector('[data-confirm-document]');
        if (!state.active && state.can_confirm && !existing) {
            const button = document.createElement('button'); button.className = 'button button-confirm'; button.type = 'button'; button.dataset.confirmDocument = ''; button.textContent = 'Verify / Confirm'; engagementPanel.appendChild(button);
        } else if ((state.active || !state.can_confirm) && existing) existing.remove();
        engagementPanel.dataset.lockOwner = state.owned_by_actor ? '1' : '0';
        const blocked = state.active && !state.owned_by_actor;
        document.querySelectorAll('.route-form').forEach((form) => { form.dataset.engagementBlocked = blocked ? '1' : '0'; });
        document.querySelectorAll('.assignment-correction-trigger button').forEach((button) => {
            button.disabled = blocked;
            button.title = blocked ? 'Another assigned user currently holds the active work lock.' : '';
        });
        window.refreshRoutingActionFlow?.();
    };
    <?php if ($document['engagement']): ?>renderEngagement(<?= json_encode([
        'active' => true,
        'confirmed_by_name' => trim($document['engagement']['first_name'] . ' ' . $document['engagement']['last_name']),
        'owned_by_actor' => hash_equals((string) $document['engagement']['confirmed_by'], (string) session()->get('auth_user_id')),
        'can_confirm' => $document['can_confirm_engagement'],
    ]) ?>);<?php else: ?>renderEngagement(<?= json_encode(['active' => false, 'can_confirm' => $document['can_confirm_engagement'], 'owned_by_actor' => false]) ?>);<?php endif ?>
    const engagementPost = async (url) => {
        const body = new FormData(); body.append(csrfName, csrfHash);
        const response = await fetch(url, {method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest'}}); const data = await response.json();
        synchronizeCsrf(data.csrf);
        if (!response.ok) throw new Error(data.error || 'Unable to confirm this document.'); return data.engagement;
    };
    engagementPanel?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-confirm-document]'); if (!button) return;
        button.disabled = true; button.textContent = 'Confirming…';
        try { renderEngagement(await engagementPost(engagementPanel.dataset.confirmUrl)); } catch (error) { alert(error.message); button.disabled = false; button.textContent = 'Verify / Confirm'; }
    });
    const refreshEngagement = async () => {
        if (!engagementPanel || document.hidden) return;
        try {
            const url = engagementPanel.dataset.lockOwner === '1' ? engagementPanel.dataset.heartbeatUrl : engagementPanel.dataset.engagementUrl;
            const response = engagementPanel.dataset.lockOwner === '1'
                ? await fetch(url, {method: 'POST', body: (() => { const f = new FormData(); f.append(csrfName, csrfHash); return f; })(), headers: {'X-Requested-With': 'XMLHttpRequest'}})
                : await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (response.ok) { const data = await response.json(); synchronizeCsrf(data.csrf); renderEngagement(data.engagement); }
        } catch (_) {}
    };
    window.setInterval(refreshEngagement, 10000);
    const section = document.getElementById('destination_section');
    const user = document.getElementById('destination_user');
    if (section && user) {
        const refreshUsers = () => {
            for (const option of user.options) {
                if (!option.dataset.section) continue;
                option.hidden = option.dataset.section !== section.value;
                option.disabled = option.hidden;
            }
            const selected = user.options[user.selectedIndex];
            if (selected && selected.disabled) user.value = '';
        };
        section.addEventListener('change', refreshUsers);
        refreshUsers();
    }


    const reassignSection = document.getElementById('reassign_section');
    const reassignUser = document.getElementById('reassign_user');
    if (reassignSection && reassignUser) {
        const refreshReassignUsers = () => {
            for (const option of reassignUser.options) {
                if (!option.dataset.section) continue;
                option.hidden = option.dataset.section !== reassignSection.value;
                option.disabled = option.hidden;
            }
            const selected = reassignUser.options[reassignUser.selectedIndex];
            if (selected && selected.disabled) reassignUser.value = '';
        };
        reassignSection.addEventListener('change', refreshReassignUsers);
        refreshReassignUsers();
    }

    const action = document.getElementById('routing_action');
    const remarks = document.getElementById('routing_remarks');
    const hint = document.getElementById('remarks_hint');
    const evidenceField = document.getElementById('routing_evidence_field');
    const evidence = document.getElementById('routing_evidence');
    const dependentFields = document.querySelector('.routing-dependent-fields');
    const destinationStep = document.querySelector('.routing-destination-step');
    const destinationSection = document.getElementById('destination_section');
    const destinationUser = document.getElementById('destination_user');
    const routeForm = document.querySelector('.route-form');
    const routeSubmit = routeForm?.querySelector('button[type="submit"]');
    if (action && remarks && hint) {
        const refreshRoutingActionFlow = () => {
            const selected = action.options[action.selectedIndex];
            const actionChosen = Boolean(action.value);
            const required = selected && selected.dataset.requiresRemarks === '1';
            const evidenceRequired = selected && selected.dataset.requiresEvidence === '1';
            const terminal = selected && selected.dataset.terminal === '1';
            const engagementBlocked = routeForm?.dataset.engagementBlocked === '1';
            if (dependentFields) dependentFields.disabled = !actionChosen;
            if (routeSubmit) {
                routeSubmit.disabled = !actionChosen || engagementBlocked;
                routeSubmit.title = engagementBlocked
                    ? 'Another assigned user currently holds the active work lock.'
                    : (!actionChosen ? 'Select the action taken first.' : '');
            }
            remarks.required = required;
            hint.textContent = required ? '(required for selected action)' : '(optional)';
            if (destinationStep && destinationSection && destinationUser) {
                destinationStep.hidden = terminal;
                destinationSection.disabled = terminal;
                destinationSection.required = !terminal;
                destinationUser.disabled = terminal;
            }
            if (routeSubmit) routeSubmit.textContent = terminal ? 'Complete and Archive' : 'Route Document';
            if (evidenceField && evidence) {
                evidenceField.hidden = !evidenceRequired;
                evidence.required = evidenceRequired;
                if (!evidenceRequired) evidence.value = '';
            }
        };
        window.refreshRoutingActionFlow = refreshRoutingActionFlow;
        action.addEventListener('change', refreshRoutingActionFlow);
        refreshRoutingActionFlow();
    }

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal());
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
}());
</script>
<?= $this->endSection() ?>
