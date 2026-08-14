<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading">
    <div>
        <div class="eyebrow">DOCUMENT MONITORING</div>
        <h1 class="page-title">Document Monitoring</h1>
        <p class="lead compact">Track documents across sections without changing their assignment.</p>
    </div>
</section>

<form class="panel monitoring-filters" method="get" action="<?= site_url('monitoring') ?>">
    <label class="field filter-search"><span>Search</span><input type="search" name="q" value="<?= esc($filters['q']) ?>" maxlength="150" placeholder="Document no., receiving no., subject, or sender"></label>
    <label class="field"><span>Current Section</span><select name="section"><option value="">All sections</option><?php foreach ($references['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $filters['section'] === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code'] . ' — ' . $section['section_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Responsible Person</span><select name="person"><option value="">All people</option><?php foreach ($references['people'] as $person): ?><option value="<?= esc($person['user_id']) ?>" <?= $filters['person'] === $person['user_id'] ? 'selected' : '' ?>><?= esc($person['last_name'] . ', ' . $person['first_name'] . ' · ' . $person['employee_id']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach ($references['statuses'] as $status): ?><option value="<?= esc($status['status_id']) ?>" <?= $filters['status'] === $status['status_id'] ? 'selected' : '' ?>><?= esc($status['status_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Document Type</span><select name="type"><option value="">All types</option><?php foreach ($references['types'] as $type): ?><option value="<?= esc($type['document_type_id']) ?>" <?= $filters['type'] === $type['document_type_id'] ? 'selected' : '' ?>><?= esc($type['type_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Received From</span><input type="date" name="from" value="<?= esc($filters['from']) ?>"></label>
    <label class="field"><span>Received To</span><input type="date" name="to" value="<?= esc($filters['to']) ?>"></label>
    <div class="monitoring-filter-actions">
        <a class="button" href="<?= site_url('monitoring') ?>">Clear</a>
        <button class="button button-primary" type="submit">Apply filters</button>
    </div>
</form>

<div class="monitoring-result-heading">
    <div><strong><?= number_format(count($documents)) ?></strong> document<?= count($documents) === 1 ? '' : 's' ?> shown</div>
    <small>Newest movement first · maximum 200 results</small>
</div>

<?php if ($documents === []): ?>
    <div class="panel empty-state"><strong>No documents matched.</strong><p>Adjust or clear the monitoring filters.</p></div>
<?php else: ?>
    <div class="monitoring-grid">
        <?php foreach ($documents as $document): ?>
            <article class="panel monitoring-card">
                <div class="monitoring-card-status">
                    <span class="badge <?= (int) $document['is_terminal'] === 1 ? 'badge-muted' : 'badge-info' ?>"><?= esc($document['status_name']) ?></span>
                    <small class="document-number" title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></small>
                </div>
                <div class="monitoring-card-summary">
                    <h2><?= esc($document['subject']) ?></h2>
                    <p><?= esc(mb_strimwidth(trim((string) $document['description']), 0, 180, '...')) ?></p>
                </div>
                <div class="monitoring-assignment" aria-label="Current assignment">
                    <div><span>Assigned section</span><strong><?= esc($document['section_code'] . ' - ' . $document['section_name']) ?></strong></div>
                    <div><span>Assigned personnel</span><strong><?= $document['responsible_first_name'] ? esc($document['responsible_first_name'] . ' ' . $document['responsible_last_name']) : 'Section inbox / unassigned' ?></strong></div>
                </div>
                <div class="inbox-card-actions"><a class="button button-primary" href="<?= site_url('documents/' . $document['document_id']) ?>">View Details</a></div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
