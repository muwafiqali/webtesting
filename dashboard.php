<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_login();
$pdo = db();

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
    $where = '';
    $params = [];
    if ($user['role'] === 'ketua_tim') {
        $where = 'WHERE u.team_id = ?';
        $params[] = $user['team_id'];
    }
    $summary = $pdo->prepare(
        "SELECT COUNT(*) AS total,
                SUM(r.status = 'awaiting_team') AS awaiting_team,
                SUM(r.status = 'awaiting_head') AS awaiting_head,
                SUM(r.status = 'approved') AS approved
         FROM leave_requests r JOIN users u ON u.id = r.user_id {$where}"
    );
    $summary->execute($params);
    $stats = $summary->fetch();
    $recentQuery = $pdo->prepare(
        "SELECT r.*, lt.name AS type_name, u.name AS employee_name, t.name AS team_name
         FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
         JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id {$where}
         ORDER BY r.created_at DESC LIMIT 6"
    );
    $recentQuery->execute($params);
    $recent = $recentQuery->fetchAll();
    $showEmployee = true;
}

$pendingCount = 0;
if ($user['role'] === 'ketua_tim') {
    $pendingQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM leave_requests r JOIN users u ON u.id = r.user_id
         WHERE u.team_id = ? AND r.status = 'awaiting_team'"
    );
    $pendingQuery->execute([$user['team_id']]);
    $pendingCount = (int) $pendingQuery->fetchColumn();
} elseif ($user['role'] === 'kepala') {
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'awaiting_head'")->fetchColumn();
}

page_header('Ringkasan', 'dashboard', $user, $pendingCount);
?>
<section class="welcome-banner">
    <div><span class="eyebrow eyebrow-light">DASHBOARD</span><h2>Halo, <?= h($user['name']) ?> <span class="wave">✦</span></h2><p><?= $user['role'] === 'pegawai' ? 'Pantau pengajuan izin dan ajukan kebutuhan izin Anda di sini.' : 'Berikut ringkasan aktivitas perizinan di kantor Anda.' ?></p></div>
    <div class="welcome-decoration"></div>
</section>

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
        <article class="stat-card"><span class="stat-icon icon-amber">◷</span><div><span>Menunggu Ketua Tim</span><strong><?= (int) ($stats['awaiting_team'] ?? 0) ?></strong></div><small>Dalam antrean tim</small></article>
        <article class="stat-card"><span class="stat-icon icon-purple">⌛</span><div><span>Menunggu Kepala</span><strong><?= (int) ($stats['awaiting_head'] ?? 0) ?></strong></div><small>Menunggu keputusan akhir</small></article>
        <article class="stat-card"><span class="stat-icon icon-green">✓</span><div><span>Disetujui</span><strong><?= (int) ($stats['approved'] ?? 0) ?></strong></div><small>Telah selesai diproses</small></article>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">AKTIVITAS TERBARU</span><h2><?= $user['role'] === 'pegawai' ? 'Pengajuan izin saya' : 'Pengajuan terbaru' ?></h2></div><a class="button button-outline" href="requests.php">Lihat semua <span aria-hidden="true">→</span></a></div>
    <?php request_table($recent, $showEmployee); ?>
</section>
<?php if ($user['role'] === 'pegawai'): ?>
    <a class="floating-cta" href="requests.php#buat-pengajuan"><span>+</span> Buat pengajuan izin</a>
<?php endif; ?>
<?php page_footer(); ?>
