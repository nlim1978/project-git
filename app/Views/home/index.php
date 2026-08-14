<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="hero">
    <div class="eyebrow">STEP 1 · FOUNDATION</div>
    <h1>iDocTrack development foundation is ready.</h1>
    <p class="lead">CodeIgniter 4, MVC + Service pattern, SQL Server configuration, and a mobile-first interface shell.</p>

    <div class="status-grid" aria-label="Foundation status">
        <article class="status-card">
            <span class="status-dot"></span>
            <div><strong>CI4 architecture</strong><small>Controllers → Services → Models</small></div>
        </article>
        <article class="status-card">
            <span class="status-dot"></span>
            <div><strong>SQL Server</strong><small>SQLSRV via environment configuration</small></div>
        </article>
        <article class="status-card">
            <span class="status-dot"></span>
            <div><strong>Mobile first</strong><small>Responsive shell starts at small screens</small></div>
        </article>
    </div>

    <p class="next-step">Next: SQL Server migrations for the approved iDocTrack ERD.</p>
</section>
<?= $this->endSection() ?>
