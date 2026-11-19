<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_role(['admin']);
$pdo = db();
$roles = ['admin', 'kepala', 'ketua_tim', 'pegawai'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_user' || $action === 'update_user') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $nip = trim((string) ($_POST['nip'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
        $accountId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

        if (strlen($name) < 3 || strlen($name) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
            || !in_array($role, $roles, true)
            || $nip === '' || !preg_match('/^[0-9]{1,30}$/', $nip)
            || strlen($address) > 500
            || ($action === 'create_user' && strlen($password) < 8)
            || ($action === 'update_user' && !$accountId)) {
            flash('error', 'Periksa kembali nama, NIP, email, alamat, peran, dan kata sandi.');
            redirect('users.php');
        }

        if (in_array($role, ['pegawai', 'ketua_tim'], true)) {
            $teamCheck = $pdo->prepare('SELECT id FROM teams WHERE id = ?');
            $teamCheck->execute([$teamId]);
            if (!$teamId || !$teamCheck->fetchColumn()) {
                flash('error', 'Pegawai dan Ketua Tim harus memiliki tim yang valid.');
                redirect('users.php');
            }
        } else {
            $teamId = null;
        }

        if ($action === 'update_user') {
            $accountQuery = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = ?');
            $accountQuery->execute([$accountId]);
            $existingAccount = $accountQuery->fetch();
            if (!$existingAccount) {
                flash('error', 'Pengguna tidak ditemukan.');
                redirect('users.php');
            }
            if ($existingAccount['role'] === 'admin' && (int) $existingAccount['is_active'] === 1 && $role !== 'admin') {
                $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                if ($adminCount <= 1) {
                    flash('error', 'Peran admin terakhir tidak dapat diubah.');
                    redirect('users.php');
                }
            }

            try {
                $statement = $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, nip = ?, address = ?, role = ?, team_id = ? WHERE id = ?'
                );
                $statement->execute([$name, $email, $nip, $address !== '' ? $address : null, $role, $teamId, $accountId]);
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                flash('error', 'Email atau NIP sudah digunakan akun lain.');
                redirect('users.php');
            }
            flash('success', 'Data pengguna berhasil diperbarui.');
            redirect('users.php');
        }

        $normalizedName = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)
            : $name;
        $username = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '.', $normalizedName), '.'));
        if ($username === '') {
            $username = 'pengguna';
        }
        $username = substr($username, 0, 54);

        $baseUsername = $username;
        $suffix = 2;
        $usernameCheck = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        while (true) {
            $usernameCheck->execute([$username]);
            if (!$usernameCheck->fetchColumn()) {
                break;
            }
            $username = substr($baseUsername, 0, 54) . '.' . $suffix;
            $suffix++;
        }

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (name, username, email, nip, address, password_hash, role, team_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $name,
                $username,
                $email,
                $nip,
                $address !== '' ? $address : null,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $teamId,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('error', 'Nama lengkap, email, atau NIP sudah digunakan akun lain.');
            redirect('users.php');
        }
        flash('success', 'Akun baru berhasil dibuat.');
        redirect('users.php');
    }

    if ($action === 'set_user_active') {
        $accountId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $active = filter_var($_POST['active'] ?? null, FILTER_VALIDATE_INT);
        if (!$accountId || !in_array($active, [0, 1], true)) {
            flash('error', 'Permintaan perubahan status akun tidak valid.');
            redirect('users.php');
        }
        if ($active === 0 && (int) $accountId === (int) $user['id']) {
            flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
            redirect('users.php');
        }

        $accountQuery = $pdo->prepare('SELECT id, role, is_active FROM users WHERE id = ?');
        $accountQuery->execute([$accountId]);
        $account = $accountQuery->fetch();
        if (!$account) {
            flash('error', 'Pengguna tidak ditemukan.');
            redirect('users.php');
        }
        if ($active === 0 && (int) $account['is_active'] === 1 && $account['role'] === 'admin') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($adminCount <= 1) {
                flash('error', 'Admin aktif terakhir tidak dapat dinonaktifkan.');
                redirect('users.php');
            }
        }

        $statement = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $statement->execute([$active, $accountId]);
        flash('success', $active === 1 ? 'Akun pengguna berhasil diaktifkan.' : 'Akun pengguna berhasil dinonaktifkan.');
        redirect('users.php');
    }

    flash('error', 'Aksi pengguna tidak dikenali.');
    redirect('users.php');
}

$teams = $pdo->query('SELECT id, name FROM teams ORDER BY name')->fetchAll();
$accounts = $pdo->query(
    'SELECT u.id, u.name, u.username, u.email, u.nip, u.address, u.role, u.team_id, u.is_active, t.name AS team_name
     FROM users u LEFT JOIN teams t ON t.id = u.team_id
     ORDER BY u.role, u.name'
)->fetchAll();
$pendingCount = 0;
page_header('Pengguna', 'users', $user, $pendingCount);
?>
<div class="page-intro">
    <div><span class="eyebrow">ADMINISTRASI</span><h2>Kelola akun pengguna</h2><p>Atur data dan akses pengguna. Nonaktifkan akun untuk mencabut akses tanpa menghapus riwayatnya.</p></div>
    <div class="users-heading-actions"><span class="account-count"><?= count($accounts) ?> akun terdaftar</span><button class="button button-primary" type="button" data-dialog-open="create-user-dialog">+ Tambah pengguna</button></div>
</div>
<section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">DAFTAR AKUN</span><h2>Pengguna terdaftar</h2></div></div>
        <div class="user-list">
            <?php foreach ($accounts as $account): ?>
                <article class="user-row">
                    <span class="avatar"><?= h(substr($account['name'], 0, 1)) ?></span>
                    <div class="user-info"><strong><?= h($account['name']) ?></strong><small>NIP <?= h($account['nip'] ?: '-') ?> · <?= h($account['email']) ?></small></div>
                    <div class="user-role"><span class="role-pill"><?= h(role_label($account['role'])) ?></span><small><?= h($account['team_name'] ?? 'Level kantor') ?></small></div>
                    <div class="user-actions">
                        <?php if ((int) $account['is_active'] === 1): ?>
                            <span class="account-status">Aktif</span>
                        <?php else: ?>
                            <span class="account-status account-status-inactive">Nonaktif</span>
                        <?php endif; ?>
                        <button class="button button-outline" type="button" data-dialog-open="edit-user-dialog-<?= (int) $account['id'] ?>">Edit</button>
                        <?php if ((int) $account['id'] !== (int) $user['id']): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_user_active">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <input type="hidden" name="active" value="<?= (int) $account['is_active'] === 1 ? '0' : '1' ?>">
                                <?php if ((int) $account['is_active'] === 1): ?>
                                    <button class="button button-danger-outline" type="button" data-dialog-open="deactivate-user-dialog-<?= (int) $account['id'] ?>">Nonaktifkan</button>
                                <?php else: ?>
                                    <button class="button button-outline" type="submit">Aktifkan</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
                <dialog class="app-dialog" id="edit-user-dialog-<?= (int) $account['id'] ?>" aria-labelledby="edit-user-title-<?= (int) $account['id'] ?>">
                    <div class="app-dialog-heading"><div><span class="eyebrow">DATA PENGGUNA</span><h2 id="edit-user-title-<?= (int) $account['id'] ?>">Edit pengguna</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button></div>
                    <form method="post" class="request-form admin-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                        <label>Nama lengkap<input type="text" name="name" maxlength="150" value="<?= h($account['name']) ?>" required></label>
                        <label>NIP<input type="text" name="nip" minlength="1" maxlength="30" pattern="[0-9]+" value="<?= h($account['nip']) ?>" required></label>
                        <label>Email<input type="email" name="email" maxlength="190" value="<?= h($account['email']) ?>" required></label>
                        <label>Alamat<textarea name="address" maxlength="500" rows="3"><?= h($account['address'] ?? '') ?></textarea></label>
                        <div class="custom-select-field"><label for="edit-user-role-<?= (int) $account['id'] ?>">Peran</label><select id="edit-user-role-<?= (int) $account['id'] ?>" name="role" required data-custom-select data-custom-select-label="Peran"><?php foreach ($roles as $roleOption): ?><option value="<?= h($roleOption) ?>" <?= $roleOption === $account['role'] ? 'selected' : '' ?>><?= h(role_label($roleOption)) ?></option><?php endforeach; ?></select></div>
                        <div class="custom-select-field"><label for="edit-user-team-<?= (int) $account['id'] ?>">Tim <span class="muted">(wajib untuk pegawai dan Ketua Tim)</span></label><select id="edit-user-team-<?= (int) $account['id'] ?>" name="team_id" data-custom-select data-custom-select-label="Tim"><option value="">Tidak ditentukan</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>" <?= (int) $team['id'] === (int) $account['team_id'] ? 'selected' : '' ?>><?= h($team['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="app-dialog-actions"><button class="button button-outline" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
                    </form>
                </dialog>
                <?php if ((int) $account['is_active'] === 1 && (int) $account['id'] !== (int) $user['id']): ?>
                    <dialog class="app-dialog user-deactivate-dialog" id="deactivate-user-dialog-<?= (int) $account['id'] ?>" aria-labelledby="deactivate-user-title-<?= (int) $account['id'] ?>">
                        <div class="user-deactivate-icon" aria-hidden="true">!</div>
                        <span class="eyebrow">KONFIRMASI NONAKTIFKAN</span>
                        <h2 id="deactivate-user-title-<?= (int) $account['id'] ?>">Nonaktifkan akun ini?</h2>
                        <p><?= h($account['name']) ?> tidak dapat login setelah dinonaktifkan. Riwayat pengajuan dan persetujuannya tetap tersimpan.</p>
                        <form method="post" class="user-deactivate-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_user_active">
                            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                            <input type="hidden" name="active" value="0">
                            <button class="button button-outline" type="button" data-dialog-close>Batal</button>
                            <button class="button button-danger-outline" type="submit">Ya, nonaktifkan</button>
                        </form>
                    </dialog>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<dialog class="app-dialog" id="create-user-dialog" aria-labelledby="create-user-title">
    <div class="app-dialog-heading"><div><span class="eyebrow">AKUN BARU</span><h2 id="create-user-title">Tambah pengguna</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button></div>
    <form method="post" class="request-form admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">
        <label>Nama lengkap<input type="text" name="name" maxlength="150" required></label>
        <label>NIP<input type="text" name="nip" minlength="1" maxlength="30" inputmode="numeric" pattern="[0-9]+" required></label>
        <label>Email<input type="email" name="email" maxlength="190" required></label>
        <label>Alamat<textarea name="address" maxlength="500" rows="3"></textarea></label>
        <div class="custom-select-field"><label for="create-user-role">Peran</label><select id="create-user-role" name="role" required data-custom-select data-custom-select-label="Peran"><option value="">Pilih peran</option><option value="pegawai">Pegawai</option><option value="ketua_tim">Ketua Tim</option><option value="kepala">Kepala Kantor</option><option value="admin">Administrator</option></select></div>
        <div class="custom-select-field"><label for="create-user-team">Tim <span class="muted">(wajib untuk pegawai dan Ketua Tim)</span></label><select id="create-user-team" name="team_id" data-custom-select data-custom-select-label="Tim"><option value="">Tidak ditentukan</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option><?php endforeach; ?></select></div>
        <label>Kata sandi sementara<input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
        <div class="app-dialog-actions"><button class="button button-outline" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Buat akun</button></div>
    </form>
</dialog>
<?php page_footer(); ?>
