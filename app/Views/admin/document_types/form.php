<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$errors = session('errors') ?? [];
$value = static fn (string $name, string $fallback = ''): string => (string) old($name, $documentType[$name] ?? $fallback, false);
$active = (string) old('active', isset($documentType['active']) ? (string) (int) $documentType['active'] : '1', false);
?>
<section class="page-heading">
    <div class="eyebrow">DOCUMENT TYPES</div>
    <h1 class="page-title"><?= $creating ? 'Create Document Type' : 'Edit Document Type' ?></h1>
    <p class="lead compact">The prefix is used only when generating control numbers for newly received documents.</p>
</section>
<?php if ($errors): ?><div class="alert alert-error"><strong>Please review the form.</strong><ul><?php foreach ($errors as $error): ?><li><?= esc($error) ?></li><?php endforeach ?></ul></div><?php endif ?>

<form class="panel form-section organization-form" action="<?= $creating ? site_url('admin/document-types') : site_url('admin/document-types/' . $documentType['document_type_id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="detail-header"><h2>Classification Profile</h2><span class="muted"><?= $creating ? 'New reference' : 'Existing reference' ?></span></div>
    <div class="form-grid">
        <label class="field"><span>Document Type Code *</span><input name="type_code" maxlength="20" value="<?= esc($value('type_code')) ?>" placeholder="e.g. MEM" required><small class="field-help">Maximum 20 characters. Saved in uppercase.</small></label>
        <label class="field"><span>Prefix *</span><input name="prefix" maxlength="20" value="<?= esc($value('prefix')) ?>" placeholder="e.g. MEM" required><small class="field-help">Used for new document control numbers; saved in uppercase.</small></label>
        <label class="field field-wide"><span>Document Type Name *</span><input name="type_name" maxlength="100" value="<?= esc($value('type_name')) ?>" placeholder="e.g. Memorandum" required></label>
        <label class="field field-wide"><span>Description</span><textarea name="description" rows="4" maxlength="500" placeholder="Brief description of this document classification"><?= esc($value('description')) ?></textarea></label>
        <label class="field"><span>Status *</span><select name="active" required><option value="1" <?= $active === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inactive</option></select><small class="field-help">Inactive types remain on historical documents but cannot be selected for new receiving.</small></label>
    </div>
    <div class="form-actions"><a class="button" href="<?= site_url('admin/document-types') ?>">Cancel</a><button class="button button-primary" type="submit"><?= $creating ? 'Create Document Type' : 'Save Changes' ?></button></div>
</form>
<?= $this->endSection() ?>
