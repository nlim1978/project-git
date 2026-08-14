<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>
<section class="login-page">
    <div class="login-shell">
        <section class="login-brand">
            <div>
                <span class="brand-mark" aria-hidden="true">IDT</span>
                <h1>Document Tracking and Routing System</h1>
                <p>Centralized receiving, work queue, monitoring, reporting, and administration for secure document operations.</p>
            </div>
            <small>Authorized users only · Business information system</small>
        </section>
        <section class="login-form-panel">
            <div class="eyebrow">SECURE ACCESS</div>
            <h2>Welcome back</h2>
            <p>Enter your iDocTrack account credentials to continue.</p>

            <?php $errors = session('errors') ?? []; ?>
            <form action="<?= site_url('login') ?>" method="post" class="form-stack" autocomplete="on">
                <?= csrf_field() ?>
                <label class="field">
                    <span>Username</span>
                    <input name="username" type="text" maxlength="50" value="<?= esc(old('username', '', false)) ?>" autocomplete="username" required autofocus>
                    <?php if (isset($errors['username'])): ?><small class="field-error"><?= esc($errors['username']) ?></small><?php endif ?>
                </label>
                <div class="field">
                    <label for="loginPassword">Password</label>
                    <div class="password-control">
                        <input id="loginPassword" name="password" type="password" maxlength="255" autocomplete="current-password" required>
                        <button id="passwordToggle" class="password-toggle" type="button" aria-controls="loginPassword" aria-label="Show password">Show</button>
                    </div>
                    <?php if (isset($errors['password'])): ?><small class="field-error"><?= esc($errors['password']) ?></small><?php endif ?>
                </div>
                <button class="button button-primary button-block" type="submit">Sign in</button>
            </form>
            <a class="login-track-link" href="<?= site_url('track') ?>">Track a document instead</a>
        </section>
    </div>
</section>
<script>
(function () {
    const input = document.getElementById('loginPassword');
    const button = document.getElementById('passwordToggle');
    if (!input || !button) return;
    button.addEventListener('click', () => {
        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        button.textContent = hidden ? 'Hide' : 'Show';
        button.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    });
}());
</script>
<?= $this->endSection() ?>
