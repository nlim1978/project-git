<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Roles &amp; Permissions</h1>
        <p class="lead compact">Create roles and configure controlled access to iDocTrack modules and actions.</p>
    </div>
    <?php if ($canManage): ?><a class="button button-primary" href="<?= site_url('admin/roles/new') ?>">Create Role</a><?php endif ?>
</section>

<form class="panel admin-toolbar" method="get" action="<?= site_url('admin/roles') ?>">
    <label class="field admin-search"><span>Search</span><input type="search" name="q" maxlength="100" value="<?= esc($search) ?>" placeholder="Role name or description"></label>
    <label class="field"><span>Status</span><select name="status"><option value="">All statuses</option><option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option><option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
    <div class="admin-toolbar-actions"><a class="button" href="<?= site_url('admin/roles') ?>">Reset</a><button class="button button-primary" type="submit">Apply</button></div>
</form>

<div class="table-card admin-table-card">
    <?php if ($roles === []): ?>
        <div class="empty-state"><strong>No roles match the selected filters.</strong></div>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Role</th><th>Type</th><th>Assigned Users</th><th>Permissions</th><th>Status</th><th>Created</th><th class="actions-heading">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><strong><?= esc($role['role_name']) ?></strong><small><?= esc($role['description'] ?: 'No description') ?></small></td>
                        <td><span class="badge <?= $role['role_type'] === 'System' ? 'badge-system' : 'badge-custom' ?>"><?= esc($role['role_type']) ?></span></td>
                        <td><?= number_format((int) $role['user_count']) ?></td>
                        <td><?= number_format((int) $role['permission_count']) ?> enabled</td>
                        <td><span class="badge <?= (int) $role['active'] === 1 ? 'badge-success' : 'badge-muted' ?>"><?= (int) $role['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= esc(substr((string) $role['created_at'], 0, 10)) ?></td>
                        <td class="row-actions">
                            <a class="button button-small" href="<?= site_url('admin/roles/' . $role['role_id']) ?>">View</a>
                            <?php if ($canManage): ?><a class="button button-small" href="<?= site_url('admin/roles/' . $role['role_id'] . '/edit') ?>">Edit</a><?php endif ?>
                            <?php if ($canManage && $role['role_type'] === 'Custom' && (int) $role['user_count'] === 0): ?>
                                <form action="<?= site_url('admin/roles/' . $role['role_id'] . '/delete') ?>" method="post" onsubmit="return confirm('Permanently delete this custom role?');">
                                    <?= csrf_field() ?><button class="button button-small button-danger" type="submit">Delete</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
    <div class="report-footer"><?= number_format(count($roles)) ?> role record<?= count($roles) === 1 ? '' : 's' ?></div>
</div>
<?= $this->endSection() ?>
