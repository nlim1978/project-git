<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | iDocTrack</title>
    <style>
        body{font:12px/1.45 Arial,sans-serif;color:#182536;margin:24px}h1{margin:0 0 4px;font-size:22px}.meta{color:#5f6e7d;margin-bottom:16px}.summary{display:flex;gap:8px;margin:14px 0}.summary div{border:1px solid #ccd6df;padding:8px 12px;border-radius:6px}.summary strong{display:block;font-size:18px}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #d4dde5;text-align:left;vertical-align:top}th{background:#eef3f7;font-size:10px;text-transform:uppercase}.print-actions{margin-bottom:16px}.print-actions button{padding:8px 12px}@page{size:landscape;margin:10mm}@media print{body{margin:0}.print-actions{display:none}thead{display:table-header-group}tr{break-inside:avoid}}
    </style>
</head>
<body>
<div class="print-actions"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
<h1><?= esc($filters['report_type']) ?></h1>
<div class="meta">iDocTrack · Date range: <?= esc($filters['from'] ?: 'Any') ?> to <?= esc($filters['to'] ?: 'Any') ?> · Generated <?= esc(gmdate('Y-m-d H:i:s')) ?> UTC</div>
<div class="summary">
    <div><span>Total</span><strong><?= number_format($summary['total']) ?></strong></div>
    <div><span>Received</span><strong><?= number_format($summary['received']) ?></strong></div>
    <div><span>In Progress</span><strong><?= number_format($summary['in_progress']) ?></strong></div>
    <div><span>Completed</span><strong><?= number_format($summary['completed']) ?></strong></div>
</div>
<table>
    <thead><tr><th>Document Number</th><th>Receiving Number</th><th>Date Received</th><th>Type</th><th>Subject</th><th>Sender</th><th>Current Section</th><th>Responsible</th><th>Status</th><th>Latest Action</th><th>Last Updated</th></tr></thead>
    <tbody>
    <?php foreach ($records as $record): ?>
        <tr>
            <td><?= esc($record['document_control_number']) ?></td><td><?= esc($record['receiving_number']) ?></td><td><?= esc($record['date_received']) ?></td><td><?= esc($record['type_name']) ?></td><td><?= esc($record['subject']) ?></td><td><?= esc($record['sender_name']) ?></td><td><?= esc($record['section_name']) ?></td><td><?= $record['responsible_first_name'] ? esc($record['responsible_first_name'] . ' ' . $record['responsible_last_name']) : 'Section inbox / unassigned' ?></td><td><?= esc($record['status_name']) ?></td><td><?= esc($record['latest_action']) ?></td><td><?= esc($record['updated_at']) ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</body>
</html>
