<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | iDocTrack</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;color:#0f2747;background:#eef3f8}.sheet{width:min(520px,calc(100% - 32px));margin:40px auto;padding:38px;text-align:center;background:#fff;border:1px solid #d8e1eb;border-radius:18px}.brand{font-size:13px;font-weight:800;letter-spacing:.12em;color:#195ea8}.sheet h1{margin:12px 0 4px;font-size:24px}.subject{margin:0 0 22px;color:#607086}.qr{width:min(320px,100%);aspect-ratio:1;margin:0 auto 18px}.qr img{width:100%;height:100%;display:block}.note{font-size:13px;line-height:1.5;color:#607086}.actions{margin-top:24px}.actions button{border:0;border-radius:9px;background:#174f91;color:#fff;padding:11px 18px;font-weight:700;cursor:pointer}@media print{body{background:#fff}.sheet{width:100%;margin:0;border:0;border-radius:0}.actions{display:none}}
    </style>
</head>
<body>
<main class="sheet">
    <div class="brand">iDocTrack · DOCUMENT TRACKING &amp; ROUTING SYSTEM</div>
    <h1><?= esc($document['document_control_number']) ?></h1>
    <p class="subject"><?= esc($document['subject']) ?></p>
    <div class="qr"><img src="<?= site_url('documents/' . $document['document_id'] . '/qr') ?>" alt="Document QR code"></div>
    <p class="note">Scan to open the authenticated iDocTrack document record. A valid account and document access permission are required.</p>
    <div class="actions"><button type="button" onclick="window.print()">Print QR Code</button></div>
</main>
</body>
</html>
