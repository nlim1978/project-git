<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<div class="tracking-page tracking-catalog">
    <header class="catalog-header">
        <a class="catalog-brand" href="<?= site_url('track') ?>" aria-label="iDocTrack client tracking home">
            <span class="catalog-mark" aria-hidden="true">IDT</span>
            <span><strong>iDocTrack</strong><small>Document Tracking and Routing System</small></span>
        </a>
        <?php if (session()->has('auth_user_id')): ?>
            <a class="catalog-staff-link" href="<?= site_url('dashboard') ?>">Staff workspace</a>
        <?php else: ?>
            <a class="catalog-staff-link" href="<?= site_url('login') ?>">Staff sign in</a>
        <?php endif ?>
    </header>

    <div class="catalog-main">
        <section class="catalog-hero" aria-labelledby="tracking-title">
            <div class="catalog-introduction">
                <div class="eyebrow">CLIENT SERVICE</div>
                <h1 id="tracking-title">Track your document</h1>
                <p>Enter the tracking reference provided by the receiving office, or scan the QR code you received, to check your document’s current processing status.</p>
                <p class="catalog-assurance"><span aria-hidden="true">✓</span> Secure, limited status information for document proponents.</p>
            </div>

            <form id="trackingForm" class="tracking-search catalog-search" action="<?= site_url('track/status') ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="eyebrow">STATUS LOOKUP</div>
                <label for="trackingToken">Tracking reference</label>
                <input id="trackingToken" name="tracking_token" type="text" maxlength="71" autocomplete="off" spellcheck="false" placeholder="TRK-0226-0001" aria-describedby="trackingReferenceHelp" required>
                <button class="button button-primary button-block" type="submit">Track document</button>
                <small id="trackingReferenceHelp">Example: TRK-0226-0001. You may type the reference with or without spaces and hyphens.</small>
                <details class="legacy-reference-help"><summary>Using an older tracking reference?</summary><p>Long references issued before the current format are still accepted.</p></details>
            </form>
        </section>

        <div id="trackingNotice" class="alert alert-error tracking-notice" role="alert" hidden></div>

    <section id="trackingResult" class="tracking-result" hidden aria-live="polite" tabindex="-1">
        <div class="tracking-status-card panel">
            <div>
                <div class="eyebrow">CURRENT STATUS</div>
                <h2 id="trackStatus">—</h2>
                <p id="trackSection" class="tracking-section">—</p>
            </div>
            <span class="tracking-live"><i></i>Live status</span>
        </div>

        <div class="tracking-grid">
            <section class="panel tracking-summary">
                <div class="detail-header"><h2>Document summary</h2><span id="trackType" class="badge badge-info">—</span></div>
                <dl class="definition-grid">
                    <div><dt>Reference</dt><dd id="trackReference">—</dd></div>
                    <div><dt>Date received</dt><dd id="trackReceived">—</dd></div>
                    <div class="tracking-subject"><dt>Subject</dt><dd id="trackSubject">—</dd></div>
                    <div><dt>Last activity</dt><dd id="trackLastActivity">—</dd></div>
                </dl>
                <p class="tracking-privacy-note">Only client-safe status information is displayed. Internal notes, personnel activity, attachments, and audit details remain private.</p>
            </section>

            <section class="panel tracking-timeline-panel">
                <div class="detail-header"><h2>Progress</h2><span class="muted">Automatically refreshes</span></div>
                <ol id="trackTimeline" class="client-timeline"></ol>
            </section>
        </div>
    </section>

        <section class="catalog-section" aria-labelledby="tracking-process-title">
            <div class="catalog-section-heading"><div class="eyebrow">HOW IT WORKS</div><h2 id="tracking-process-title">How document tracking works</h2></div>
            <div class="catalog-process-grid">
                <article><span>1</span><h3>Submit your document</h3><p>Submit the document to the appropriate receiving office.</p></article>
                <article><span>2</span><h3>Receive tracking details</h3><p>After registration, receive a tracking reference and QR code by email or directly from receiving personnel.</p></article>
                <article><span>3</span><h3>Check the status</h3><p>Use the reference or QR code to view the latest client-accessible status.</p></article>
            </div>
        </section>

        <section class="catalog-section catalog-services" aria-labelledby="client-services-title">
            <div class="catalog-section-heading"><div class="eyebrow">CLIENT SERVICES</div><h2 id="client-services-title">Designed for convenient document follow-up</h2></div>
            <div class="catalog-service-grid">
                <article><span aria-hidden="true">◎</span><div><h3>Easy status tracking</h3><p>Check the current document status without contacting the receiving office for routine updates.</p></div></article>
                <article><span aria-hidden="true">▦</span><div><h3>Email and QR access</h3><p>Use tracking details sent by email when available, or those provided directly by receiving personnel.</p></div></article>
                <article><span aria-hidden="true">◆</span><div><h3>Secure information</h3><p>Only limited document status is displayed. Internal records and personnel activity remain protected.</p></div></article>
            </div>
        </section>

        <section class="catalog-support" aria-label="Tracking help and privacy information">
            <div><h2>Cannot find your reference?</h2><p>Check the email sent after registration or contact the receiving office where you submitted your document.</p></div>
            <div><h2>Privacy notice</h2><p>The portal does not display internal remarks, attachments, personnel activity, audit records, or administrative information.</p></div>
        </section>
    </div>

    <footer class="catalog-footer">
        <p><strong>iDocTrack</strong> provides secure and convenient document status tracking for document proponents and authorized office personnel.</p>
        <span>Client Tracking Portal</span>
    </footer>
</div>

<script>
(function () {
    const form = document.getElementById('trackingForm');
    const input = document.getElementById('trackingToken');
    const result = document.getElementById('trackingResult');
    const notice = document.getElementById('trackingNotice');
    const submit = form.querySelector('button[type="submit"]');
    const csrfName = <?= json_encode(csrf_token()) ?>;
    let csrfHash = <?= json_encode(csrf_hash()) ?>;
    let activeToken = '';
    let refreshTimer = null;

    const setText = (id, value) => { document.getElementById(id).textContent = value || '—'; };
    const localDate = (value) => {
        if (!value) return '—';
        const iso = value.includes('T') ? value : value.replace(' ', 'T') + 'Z';
        const date = new Date(iso);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString([], {dateStyle: 'medium', timeStyle: 'short'});
    };
    const render = (doc) => {
        setText('trackStatus', doc.status);
        setText('trackSection', doc.current_section ? 'Currently with ' + doc.current_section : 'In processing');
        setText('trackType', doc.document_type);
        setText('trackReference', doc.reference);
        setText('trackReceived', localDate(doc.date_received));
        setText('trackSubject', doc.subject);
        setText('trackLastActivity', localDate(doc.last_activity));
        const timeline = document.getElementById('trackTimeline');
        timeline.replaceChildren();
        (doc.timeline || []).forEach((event, index, events) => {
            const item = document.createElement('li');
            if (index === events.length - 1) item.classList.add('is-current');
            const marker = document.createElement('span'); marker.className = 'client-timeline-marker';
            const body = document.createElement('div');
            const label = document.createElement('strong'); label.textContent = event.label;
            const detail = document.createElement('p'); detail.textContent = event.detail;
            const time = document.createElement('time'); time.textContent = localDate(event.at);
            body.append(label, detail, time); item.append(marker, body); timeline.append(item);
        });
        notice.hidden = true;
        result.hidden = false;
    };
    const lookup = async (refresh) => {
        if (!activeToken) return;
        const body = new FormData();
        body.append(csrfName, csrfHash);
        body.append('tracking_token', activeToken);
        if (refresh) body.append('refresh', '1');
        try {
            const response = await fetch(form.action, {method: 'POST', body, headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
            const data = await response.json();
            if (data.csrf) csrfHash = data.csrf;
            const hiddenCsrf = form.querySelector('input[name="' + csrfName + '"]');
            if (hiddenCsrf) hiddenCsrf.value = csrfHash;
            if (!response.ok) throw new Error(data.error || 'Unable to retrieve tracking status.');
            render(data.document);
            if (!refresh) {
                result.focus({preventScroll: true});
                result.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        } catch (error) {
            if (!refresh) result.hidden = true;
            notice.textContent = error.message || 'Unable to retrieve tracking status.';
            notice.hidden = false;
        }
    };
    const start = async (token) => {
        activeToken = String(token || '').trim();
        if (!activeToken) return;
        input.value = activeToken;
        submit.disabled = true; submit.textContent = 'Checking…';
        await lookup(false);
        submit.disabled = false; submit.textContent = 'Track document';
        if (refreshTimer) window.clearInterval(refreshTimer);
        refreshTimer = window.setInterval(() => { if (!document.hidden) lookup(true); }, 10000);
    };
    input.addEventListener('input', () => { input.value = input.value.toUpperCase(); });
    input.addEventListener('blur', () => {
        const compact = input.value.toUpperCase().replace(/[\s-]+/g, '').replace(/^TRK/, '');
        if (/^\d{8}$/.test(compact)) input.value = 'TRK-' + compact.slice(0, 4) + '-' + compact.slice(4);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!input.value.trim()) {
            notice.textContent = 'Enter your tracking reference to continue.';
            notice.hidden = false;
            input.focus();
            return;
        }
        start(input.value);
    });
    if (location.hash.length > 1) {
        const fromQr = decodeURIComponent(location.hash.substring(1));
        history.replaceState(null, '', location.pathname + location.search);
        start(fromQr);
    }
}());
</script>
<?= $this->endSection() ?>
