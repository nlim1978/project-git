<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading inbox-heading">
    <div>
        <div class="eyebrow">WORK QUEUE MANAGEMENT</div>
        <h1 class="page-title">General Inbox</h1>
        <p class="lead compact">Review and process active documents assigned to you or one of your sections.</p>
    </div>
    <div class="inbox-live-status" aria-live="polite"><span class="inbox-live-dot"></span><span>Live updates</span><strong data-inbox-event-count hidden>0</strong></div>
</section>

<div class="inbox-event-stack" data-inbox-event-stack aria-live="polite" aria-atomic="false"></div>

<?php if ($documents === []): ?>
    <div class="panel empty-state">
        <strong>Your General Inbox is clear.</strong>
        <p>There are no active documents assigned to you or your sections.</p>
    </div>
<?php else: ?>
    <div class="inbox-grid">
        <?php foreach ($documents as $document): ?>
            <article class="panel inbox-card" data-document-id="<?= esc($document['document_id'], 'attr') ?>" data-engagement-url="<?= esc(site_url('documents/' . $document['document_id'] . '/engagement'), 'attr') ?>" data-confirm-url="<?= esc(site_url('documents/' . $document['document_id'] . '/confirm'), 'attr') ?>" data-heartbeat-url="<?= esc(site_url('documents/' . $document['document_id'] . '/heartbeat'), 'attr') ?>">
                <div class="inbox-card-top">
                    <div>
                        <strong class="document-number" title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></strong>
                        <small><?= esc($document['receiving_number']) ?></small>
                    </div>
                    <span class="badge badge-info"><?= esc($document['status_name']) ?></span>
                </div>
                <div class="inbox-document-copy">
                    <span class="document-type-chip"><?= esc($document['type_name']) ?></span>
                    <h2><?= esc($document['subject']) ?></h2>
                    <p class="muted"><?= esc($document['sender_name']) ?></p>
                </div>
                <?php if ($document['can_use_qr']): ?><div class="inbox-qr">
                    <img src="<?= site_url('documents/' . $document['document_id'] . '/qr') ?>" alt="QR code for <?= esc($document['document_control_number'], 'attr') ?>">
                    <div><strong>Secure QR access</strong><small>Scanning opens this record only after sign-in and the same document access check.</small></div>
                </div><?php endif ?>
                <dl class="definition-list inbox-assignment">
                    <div><dt>Current assignment</dt><dd><?= esc($document['section_name']) ?></dd></div>
                    <div><dt>Responsible</dt><dd><?= $document['responsible_first_name'] ? esc($document['responsible_first_name'] . ' ' . $document['responsible_last_name']) : 'Section inbox / unassigned' ?></dd></div>
                </dl>
                <div class="engagement-strip" data-engagement-state>
                    <?php if ($document['engagement']): ?>
                        <span class="engagement-dot is-active"></span><span><strong>Being handled</strong><small><?= esc(trim($document['engagement']['first_name'] . ' ' . $document['engagement']['last_name'])) ?></small></span>
                    <?php else: ?>
                        <span class="engagement-dot"></span><span><strong>Awaiting confirmation</strong><small>No active work lock</small></span>
                    <?php endif ?>
                </div>
                <div class="inbox-card-actions">
                    <?php if ($document['can_confirm_engagement'] && ! $document['engagement']): ?><button class="button button-confirm" type="button" data-confirm-document>Verify / Confirm</button><?php endif ?>
                    <a class="button button-primary" href="<?= site_url('documents/' . $document['document_id']) ?>">View document</a>
                </div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<script>
(function () {
    const csrfName = <?= json_encode(csrf_token()) ?>;
    let csrfHash = <?= json_encode(csrf_hash()) ?>;
    let eventCursor = <?= (int) $eventCursor ?>;
    const eventUrl = <?= json_encode(site_url('inbox/events')) ?>;
    const seenEvents = new Set();
    let unreadEvents = 0;
    const eventStack = document.querySelector('[data-inbox-event-stack]');
    const eventCount = document.querySelector('[data-inbox-event-count]');
    const originalTitle = document.title;
    const updateEventCount = () => {
        if (!eventCount) return;
        eventCount.textContent = String(unreadEvents);
        eventCount.hidden = unreadEvents === 0;
        document.title = unreadEvents > 0 ? '(' + unreadEvents + ') ' + originalTitle : originalTitle;
    };
    const showInboxEvent = (item) => {
        if (!eventStack || !item || seenEvents.has(item.event_key)) return;
        seenEvents.add(item.event_key); unreadEvents += 1; updateEventCount();
        const notice = document.createElement('article');
        notice.className = 'inbox-event-message'; notice.dataset.eventKey = item.event_key;
        const copy = document.createElement('div');
        const eyebrow = document.createElement('small'); eyebrow.textContent = item.message || 'New General Inbox transaction';
        const title = document.createElement('strong'); title.textContent = (item.control_number || 'Document') + ' · ' + (item.document_type || 'Document');
        const subject = document.createElement('span'); subject.textContent = item.subject || 'Untitled document';
        const meta = document.createElement('small'); meta.textContent = 'Assigned to ' + (item.section || 'your section') + (item.remarks ? ' · Remarks: ' + item.remarks : '');
        copy.append(eyebrow, title, subject, meta);
        const actions = document.createElement('div'); actions.className = 'inbox-event-actions';
        const open = document.createElement('a'); open.className = 'button button-primary'; open.href = <?= json_encode(site_url('documents')) ?> + '/' + encodeURIComponent(item.document_id); open.textContent = 'View';
        const dismiss = document.createElement('button'); dismiss.type = 'button'; dismiss.className = 'icon-button'; dismiss.setAttribute('aria-label', 'Dismiss notification'); dismiss.textContent = '×';
        dismiss.addEventListener('click', () => { notice.remove(); unreadEvents = Math.max(0, unreadEvents - 1); updateEventCount(); });
        actions.append(open, dismiss); notice.append(copy, actions); eventStack.prepend(notice);
        while (eventStack.children.length > 5) eventStack.lastElementChild.remove();
    };
    const render = (card, state) => {
        const strip = card.querySelector('[data-engagement-state]');
        if (!strip || !state) return;
        strip.innerHTML = state.active
            ? '<span class="engagement-dot is-active"></span><span><strong>Being handled</strong><small></small></span>'
            : '<span class="engagement-dot"></span><span><strong>Awaiting confirmation</strong><small>No active work lock</small></span>';
        if (state.active) strip.querySelector('small').textContent = state.confirmed_by_name || 'Assigned user';
        card.dataset.lockOwner = state.owned_by_actor ? '1' : '0';
        const existing = card.querySelector('[data-confirm-document]');
        if (!state.active && state.can_confirm && !existing) {
            const button = document.createElement('button');
            button.className = 'button button-confirm'; button.type = 'button'; button.dataset.confirmDocument = ''; button.textContent = 'Verify / Confirm';
            card.querySelector('.inbox-card-actions')?.prepend(button);
        } else if ((state.active || !state.can_confirm) && existing) existing.remove();
    };
    const post = async (url) => {
        const body = new FormData(); body.append(csrfName, csrfHash);
        const response = await fetch(url, {method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const data = await response.json();
        if (data.csrf) csrfHash = data.csrf;
        if (!response.ok) throw new Error(data.error || 'The document could not be confirmed.');
        return data.engagement;
    };
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-confirm-document]'); if (!button) return;
        const card = button.closest('[data-document-id]'); button.disabled = true; button.textContent = 'Confirming…';
        try { render(card, await post(card.dataset.confirmUrl)); } catch (error) { alert(error.message); button.disabled = false; button.textContent = 'Verify / Confirm'; }
    });
    const refresh = async () => {
        for (const card of document.querySelectorAll('[data-document-id]')) {
            try {
                const ownsLock = card.dataset.lockOwner === '1';
                const options = {headers: {'X-Requested-With': 'XMLHttpRequest'}};
                if (ownsLock) { const body = new FormData(); body.append(csrfName, csrfHash); options.method = 'POST'; options.body = body; }
                const response = await fetch(ownsLock ? card.dataset.heartbeatUrl : card.dataset.engagementUrl, options);
                if (response.ok) { const data = await response.json(); if (data.csrf) csrfHash = data.csrf; render(card, data.engagement); }
            } catch (_) {}
        }
    };
    window.setInterval(refresh, 10000);
    const pollInboxEvents = async () => {
        if (document.visibilityState === 'hidden') return;
        try {
            const response = await fetch(eventUrl + '?since=' + encodeURIComponent(eventCursor), {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            if (Array.isArray(data.events)) data.events.forEach(showInboxEvent);
            if (Number.isInteger(data.cursor)) eventCursor = Math.max(eventCursor, data.cursor);
        } catch (_) {}
    };
    window.setInterval(pollInboxEvents, 5000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') pollInboxEvents(); });
}());
</script>
<?= $this->endSection() ?>
