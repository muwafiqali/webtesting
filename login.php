<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nip = trim((string) ($_POST['nip'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $statement = db()->prepare(
        'SELECT id, password_hash FROM users WHERE nip = ? AND is_active = 1 LIMIT 1'
    );
    $statement->execute([$nip]);
    $account = $statement->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        redirect('dashboard.php');
    }

    $error = 'NIP atau kata sandi tidak sesuai.';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · IzinKantor</title>
    <link rel="icon" href="assets/logo.png" type="image/png">
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-visual" position="center">
           
            <a class="brand brand-on-dark" href="login.php"><img class="brand-logo" src="assets/logo.png" alt="Logo SEMESTA"></a>
            <div class="login-card">
                <div class="login-mobile-brand brand"><img class="brand-logo" src="assets/logo.png" alt="Logo SEMESTA"></div>
                <span class="eyebrow">SELAMAT DATANG</span>
                <h2>Masuk ke akun Anda</h2>
                <p class="muted">Gunakan NIP dan kata sandi yang terdaftar.</p>
                <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
                <form method="post" class="form-stack">
                    <?= csrf_field() ?>
                    <label>NIP
                        <input type="text" name="nip" inputmode="numeric" autocomplete="username" pattern="[0-9]+" placeholder="Masukkan NIP" required autofocus>
                    </label>
                    <label>Kata sandi
                        <input type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                    </label>
                    <button class="button button-primary button-wide" type="submit">Masuk <span aria-hidden="true">→</span></button>
                </form>
                <div class="login-demo">
                    <strong>Akun </strong>
                    <p>NIip w12345678</p>
                    <small>.....: <code>password</code></small>
                </div>
                <p class="login-caption">Akses khusus untuk pegawai yang telah terdaftar.</p>
            </div>
        </section>
    </main>
</body>
</html>
