<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare(
        'SELECT id, password_hash FROM users WHERE username = ? AND is_active = 1 LIMIT 1'
    );
    $statement->execute([$username]);
    $account = $statement->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        redirect('dashboard.php');
    }

    $error = 'Username atau kata sandi tidak sesuai.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · IzinKantor</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-visual" position="center">
           
            <a class="brand brand-on-dark" href="login.php"><span class="brand-mark">IK</span><span>Izin<span class="brand-light">Kantor</span><small>PORTAL LAYANAN PEGAWAI</small></span></a>
            <div class="visual-copy">
                <span class="eyebrow eyebrow-light">LAYANAN INTERNAL</span>
                <h1>Urus izin kerja,<br><em>lebih mudah.</em></h1>
                <p>Ajukan izin dan pantau proses persetujuannya dalam satu tempat.</p>
            </div>
             <link rel="image" href="assets\logo.png" type="image/x-icon"\>
            <div class="login-card">
                <div class="login-mobile-brand brand"><span class="brand-mark">IK</span><span>Izin<span class="brand-light">Kantor</span></span></div>
                <span class="eyebrow">SELAMAT DATANG</span>
                <h2>Masuk ke akun Anda</h2>
                <p class="muted">Gunakan username dan kata sandi yang terdaftar.</p>
                <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <label>Username
                        <input type="text" name="username" autocomplete="username" placeholder="Contoh: rina" required autofocus>
                    </label>
                    <label>Kata sandi
                        <input type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                    </label>
                    <button class="button button-primary button-wide" type="submit">Masuk <span aria-hidden="true">→</span></button>
                </form>
                <div class="login-demo">
                    <strong>Akun demo</strong>
                    <p>admin · kepala · ketua.statistik · rina · budi · ketua.admin · andi · sari</p>
                    <small>Kata sandi semua akun: <code>password</code></small>
                </div>
                <p class="login-caption">Akses khusus untuk pegawai yang telah terdaftar.</p>
            </div>
        </section>
    </main>
</body>
</html>
