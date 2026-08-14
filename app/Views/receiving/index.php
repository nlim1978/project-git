<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">DOCUMENTS</div>
        <h1 class="page-title">Receiving</h1>
        <p class="lead compact">Incoming documents registered in iDocTrack.</p>
    </div>
    <?php if ($canCreate): ?><a class="button button-primary" href="<?= site_url('receiving/new') ?>">Register document</a><?php endif ?>
</section>

<div class="table-card">
    <?php if ($documents === []): ?>
        <div class="empty-state"><strong>No received documents yet.</strong><p>The first document you register will appear here.</p></div>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Control no.</th><th>Subject / sender</th><th>Type</th><th>Current section</th><th>Status</th><th>Received</th><th class="actions-heading">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><a class="table-link" href="<?= site_url('receiving/' . $document['document_id']) ?>" title="<?= esc($document['document_control_number'], 'attr') ?>"><strong><?= esc(short_control_number($document['document_control_number'])) ?></strong></a><small><?= esc($document['receiving_number']) ?></small></td>
                        <td><strong><?= esc($document['subject']) ?></strong><small><?= esc($document['sender_name']) ?></small></td>
                        <td><?= esc($document['type_name']) ?></td>
                        <td><?= esc($document['section_name']) ?></td>
                        <td><span class="badge badge-info"><?= esc($document['status_name']) ?></span></td>
                        <td><?= esc($document['date_received']) ?> UTC</td>
                        <td class="row-actions"><a class="button button-small" href="<?= site_url('receiving/' . $document['document_id']) ?>">View</a><?php if ($document['can_update']): ?><a class="button button-small" href="<?= site_url('receiving/' . $document['document_id'] . '/edit') ?>">Edit</a><?php endif ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
