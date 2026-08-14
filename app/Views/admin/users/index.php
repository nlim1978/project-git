<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="page-heading page-heading-actions">
    <div>
        <div class="eyebrow">ADMINISTRATION</div>
        <h1 class="page-title">Users</h1>
        <p class="lead compact">Manage iDocTrack access without deleting historical identities.</p>
    </div>
    <?php if ($canCreate): ?><a class="button button-primary" href="<?= site_url('admin/users/new') ?>">Add user</a><?php endif ?>
</section>

<div class="table-card">
    <div class="table-scroll">
        <table>
            <thead><tr><th>User</th><th>Employee ID</th><th>Roles</th><th>Status</th><th class="actions-heading">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><strong><?= esc($row['first_name'] . ' ' . $row['last_name']) ?></strong><small><?= esc($row['username']) ?> · <?= esc($row['email']) ?></small></td>
                    <td><?= esc($row['employee_id']) ?></td>
                    <td><?= esc($row['roles'] ?: 'No role') ?></td>
                    <td><span class="badge <?= $row['account_status'] === 'Active' ? 'badge-success' : 'badge-muted' ?>"><?= esc($row['account_status']) ?></span></td>
                    <td class="row-actions">
                        <a class="button button-small" href="<?= site_url('admin/users/' . $row['user_id']) ?>">View</a>
                        <?php if ($canUpdate): ?><a class="button button-small" href="<?= site_url('admin/users/' . $row['user_id'] . '/edit') ?>">Edit</a><?php endif ?>
                        <?php if ($canDeactivate && ($row['account_status'] !== 'Active' || $row['user_id'] !== session('auth_user_id'))): ?>
                            <form action="<?= site_url('admin/users/' . $row['user_id'] . '/status') ?>" method="post" onsubmit="return confirm('<?= $row['account_status'] === 'Active' ? 'Deactivate' : 'Reactivate' ?> this user?');">
                                <?= csrf_field() ?>
                                <button class="button button-small <?= $row['account_status'] === 'Active' ? 'button-danger' : '' ?>" type="submit"><?= $row['account_status'] === 'Active' ? 'Deactivate' : 'Reactivate' ?></button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
