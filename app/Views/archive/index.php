<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading archive-heading">
    <div><div class="eyebrow">RECORDS CATALOG</div><h1 class="page-title">Document Archive</h1><p class="lead compact">Retrieve completed Filed and Released documents without returning them to active processing.</p></div>
    <div class="archive-summary" aria-label="Archive result summary">
        <div><strong><?= number_format(count($documents)) ?></strong><span>Results</span></div>
        <div><strong><?= number_format($filedCount) ?></strong><span>Filed</span></div>
        <div><strong><?= number_format($releasedCount) ?></strong><span>Released</span></div>
    </div>
</section>

<nav class="archive-tabs" aria-label="Archive status filter">
    <a class="<?= $filters['state'] === '' ? 'active' : '' ?>" href="<?= site_url('archive') ?>">All archived</a>
    <a class="<?= $filters['state'] === 'Filed' ? 'active' : '' ?>" href="<?= site_url('archive') . '?state=Filed' ?>">Filed</a>
    <a class="<?= $filters['state'] === 'Released' ? 'active' : '' ?>" href="<?= site_url('archive') . '?state=Released' ?>">Released</a>
</nav>

<form class="panel archive-filters" method="get" action="<?= site_url('archive') ?>">
    <label class="field archive-search"><span>Search archive</span><input type="search" name="q" value="<?= esc($filters['q']) ?>" maxlength="150" placeholder="Reference, subject, sender, or organization"></label>
    <label class="field"><span>Record state</span><select name="state"><option value="">Filed and Released</option><option value="Filed" <?= $filters['state'] === 'Filed' ? 'selected' : '' ?>>Filed</option><option value="Released" <?= $filters['state'] === 'Released' ? 'selected' : '' ?>>Released</option></select></label>
    <label class="field"><span>Final section</span><select name="section"><option value="">All sections</option><?php foreach ($references['sections'] as $section): ?><option value="<?= esc($section['section_id']) ?>" <?= $filters['section'] === $section['section_id'] ? 'selected' : '' ?>><?= esc($section['section_code'] . ' — ' . $section['section_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Document type</span><select name="type"><option value="">All types</option><?php foreach ($references['types'] as $type): ?><option value="<?= esc($type['document_type_id']) ?>" <?= $filters['type'] === $type['document_type_id'] ? 'selected' : '' ?>><?= esc($type['type_name']) ?></option><?php endforeach ?></select></label>
    <label class="field"><span>Completed from</span><input type="date" name="from" value="<?= esc($filters['from']) ?>"></label>
    <label class="field"><span>Completed to</span><input type="date" name="to" value="<?= esc($filters['to']) ?>"></label>
    <div class="archive-filter-actions"><a class="button" href="<?= site_url('archive') ?>">Clear</a><button class="button button-primary" type="submit">Search archive</button></div>
</form>

<div class="archive-result-note"><strong><?= number_format(count($documents)) ?></strong> archived document<?= count($documents) === 1 ? '' : 's' ?> shown <span>Newest completion first · maximum 200 results</span></div>

<?php if ($documents === []): ?>
    <div class="panel empty-state"><strong>No archived documents matched.</strong><p>Adjust the filters or clear the search to view available records.</p></div>
<?php else: ?>
    <div class="archive-catalog">
        <?php foreach ($documents as $document): ?>
            <article class="panel archive-record">
                <div class="archive-record-head">
                    <div><span class="badge <?= $document['archive_state'] === 'Released' ? 'badge-success' : 'badge-muted' ?>"><?= esc($document['archive_state']) ?></span><small><?= esc($document['type_name']) ?></small></div>
                    <time datetime="<?= esc($document['archived_at'], 'attr') ?>">Completed <?= esc(gmdate('M j, Y', strtotime($document['archived_at'] . ' UTC'))) ?></time>
                </div>
                <div class="archive-record-title"><h2><?= esc($document['subject']) ?></h2><code title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></code></div>
                <dl class="archive-record-meta">
                    <div><dt>Sender</dt><dd><?= esc($document['sender_name']) ?><?= $document['sender_organization'] ? '<small>' . esc($document['sender_organization']) . '</small>' : '' ?></dd></div>
                    <div><dt>Final section</dt><dd><?= esc($document['section_code'] . ' — ' . $document['section_name']) ?></dd></div>
                    <div><dt>Tracking reference</dt><dd><?= esc($document['client_tracking_reference']) ?></dd></div>
                </dl>
                <div class="archive-record-actions"><span>Read-only archived record</span><a class="button button-primary" href="<?= site_url('documents/' . $document['document_id']) ?>">View archived document</a></div>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
