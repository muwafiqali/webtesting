<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $nip = trim((string) ($_POST['nip'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? '');
    $teamId = filter_var($_POST['team_id'] ?? null, FILTER_VALIDATE_INT);
    $roles = ['admin', 'kepala', 'ketua_tim', 'pegawai'];

    if (strlen($name) < 3 || strlen($name) > 150
        || !preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array($role, $roles, true)
        || strlen($password) < 8
        || ($nip !== '' && strlen($nip) > 30)) {
        flash('error', 'Periksa kembali nama, username, email, peran, dan kata sandi (minimal 8 karakter).');
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

    try {
        $statement = $pdo->prepare(
            'INSERT INTO users (name, username, email, nip, password_hash, role, team_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $name,
            $username,
            $email,
            $nip !== '' ? $nip : null,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $teamId,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
        flash('error', 'Username, email, atau NIP sudah digunakan akun lain.');
        redirect('users.php');
    }
    flash('success', 'Akun baru berhasil dibuat.');
    redirect('users.php');
}

$teams = $pdo->query('SELECT id, name FROM teams ORDER BY name')->fetchAll();
$accounts = $pdo->query(
    'SELECT u.id, u.name, u.username, u.email, u.nip, u.role, u.is_active, t.name AS team_name
     FROM users u LEFT JOIN teams t ON t.id = u.team_id
     ORDER BY u.role, u.name'
)->fetchAll();
$pendingCount = 0;
page_header('Pengguna', 'users', $user, $pendingCount);
?>
<div class="page-intro"><div><span class="eyebrow">ADMINISTRASI</span><h2>Kelola akun pengguna</h2><p>Atur akses masuk dan penempatan tim untuk pengguna portal.</p></div><span class="account-count"><?= count($accounts) ?> akun terdaftar</span></div>
<div class="admin-grid">
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">AKUN BARU</span><h2>Tambah pengguna</h2></div></div>
        <form method="post" class="request-form admin-form">
            <?= csrf_field() ?>
            <label>Nama lengkap<input type="text" name="name" maxlength="150" required></label>
            <div class="form-row"><label>Username<input type="text" name="username" minlength="3" maxlength="60" pattern="[A-Za-z0-9._-]+" required></label><label>NIP <span class="muted">(opsional)</span><input type="text" name="nip" maxlength="30"></label></div>
            <label>Email<input type="email" name="email" maxlength="190" required></label>
            <label>Peran<select name="role" required><option value="">Pilih peran</option><option value="pegawai">Pegawai</option><option value="ketua_tim">Ketua Tim</option><option value="kepala">Kepala Kantor</option><option value="admin">Administrator</option></select></label>
            <label>Tim <span class="muted">(wajib untuk pegawai dan Ketua Tim)</span><select name="team_id"><option value="">Tidak ditentukan</option><?php foreach ($teams as $team): ?><option value="<?= (int) $team['id'] ?>"><?= h($team['name']) ?></option><?php endforeach; ?></select></label>
            <label>Kata sandi sementara<input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
            <button class="button button-primary button-wide" type="submit">Buat akun</button>
        </form>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><span class="eyebrow">DAFTAR AKUN</span><h2>Pengguna terdaftar</h2></div></div>
        <div class="user-list">
            <?php foreach ($accounts as $account): ?>
                <article class="user-row"><span class="avatar"><?= h(substr($account['name'], 0, 1)) ?></span><div class="user-info"><strong><?= h($account['name']) ?></strong><small>@<?= h($account['username']) ?> · <?= h($account['email']) ?></small></div><div class="user-role"><span class="role-pill"><?= h(role_label($account['role'])) ?></span><small><?= h($account['team_name'] ?? 'Level kantor') ?></small></div></article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php page_footer(); ?>
