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
<?php if (session()->has('auth_user_id')): ?>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php
    $nav = $navigation ?? [];
    $path = trim(uri_string(), '/');
    $active = static fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/');
    $documentsActive = $active('documents');
    $documentContext = (string) ($documentContext ?? 'inbox');
    ?>
    <div class="app-shell">
        <header class="topbar">
            <div class="topbar-brand-wrap">
                <button id="sidebarToggle" class="icon-button mobile-menu" type="button" aria-label="Open navigation" aria-controls="appSidebar" aria-expanded="false">☰</button>
                <a class="brand" href="<?= site_url('dashboard') ?>" aria-label="iDocTrack dashboard">
                    <span class="brand-mark" aria-hidden="true">IDT</span>
                    <span class="brand-copy"><strong>iDocTrack</strong><small>Document Tracking &amp; Routing Workspace</small></span>
                </a>
            </div>
            <div class="topbar-account">
                <a class="button button-topbar client-portal-link" href="<?= site_url('track') ?>">
                    <span aria-hidden="true">↗</span>
                    <span class="client-portal-full">Client portal</span>
                    <span class="client-portal-short">Portal</span>
                </a>
                <div class="account-copy"><strong><?= esc(session('auth_name')) ?></strong><small><?= esc((string) ($nav['roleLabel'] ?? 'Authorized User')) ?></small></div>
                <form class="topbar-signout" action="<?= site_url('logout') ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button-topbar topbar-signout-button">Sign out</button>
                </form>
            </div>
        </header>

        <aside id="appSidebar" class="sidebar" aria-label="Primary navigation">
            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Operations</div>
                <a class="nav-link <?= $active('dashboard') ? 'active' : '' ?>" href="<?= site_url('dashboard') ?>"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
                <?php if (! empty($nav['receiving'])): ?><a class="nav-link <?= $active('receiving') ? 'active' : '' ?>" href="<?= site_url('receiving') ?>"><span class="nav-icon">↓</span><span>Receiving of Documents</span></a><?php endif ?>
                <?php if (! empty($nav['inbox'])): ?><a class="nav-link <?= ($active('inbox') || ($documentsActive && $documentContext !== 'archive')) ? 'active' : '' ?>" href="<?= site_url('inbox') ?>"><span class="nav-icon">▣</span><span>General Inbox</span></a><?php endif ?>
                <?php if (! empty($nav['monitoring'])): ?><a class="nav-link <?= $active('monitoring') ? 'active' : '' ?>" href="<?= site_url('monitoring') ?>"><span class="nav-icon">◎</span><span>Document Monitoring</span></a><?php endif ?>
                <?php if (! empty($nav['archive'])): ?><a class="nav-link <?= ($active('archive') || ($documentsActive && $documentContext === 'archive')) ? 'active' : '' ?>" href="<?= site_url('archive') ?>"><span class="nav-icon">▥</span><span>Document Archive</span></a><?php endif ?>
                <?php if (! empty($nav['reports'])): ?><a class="nav-link <?= $active('reports') ? 'active' : '' ?>" href="<?= site_url('reports') ?>"><span class="nav-icon">▤</span><span>Reports</span></a><?php endif ?>

                <?php if (! empty($nav['administration'])): ?>
                    <details class="nav-group" open>
                        <summary><span><span class="nav-icon">⚙</span>Administration</span><span class="nav-chevron">⌄</span></summary>
                        <div class="subnav">
                            <?php if (! empty($nav['users'])): ?><a class="nav-link <?= $active('admin/users') ? 'active' : '' ?>" href="<?= site_url('admin/users') ?>">User Management</a><?php endif ?>
                            <?php if (! empty($nav['roles'])): ?><a class="nav-link <?= $active('admin/roles') ? 'active' : '' ?>" href="<?= site_url('admin/roles') ?>">Roles &amp; Permissions</a><?php endif ?>
                            <?php if (! empty($nav['organization'])): ?><a class="nav-link <?= $active('admin/organization') ? 'active' : '' ?>" href="<?= site_url('admin/organization') ?>">Organization Structure</a><?php endif ?>
                            <?php if (! empty($nav['documentTypes'])): ?><a class="nav-link <?= $active('admin/document-types') ? 'active' : '' ?>" href="<?= site_url('admin/document-types') ?>">Document Types</a><?php endif ?>
                            <?php if (! empty($nav['actions'])): ?><a class="nav-link <?= $active('admin/routing-actions') ? 'active' : '' ?>" href="<?= site_url('admin/routing-actions') ?>">Routing Actions</a><?php endif ?>
                            <?php if (! empty($nav['email'])): ?><a class="nav-link <?= $active('admin/email-settings') ? 'active' : '' ?>" href="<?= site_url('admin/email-settings') ?>">Email Configuration</a><?php endif ?>
                            <?php if (! empty($nav['telegram'])): ?><a class="nav-link <?= $active('admin/telegram-settings') ? 'active' : '' ?>" href="<?= site_url('admin/telegram-settings') ?>">Telegram Configuration</a><?php endif ?>
                            <?php if (! empty($nav['audit'])): ?><a class="nav-link <?= $active('admin/audit') ? 'active' : '' ?>" href="<?= site_url('admin/audit') ?>">Audit Log</a><?php endif ?>
                        </div>
                    </details>
                <?php endif ?>
            </nav>
            <div class="sidebar-footer"><span class="sidebar-status-dot"></span><span>Authorized system workspace</span></div>
        </aside>

        <button id="sidebarOverlay" class="sidebar-overlay" type="button" aria-label="Close navigation"></button>

        <main id="main-content" class="workspace" tabindex="-1">
            <div class="workspace-head">
                <div><span class="workspace-kicker">iDocTrack Workspace</span><strong><?= esc($title ?? 'Dashboard') ?></strong></div>
                <span class="workspace-context">Document Tracking &amp; Routing</span>
            </div>
            <div class="content-wrap">
                <?php if (session('success')): ?><div class="alert alert-success" role="status"><?= esc(session('success')) ?></div><?php endif ?>
                <?php if (session('error')): ?><div class="alert alert-error" role="alert"><?= esc(session('error')) ?></div><?php endif ?>
                <?= $this->renderSection('content') ?>
            </div>
        </main>
    </div>

    <script>
    (function () {
        const sidebar = document.getElementById('appSidebar');
        const toggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !toggle || !overlay) return;
        const close = () => { sidebar.classList.remove('open'); overlay.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); };
        const open = () => { sidebar.classList.add('open'); overlay.classList.add('open'); toggle.setAttribute('aria-expanded', 'true'); };
        toggle.addEventListener('click', () => sidebar.classList.contains('open') ? close() : open());
        overlay.addEventListener('click', close);
        sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
        window.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    }());
    </script>
<?php else: ?>
    <main class="public-content">
        <?php if (session('error')): ?><div class="public-alert-wrap"><div class="alert alert-error" role="alert"><?= esc(session('error')) ?></div></div><?php endif ?>
        <?= $this->renderSection('content') ?>
    </main>
<?php endif ?>
</body>
</html>
