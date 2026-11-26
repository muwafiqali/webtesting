<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_login();
$pdo = db();
$canFilterRequests = in_array($user['role'], ['admin', 'kepala'], true);
$filterKeys = ['employee_name', 'submitted_on'];
$filters = [];
$filterError = null;
$filterPage = max(1, (int) ($_GET['page'] ?? 1));
$filterPages = 1;
$paginationParams = [];

if ($canFilterRequests) {
    foreach ($filterKeys as $key) {
        $value = $_GET[$key] ?? '';
        if (!is_string($value)) {
            $filterError = 'Nilai filter tidak valid.';
            $value = '';
        }
        $filters[$key] = trim($value);
        if ($filters[$key] !== '') {
            $paginationParams[$key] = $filters[$key];
        }
    }

    if ($filters['submitted_on'] !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $filters['submitted_on']);
        if ($date === false || $date->format('Y-m-d') !== $filters['submitted_on']) {
            $filterError = 'Format tanggal pengajuan tidak valid.';
        }
    }
}
$filterUserNames = $canFilterRequests
    ? $pdo->query("SELECT DISTINCT name FROM users WHERE role IN ('pegawai', 'ketua_tim') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)
    : [];

if ($user['role'] === 'pegawai') {
    $summary = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(status IN ('awaiting_team', 'awaiting_head')) AS pending,
                SUM(status = 'approved') AS approved,
                SUM(status IN ('revision', 'rejected')) AS needs_attention
         FROM leave_requests WHERE user_id = ?"
    );
    $summary->execute([$user['id']]);
    $stats = $summary->fetch();
    $recentQuery = $pdo->prepare(
        'SELECT r.*, lt.name AS type_name, u.name AS employee_name, t.name AS team_name
         FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
         JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id
         WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT 6'
    );
    $recentQuery->execute([$user['id']]);
    $recent = $recentQuery->fetchAll();
    $showEmployee = false;
} else {
    $conditions = [];
    $params = [];
    if ($user['role'] === 'ketua_tim') {
        $conditions[] = 'u.team_id = ?';
        $params[] = $user['team_id'];
    }
    if ($canFilterRequests && !$filterError) {
        if ($filters['employee_name'] !== '') {
            $conditions[] = 'u.name LIKE ?';
            $params[] = '%' . $filters['employee_name'] . '%';
        }
        if ($filters['submitted_on'] !== '') {
            $dateStart = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['submitted_on']);
            $dateEnd = $dateStart->modify('+1 day');
            $conditions[] = 'r.created_at >= ?';
            $params[] = $dateStart->format('Y-m-d 00:00:00');
            $conditions[] = 'r.created_at < ?';
            $params[] = $dateEnd->format('Y-m-d 00:00:00');
        }
    } elseif ($filterError) {
        $conditions[] = '1 = 0';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $summary = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(r.status = 'awaiting_team') AS awaiting_team,
                SUM(r.status = 'awaiting_head') AS awaiting_head,
                SUM(r.status = 'approved') AS approved
         FROM leave_requests r JOIN users u ON u.id = r.user_id {$where}"
    );
    $summary->execute($params);
    $stats = $summary->fetch();
    if ($canFilterRequests) {
        $filterPages = max(1, (int) ceil((int) $stats['total'] / 20));
        $filterPage = min($filterPage, $filterPages);
        $offset = ($filterPage - 1) * 20;
        $recentQuery = $pdo->prepare(
            "SELECT r.*, lt.name AS type_name, u.name AS employee_name, t.name AS team_name
             FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
             JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id {$where}
             ORDER BY r.created_at DESC LIMIT 20 OFFSET {$offset}"
        );
    } else {
        $recentQuery = $pdo->prepare(
            "SELECT r.*, lt.name AS type_name, u.name AS employee_name, t.name AS team_name
             FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
             JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id {$where}
             ORDER BY r.created_at DESC LIMIT 6"
        );
    }
    $recentQuery->execute($params);
    $recent = $recentQuery->fetchAll();
    $showEmployee = true;
}

$pendingCount = pending_approval_count($pdo, $user);

page_header('Ringkasan', 'dashboard', $user, $pendingCount);
?>
<section class="welcome-banner">
    <div><span class="eyebrow eyebrow-light">DASHBOARD</span><h2>Halo, <?= h($user['name']) ?> <span class="wave">✦</span></h2><p><?= $user['role'] === 'pegawai' ? 'Pantau pengajuan izin dan ajukan kebutuhan izin Anda di sini.' : 'Berikut ringkasan aktivitas perizinan di kantor Anda.' ?></p></div>
    <div class="welcome-decoration"></div>
</section>

<?php if ($canFilterRequests): ?>
    <section class="panel dashboard-filter-panel">
        <div class="panel-heading"><div><span class="eyebrow">PENYARINGAN DATA</span><h2>Filter pengajuan</h2></div></div>
        <?php if ($filterError): ?><div class="alert alert-error"><?= h($filterError) ?></div><?php endif; ?>
        <form action="dashboard.php" method="get" class="request-form dashboard-filter-form">
            <div class="dashboard-filter-grid">
                <div class="dashboard-name-field">
                    <label for="filter-employee-name">Nama pegawai / Ketua Tim</label>
                    <div class="dashboard-name-suggest" data-name-suggest>
                        <input type="search" name="employee_name" id="filter-employee-name" maxlength="150" value="<?= h($filters['employee_name']) ?>" placeholder="Pilih atau ketik nama" autocomplete="off" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="filter-user-names">
                        <div class="dashboard-name-suggest-popup" data-name-popup hidden>
                            <div class="dashboard-name-suggest-options" id="filter-user-names" role="listbox" aria-label="Nama pegawai dan Ketua Tim">
                                <?php foreach ($filterUserNames as $index => $filterUserName): ?>
                                    <button class="dashboard-name-suggest-option" id="filter-user-name-<?= (int) $index ?>" type="button" role="option" tabindex="-1" aria-selected="false" data-name-option><?= h($filterUserName) ?></button>
                                <?php endforeach; ?>
                            </div>
                            <p class="dashboard-name-suggest-empty" data-name-empty role="status" hidden>Tidak ada nama yang cocok.</p>
                        </div>
                    </div>
                </div>
                <label>Tanggal pengajuan
                    <input type="date" name="submitted_on" value="<?= h($filters['submitted_on']) ?>">
                </label>
            </div>
            <div class="form-actions">
                <span class="muted">Filter hanya memakai nama dan tanggal pengajuan. Hasil ditampilkan 20 per halaman.</span>
                <div class="dashboard-filter-actions">
                    <a class="button button-outline" href="dashboard.php">Reset</a>
                    <button class="button button-primary" type="submit">Terapkan filter</button>
                </div>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($user['role'] === 'pegawai'): ?>
    <div class="stats-grid">
        <article class="stat-card"><span class="stat-icon icon-blue">▤</span><div><span>Total pengajuan</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></div><small>Semua pengajuan Anda</small></article>
        <article class="stat-card"><span class="stat-icon icon-amber">◷</span><div><span>Dalam proses</span><strong><?= (int) ($stats['pending'] ?? 0) ?></strong></div><small>Menunggu persetujuan</small></article>
        <article class="stat-card"><span class="stat-icon icon-green">✓</span><div><span>Disetujui</span><strong><?= (int) ($stats['approved'] ?? 0) ?></strong></div><small>Telah disetujui pimpinan</small></article>
        <article class="stat-card"><span class="stat-icon icon-red">!</span><div><span>Perlu tindak lanjut</span><strong><?= (int) ($stats['needs_attention'] ?? 0) ?></strong></div><small>Perbaikan atau ditolak</small></article>
    </div>
<?php else: ?>
    <div class="stats-grid">
        <article class="stat-card"><span class="stat-icon icon-blue">▤</span><div><span>Total pengajuan</span><strong><?= (int) ($stats['total'] ?? 0) ?></strong></div><small>Seluruh data yang dapat diakses</small></article>
        <article class="stat-card"><span class="stat-icon icon-amber">◷</span><div><span><?= in_array($user['role'], ['ketua_tim', 'kepala'], true) ? 'Menunggu Persetujuan Anda' : 'Menunggu Ketua Tim' ?></span><strong><?= in_array($user['role'], ['ketua_tim', 'kepala'], true) ? $pendingCount : (int) ($stats['awaiting_team'] ?? 0) ?></strong></div><small><?= in_array($user['role'], ['ketua_tim', 'kepala'], true) ? 'Antrean yang dapat Anda tindak lanjuti' : 'Dalam antrean tim' ?></small></article>
        <article class="stat-card"><span class="stat-icon icon-purple">⌛</span><div><span>Menunggu Kepala</span><strong><?= (int) ($stats['awaiting_head'] ?? 0) ?></strong></div><small>Menunggu keputusan akhir</small></article>
        <article class="stat-card"><span class="stat-icon icon-green">✓</span><div><span>Disetujui</span><strong><?= (int) ($stats['approved'] ?? 0) ?></strong></div><small>Telah selesai diproses</small></article>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow"><?= $canFilterRequests ? 'HASIL PENGAJUAN' : 'AKTIVITAS TERBARU' ?></span><h2><?= $user['role'] === 'pegawai' ? 'Pengajuan izin saya' : 'Pengajuan terbaru' ?><?php if ($canFilterRequests): ?> <span class="heading-count"><?= (int) ($stats['total'] ?? 0) ?></span><?php endif; ?></h2></div><a class="button button-outline" href="requests.php">Lihat semua <span aria-hidden="true">→</span></a></div>
    <?php request_table($recent, $showEmployee); ?>
    <?php if ($canFilterRequests && $filterPages > 1): ?>
        <nav class="form-actions" aria-label="Navigasi hasil filter">
            <?php if ($filterPage > 1): ?><a class="button button-outline" href="?<?= h(http_build_query(array_merge($paginationParams, ['page' => $filterPage - 1]))) ?>">← Sebelumnya</a><?php endif; ?>
            <span class="muted">Halaman <?= $filterPage ?> dari <?= $filterPages ?></span>
            <?php if ($filterPage < $filterPages): ?><a class="button button-outline" href="?<?= h(http_build_query(array_merge($paginationParams, ['page' => $filterPage + 1]))) ?>">Berikutnya →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php if ($user['role'] === 'pegawai'): ?>
    <a class="floating-cta" href="requests.php#buat-pengajuan"><span>+</span> Buat pengajuan izin</a>
<?php endif; ?>
<?php page_footer(); ?>
