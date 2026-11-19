<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if (strlen($name) < 3 || strlen($name) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 190
            || strlen($address) > 500) {
            flash('error', 'Periksa kembali nama lengkap, email, dan alamat Anda.');
            redirect('profile.php');
        }

        try {
            $statement = $pdo->prepare(
                'UPDATE users SET name = ?, email = ?, address = ? WHERE id = ?'
            );
            $statement->execute([$name, $email, $address !== '' ? $address : null, $user['id']]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            flash('error', 'Email tersebut sudah digunakan akun lain.');
            redirect('profile.php');
        }

        flash('success', 'Profil berhasil diperbarui.');
        redirect('profile.php');
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$user['id']]);
        $passwordHash = $statement->fetchColumn();

        if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
            flash('error', 'Kata sandi saat ini tidak sesuai.');
            redirect('profile.php');
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 255) {
            flash('error', 'Kata sandi baru harus berisi 8 sampai 255 karakter.');
            redirect('profile.php');
        }
        if ($newPassword !== $confirmPassword) {
            flash('error', 'Konfirmasi kata sandi baru tidak sama.');
            redirect('profile.php');
        }

        $statement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $statement->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        flash('success', 'Kata sandi berhasil diganti.');
        redirect('profile.php');
    }

    flash('error', 'Permintaan profil tidak dikenali. Silakan coba kembali.');
    redirect('profile.php');
}

page_header('Profil Saya', 'profile', $user);
?>
<div class="page-intro">
    <div><span class="eyebrow">AKUN ANDA</span><h2>Profil</h2><p>Informasi akun dan keamanan Anda.</p></div>
</div>
<div class="profile-layout">
    <section class="panel profile-picture-card">
        <div class="panel-heading"><div><span class="eyebrow">AKUN ANDA</span><h2>Foto profil</h2></div></div>
        <span class="profile-avatar-large" aria-hidden="true"><?= h(strtoupper(substr($user['name'], 0, 1))) ?></span>
        <strong class="profile-picture-name"><?= h($user['name']) ?></strong>
        <span class="muted"><?= h(role_label($user['role'])) ?></span>
    </section>
    <section class="panel profile-information-card">
        <div class="panel-heading"><div><span class="eyebrow">DETAIL AKUN</span><h2>Informasi profil</h2></div></div>
        <dl class="profile-fields">
            <div class="profile-field"><dt>Nama lengkap</dt><dd><?= h($user['name']) ?></dd></div>
            <div class="profile-field"><dt>Email</dt><dd><?= h($user['email']) ?></dd></div>
            <div class="profile-field"><dt>NIP</dt><dd><?= h($user['nip'] ?: '-') ?></dd></div>
            <div class="profile-field"><dt>Alamat</dt><dd><?= $user['address'] ? nl2br(h($user['address'])) : 'Belum ditambahkan' ?></dd></div>
        </dl>
        <div class="profile-actions">
            <button class="button button-primary" type="button" data-dialog-open="edit-profile-dialog">Edit profil</button>
            <button class="button button-outline" type="button" data-dialog-open="change-password-dialog">Ganti kata sandi</button>
        </div>
    </section>
</div>
<dialog class="app-dialog" id="edit-profile-dialog" aria-labelledby="edit-profile-title">
    <div class="app-dialog-heading"><div><span class="eyebrow">INFORMASI PRIBADI</span><h2 id="edit-profile-title">Edit profil</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button></div>
    <form method="post" class="request-form profile-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <label>Nama lengkap<input type="text" name="name" maxlength="150" value="<?= h($user['name']) ?>" required></label>
        <label>Email<input type="email" name="email" maxlength="190" value="<?= h($user['email']) ?>" required></label>
        <label>Alamat<textarea name="address" maxlength="500" rows="4" placeholder="Masukkan alamat Anda"><?= h($user['address'] ?? '') ?></textarea></label>
        <div class="app-dialog-actions"><button class="button button-outline" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></div>
    </form>
</dialog>
<dialog class="app-dialog" id="change-password-dialog" aria-labelledby="change-password-title">
    <div class="app-dialog-heading"><div><span class="eyebrow">KEAMANAN AKUN</span><h2 id="change-password-title">Ganti kata sandi</h2></div><button class="dialog-close" type="button" data-dialog-close aria-label="Tutup">×</button></div>
    <form method="post" class="request-form profile-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <label>Kata sandi saat ini<input type="password" name="current_password" maxlength="255" autocomplete="current-password" required></label>
        <label>Kata sandi baru<input type="password" name="new_password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
        <label>Konfirmasi kata sandi baru<input type="password" name="confirm_password" minlength="8" maxlength="255" autocomplete="new-password" required></label>
        <p class="muted profile-password-hint">Gunakan minimal 8 karakter.</p>
        <div class="app-dialog-actions"><button class="button button-outline" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Ganti kata sandi</button></div>
    </form>
</dialog>
<?php page_footer(); ?>
