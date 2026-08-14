<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102f63">
    <title><?= esc($title ?? 'iDocTrack') ?> | iDocTrack</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" class="public-content" tabindex="-1">
        <?php if (session('success')): ?><div class="public-alert-wrap"><div class="alert alert-success" role="status"><?= esc(session('success')) ?></div></div><?php endif ?>
        <?php if (session('error')): ?><div class="public-alert-wrap"><div class="alert alert-error" role="alert"><?= esc(session('error')) ?></div></div><?php endif ?>
        <?= $this->renderSection('content') ?>
    </main>
</body>
</html>
