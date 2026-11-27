<?php

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid atau kedaluwarsa. Silakan kembali dan coba lagi.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.name, u.username, u.email, u.nip, u.address, u.role, u.team_id,
                t.name AS team_name
         FROM users u LEFT JOIN teams t ON t.id = u.team_id
         WHERE u.id = ? AND u.is_active = 1'
    );
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;

    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki akses ke halaman ini.');
    }
    return $user;
}

function general_team_id(PDO $pdo): ?int
{
    $statement = $pdo->query("SELECT id FROM teams WHERE LOWER(TRIM(name)) = 'tim umum' LIMIT 2");
    $teamIds = $statement->fetchAll(PDO::FETCH_COLUMN);
    if (count($teamIds) > 1) {
        throw new RuntimeException('Nama tim "Tim Umum" harus unik untuk routing pengajuan Non-Dinas.');
    }
    return isset($teamIds[0]) ? (int) $teamIds[0] : null;
}

function require_general_team_approver(PDO $pdo): void
{
    $generalTeamId = general_team_id($pdo);
    if ($generalTeamId === null) {
        throw new InvalidArgumentException('Tim Umum belum dikonfigurasi sebagai tujuan pengajuan Non-Dinas.');
    }

    $statement = $pdo->prepare(
        "SELECT id FROM users WHERE role = 'ketua_tim' AND team_id = ? AND is_active = 1 LIMIT 1"
    );
    $statement->execute([$generalTeamId]);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('Akun Ketua Tim Umum belum aktif.');
    }
}

function pending_approval_count(PDO $pdo, array $user): int
{
    if ($user['role'] === 'ketua_tim') {
        $generalTeamId = general_team_id($pdo);
        $isGeneralTeam = $generalTeamId !== null && (int) $user['team_id'] === $generalTeamId;
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM leave_requests r
             JOIN leave_types lt ON lt.id = r.leave_type_id
             JOIN users u ON u.id = r.user_id
             WHERE r.status = 'awaiting_team'
               AND ((lt.category = 'dinas' AND u.team_id = ?)
                    OR (lt.category = 'non_dinas' AND ? = 1))"
        );
        $statement->execute([$user['team_id'], $isGeneralTeam ? 1 : 0]);
        return (int) $statement->fetchColumn();
    }

    if ($user['role'] === 'kepala') {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM leave_requests WHERE status = 'awaiting_head'"
        )->fetchColumn();
    }

    return 0;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function role_label(string $role): string
{
    $labels = [
        'admin' => 'Administrator',
        'kepala' => 'Kepala Kantor',
        'ketua_tim' => 'Ketua Tim',
        'pegawai' => 'Pegawai',
    ];
    return $labels[$role] ?? $role;
}

function status_label(string $status): string
{
    $labels = [
        'awaiting_team' => 'Menunggu Ketua Tim',
        'awaiting_head' => 'Menunggu Kepala Kantor',
        'revision' => 'Perlu Perbaikan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];
    return $labels[$status] ?? $status;
}

function status_class(string $status): string
{
    $classes = [
        'awaiting_team' => 'status-waiting',
        'awaiting_head' => 'status-waiting',
        'revision' => 'status-revision',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
    ];
    return $classes[$status] ?? 'status-neutral';
}

function date_id(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return h($date);
    }
    $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function time_id(?string $time): string
{
    return $time ? substr($time, 0, 5) : '-';
}

function time_picker(string $name, string $label, ?string $time = null, ?string $id = null): void
{
    $selectedHour = $time ? substr($time, 0, 2) : '';
    $selectedMinute = $time ? substr($time, 3, 2) : '';
    $pickerId = $id ?? $name;
    ?>
    <div class="time-picker-field" data-time-picker>
        <span class="time-picker-label"><?= h($label) ?></span>
        <input type="hidden" name="<?= h($name) ?>_hour" data-time-hour-value value="<?= h($selectedHour) ?>">
        <input type="hidden" name="<?= h($name) ?>_minute" data-time-minute-value value="<?= h($selectedMinute) ?>">
        <button class="time-picker-trigger" type="button" data-time-trigger aria-expanded="false" aria-controls="<?= h($pickerId) ?>-panel" aria-label="Pilih waktu <?= h($label) ?>">
            <span class="time-picker-icon" aria-hidden="true">◷</span>
            <span class="time-picker-value" data-time-display><?= $time ? h(time_id($time)) : 'Pilih waktu' ?></span>
            <span class="time-picker-arrow" aria-hidden="true">⌄</span>
        </button>
        <div class="time-picker-panel" id="<?= h($pickerId) ?>-panel" data-time-panel role="group" aria-label="Pilih waktu <?= h($label) ?>" hidden>
            <div class="time-picker-panel-heading">
                <div><span class="time-picker-panel-kicker">PILIH WAKTU</span><strong>Format 24 jam</strong></div>
                <div class="time-picker-heading-controls">
                    <span class="time-picker-preview" data-time-preview><?= $time ? h(time_id($time)) : '--:--' ?></span>
                    <button class="time-picker-close" type="button" data-time-close aria-label="Tutup pemilih waktu">×</button>
                </div>
            </div>
            <div class="time-picker-columns">
                <div class="time-picker-choice">
                    <span>Jam</span>
                    <div class="time-picker-options" data-time-hour-options role="listbox" aria-label="Jam <?= h($label) ?>">
                        <?php for ($hour = 0; $hour <= 24; $hour++): $hourValue = sprintf('%02d', $hour); ?>
                            <button class="time-picker-option" type="button" role="option" tabindex="-1" data-time-option="hour" data-value="<?= $hourValue ?>" aria-selected="<?= $hourValue === $selectedHour ? 'true' : 'false' ?>"><?= $hourValue ?></button>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="time-picker-choice">
                    <span>Menit</span>
                    <div class="time-picker-options" data-time-minute-options role="listbox" aria-label="Menit <?= h($label) ?>">
                        <?php for ($minute = 0; $minute < 60; $minute++): $minuteValue = sprintf('%02d', $minute); ?>
                            <button class="time-picker-option" type="button" role="option" tabindex="-1" data-time-option="minute" data-value="<?= $minuteValue ?>" aria-selected="<?= $minuteValue === $selectedMinute ? 'true' : 'false' ?>"><?= $minuteValue ?></button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
        <small class="time-picker-error" data-time-error hidden>Pilih jam dan menit terlebih dahulu.</small>
    </div>
    <?php
}

function date_long(): string
{
    $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    return $days[(int) date('N')] . ', ' . date_id(date('Y-m-d'));
}

function page_header(string $title, string $active, array $user, int $pendingCount = 0): void
{
    $notice = flash('success');
    $error = flash('error');
    $menus = [
        ['dashboard.php', 'Ringkasan', '⌂', 'dashboard'],
        ['requests.php', 'Pengajuan Izin', '▤', 'requests'],
        ['profile.php', 'Profil Saya', '◉', 'profile'],
    ];
    if (in_array($user['role'], ['ketua_tim', 'kepala'], true)) {
        $menus[] = ['approvals.php', 'Persetujuan', '✓', 'approvals'];
    }
    if ($user['role'] === 'admin') {
        $menus[] = ['users.php', 'Pengguna', '♙', 'users'];
    }
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= h($title) ?> · IzinKantor</title>
        <link rel="icon" href="assets/logo1.png" type="image/png">
        <script>
            (function () {
                try {
                    var theme = localStorage.getItem('izinkantor-theme');
                    if (theme === 'dark' || theme === 'light') document.documentElement.dataset.theme = theme;
                } catch (e) {}
            })();
        </script>
        <link rel="stylesheet" href="assets/app.css?v=<?= (int) filemtime(__DIR__ . '/assets/app.css') ?>">
        <?php
        $pageStyles = [
            'dashboard' => 'dashboard.css',
            'requests' => 'requests.css',
            'approvals' => 'approvals.css',
            'users' => 'users.css',
            'profile' => 'profile.css',
        ];
        if (isset($pageStyles[$active])): ?>
            <link rel="stylesheet" href="assets/<?= h($pageStyles[$active]) ?>?v=<?= (int) filemtime(__DIR__ . '/assets/' . $pageStyles[$active]) ?>">
        <?php endif; ?>
        <?php if (in_array($active, ['profile', 'users'], true)): ?>
            <script src="assets/dialog.js?v=<?= (int) filemtime(__DIR__ . '/assets/dialog.js') ?>" defer></script>
        <?php endif; ?>
        <?php if ($active === 'users'): ?>
            <script src="assets/custom-select.js?v=<?= (int) filemtime(__DIR__ . '/assets/custom-select.js') ?>" defer></script>
        <?php endif; ?>
        <?php if ($active === 'dashboard' && in_array($user['role'], ['admin', 'kepala'], true)): ?>
            <script src="assets/name-suggest.js?v=<?= (int) filemtime(__DIR__ . '/assets/name-suggest.js') ?>" defer></script>
        <?php endif; ?>
        <?php if ($active === 'requests'): ?>
            <script src="assets/time-picker.js?v=<?= (int) filemtime(__DIR__ . '/assets/time-picker.js') ?>" defer></script>
        <?php endif; ?>
        <?php if ($active === 'approvals' && $user['role'] === 'kepala'): ?>
            <script src="assets/approvals.js?v=<?= (int) filemtime(__DIR__ . '/assets/approvals.js') ?>" defer></script>
        <?php endif; ?>
        <script src="assets/theme.js?v=<?= (int) filemtime(__DIR__ . '/assets/theme.js') ?>" defer></script>
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-row">
                <a class="brand" href="dashboard.php"><img class="brand-logo" src="assets/logo1.png" alt="Logo SEMESTA"></a>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Ganti mode tampilan" title="Ganti mode tampilan"><span class="theme-icon" aria-hidden="true">☾</span></button>
            </div>
            <div class="sidebar-label">MENU UTAMA</div>
            <nav class="side-nav">
                <?php foreach ($menus as [$href, $label, $icon, $key]): ?>
                    <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= h($href) ?>"><span class="nav-icon"><?= h($icon) ?></span><?= h($label) ?><?php if ($key === 'approvals' && $pendingCount > 0): ?><span class="nav-count"><?= $pendingCount ?></span><?php endif; ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-card"><span class="help-icon">?</span><strong>Butuh bantuan?</strong><p>Hubungi administrator jika mengalami kendala.</p></div>
                <div class="sidebar-user"><span class="avatar"><?= h(substr($user['name'], 0, 1)) ?></span><span><strong><?= h($user['name']) ?></strong><small><?= h(role_label($user['role'])) ?></small></span></div>
            </div>
        </aside>
        <main class="main-area">
            <header class="topbar">
                <div><span class="eyebrow">PORTAL PERIZINAN PEGAWAI</span><h1><?= h($title) ?></h1></div>
                <div class="topbar-right">
                    <span class="today"><?= h(date_long()) ?></span>
                    <div class="profile-chip"><span class="avatar avatar-small"><?= h(substr($user['name'], 0, 1)) ?></span><span><strong><?= h($user['name']) ?></strong><small><?= h(role_label($user['role'])) ?></small></span></div>
                    <form action="logout.php" method="post" class="logout-form"><?= csrf_field() ?><button class="button button-quiet" type="submit">Keluar</button></form>
                </div>
            </header>
            <section class="page-content">
                <?php if ($notice): ?><div class="alert alert-success"><?= h($notice) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <?php
}

function page_footer(): void
{
    ?>
            </section>
            <footer class="footer">IzinKantor <span>·</span> Sistem informasi perizinan pegawai</footer>
        </main>
    </div>
    </body>
    </html>
    <?php
}

function request_table(array $requests, bool $showEmployee = false): void
{
    ?>
    <?php if (!$requests): ?>
        <div class="empty-state"><span class="empty-icon">▤</span><strong>Belum ada pengajuan</strong><p>Data pengajuan akan muncul di sini.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>JENIS IZIN</th><?php if ($showEmployee): ?><th>PEGAWAI</th><?php endif; ?><th>PERIODE</th><th>TANGGAL PENGAJUAN</th><th>STATUS</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><strong><?= h($request['type_name']) ?></strong><small class="table-sub"><?= h($request['request_code']) ?></small></td>
                        <?php if ($showEmployee): ?><td><?= h($request['employee_name']) ?><small class="table-sub"><?= h($request['team_name'] ?? 'Tanpa tim') ?></small></td><?php endif; ?>
                        <td><?= h(date_id($request['start_date'])) ?> – <?= h(date_id($request['end_date'])) ?><small class="table-sub"><?= h(time_id($request['start_time'])) ?> – <?= h(time_id($request['end_time'])) ?></small></td>
                        <td><?= h(date_id($request['created_at'])) ?></td>
                        <td><span class="status-pill <?= h(status_class($request['status'])) ?>"><?= h(status_label($request['status'])) ?></span></td>
                        <td><a class="text-link" href="requests.php#request-<?= (int) $request['id'] ?>">Detail <span aria-hidden="true">→</span></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php
}
