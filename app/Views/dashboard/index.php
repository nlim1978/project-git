<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$agingTotal = array_sum($aging);
$topTypeMax = $topTypes === [] ? 0 : max(array_column($topTypes, 'document_count'));
$scopeName = $department === null ? 'No department assigned' : $department['department_name'];
$scopeLabel = $isSectionScoped ? 'SECTION OVERVIEW' : 'DEPARTMENT OVERVIEW';
$workloadLabel = $isSectionScoped ? 'Current section workload' : 'Current department workload';
?>

<section class="page-heading dashboard-heading dashboard-overview-heading">
    <div>
        <div class="eyebrow"><?= esc($scopeLabel) ?></div>
        <h1 class="page-title">Dashboard</h1>
        <p class="lead compact"><?= esc($scopeName) ?> · Document activity and processing overview.</p>
    </div>
    <form class="dashboard-period" method="get" action="<?= site_url('dashboard') ?>">
        <label for="dashboard-period">Period</label>
        <select id="dashboard-period" name="period" onchange="this.form.submit()">
            <option value="this_month" <?= $period['key'] === 'this_month' ? 'selected' : '' ?>>This month</option>
            <option value="last_30_days" <?= $period['key'] === 'last_30_days' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="last_90_days" <?= $period['key'] === 'last_90_days' ? 'selected' : '' ?>>Last 90 days</option>
        </select>
    </form>
</section>

<?php if ($department === null): ?>
    <section class="panel dashboard-empty-scope">
        <h2>Department scope unavailable</h2>
        <p class="muted">Your account needs an active primary section before department analytics can be shown.</p>
    </section>
<?php else: ?>
    <section class="dashboard-stats dashboard-stats-four" aria-label="Department document summary">
        <article class="dashboard-stat dashboard-stat-attention">
            <span>Needs attention</span>
            <strong><?= number_format($summary['needs_attention']) ?></strong>
            <small><?= $attentionDays ?>+ days without movement</small>
        </article>
        <article class="dashboard-stat">
            <span>Received</span>
            <strong><?= number_format($summary['received']) ?></strong>
            <small><?= esc($period['label']) ?></small>
        </article>
        <article class="dashboard-stat">
            <span>In progress</span>
            <strong><?= number_format($summary['in_progress']) ?></strong>
            <small><?= esc($workloadLabel) ?></small>
        </article>
        <article class="dashboard-stat dashboard-stat-complete">
            <span>Completed</span>
            <strong><?= number_format($summary['completed']) ?></strong>
            <small><?= esc($period['label']) ?></small>
        </article>
    </section>

    <div class="dashboard-analysis-grid">
        <section class="panel dashboard-analysis-card">
            <div class="dashboard-section-head dashboard-section-head-plain">
                <div><h2>Document aging</h2><p>Open documents by time since their last movement.</p></div>
            </div>
            <div class="dashboard-aging" aria-label="Document aging distribution">
                <?php
                $agingRows = [
                    ['0–2 days', $aging['fresh'], 'fresh'],
                    ['3–4 days', $aging['watch'], 'watch'],
                    ['5–9 days', $aging['attention'], 'attention'],
                    ['10+ days', $aging['critical'], 'critical'],
                ];
                ?>
                <?php foreach ($agingRows as [$label, $count, $tone]): ?>
                    <?php $width = $agingTotal > 0 ? max(3, (int) round(($count / $agingTotal) * 100)) : 0; ?>
                    <div class="dashboard-bar-row">
                        <span><?= esc($label) ?></span>
                        <div class="dashboard-bar-track"><i class="dashboard-bar dashboard-bar-<?= esc($tone, 'attr') ?>" style="width: <?= $width ?>%"></i></div>
                        <strong><?= number_format($count) ?></strong>
                    </div>
                <?php endforeach ?>
            </div>
        </section>

        <section class="panel dashboard-analysis-card">
            <div class="dashboard-section-head dashboard-section-head-plain">
                <div><h2>Top document types</h2><p>Most received · <?= esc($period['label']) ?></p></div>
            </div>
            <?php if ($topTypes === []): ?>
                <div class="dashboard-mini-empty">No documents received in this period.</div>
            <?php else: ?>
                <div class="dashboard-types">
                    <?php foreach ($topTypes as $index => $type): ?>
                        <?php $width = $topTypeMax > 0 ? max(8, (int) round(($type['document_count'] / $topTypeMax) * 100)) : 0; ?>
                        <div class="dashboard-type-row">
                            <div><span><?= $index + 1 ?></span><strong><?= esc($type['type_name']) ?></strong><b><?= number_format($type['document_count']) ?></b></div>
                            <div class="dashboard-type-track"><i style="width: <?= $width ?>%"></i></div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>
    </div>

    <div class="dashboard-main-grid dashboard-department-grid">
        <section class="panel dashboard-work-panel dashboard-attention-panel">
            <div class="dashboard-section-head">
                <div><h2>Documents needing attention</h2><p>Oldest stalled documents in <?= esc($department['department_code']) ?>.</p></div>
                <?php if ($canViewMonitoring): ?><a href="<?= site_url('monitoring') ?>">View monitoring</a><?php endif ?>
            </div>

            <?php if ($attentionDocuments === []): ?>
                <div class="dashboard-clear-state"><strong>No aging documents.</strong><span>Nothing has been idle for <?= $attentionDays ?> days or more.</span></div>
            <?php else: ?>
                <div class="dashboard-attention-list">
                    <?php foreach ($attentionDocuments as $document): ?>
                        <a class="dashboard-attention-item" href="<?= site_url('documents/' . $document['document_id']) ?>">
                            <div class="dashboard-attention-subject">
                                <strong><?= esc($document['subject']) ?></strong>
                                <small title="<?= esc($document['document_control_number'], 'attr') ?>"><?= esc(short_control_number($document['document_control_number'])) ?></small>
                            </div>
                            <span class="dashboard-age <?= (int) $document['age_days'] >= 10 ? 'is-critical' : '' ?>"><?= (int) $document['age_days'] ?>d</span>
                            <span class="dashboard-attention-section"><?= esc($document['section_name']) ?></span>
                            <span class="dashboard-attention-status"><?= esc($document['status_name']) ?></span>
                            <span class="dashboard-work-arrow" aria-hidden="true">→</span>
                        </a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </section>

        <aside class="panel dashboard-actions-panel">
            <div class="dashboard-section-head"><div><h2>Quick actions</h2><p>Available to your account.</p></div></div>
            <nav class="dashboard-actions" aria-label="Dashboard quick actions">
                <?php if ($canCreateReceiving): ?><a href="<?= site_url('receiving/new') ?>"><strong>Register document</strong><span>Record new incoming document</span></a><?php endif ?>
                <?php if ($canViewInbox): ?><a href="<?= site_url('inbox') ?>"><strong>General Inbox</strong><span>Process your assignments</span></a><?php endif ?>
                <?php if ($canViewMonitoring): ?><a href="<?= site_url('monitoring') ?>"><strong>Monitor documents</strong><span>Search and track documents</span></a><?php endif ?>
                <?php if ($canViewReports): ?><a href="<?= site_url('reports') ?>"><strong>Reports</strong><span>Open document reporting</span></a><?php endif ?>
                <?php if ($canManageUsers): ?><a href="<?= site_url('admin/users') ?>"><strong>User management</strong><span>Manage iDocTrack accounts</span></a><?php endif ?>
            </nav>
        </aside>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
