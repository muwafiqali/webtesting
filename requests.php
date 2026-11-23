<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_login();
$pdo = db();
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($user['role'] === 'pegawai') {
    $where = 'WHERE u.id = ?';
    $params[] = $user['id'];
} elseif ($user['role'] === 'ketua_tim') {
    $where = 'WHERE u.team_id = ?';
    $params[] = $user['team_id'];
}

$count = $pdo->prepare(
    "SELECT COUNT(*) FROM leave_requests r JOIN users u ON u.id = r.user_id {$where}"
);
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$query = $pdo->prepare(
    "SELECT r.*, lt.name AS type_name, u.name AS employee_name, t.name AS team_name
     FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
     JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id
     {$where} ORDER BY r.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$query->execute($params);
$requests = $query->fetchAll();

$logsByRequest = [];
if ($requests) {
    $ids = array_column($requests, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $logQuery = $pdo->prepare(
        "SELECT a.*, u.name AS approver_name
         FROM approval_logs a JOIN users u ON u.id = a.approver_id
         WHERE a.request_id IN ({$marks}) ORDER BY a.created_at"
    );
    $logQuery->execute($ids);
    foreach ($logQuery->fetchAll() as $log) {
        $logsByRequest[$log['request_id']][] = $log;
    }
}

$types = $pdo->query('SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name')->fetchAll();
$pendingCount = 0;
if ($user['role'] === 'ketua_tim') {
    $pending = $pdo->prepare("SELECT COUNT(*) FROM leave_requests r JOIN users u ON u.id = r.user_id WHERE u.team_id = ? AND r.status = 'awaiting_team'");
    $pending->execute([$user['team_id']]);
    $pendingCount = (int) $pending->fetchColumn();
} elseif ($user['role'] === 'kepala') {
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'awaiting_head'")->fetchColumn();
}

page_header('Pengajuan Izin', 'requests', $user, $pendingCount);
?>
<div class="page-intro"><div><span class="eyebrow">MANAJEMEN IZIN</span><h2><?= $user['role'] === 'pegawai' ? 'Riwayat pengajuan' : 'Daftar pengajuan izin' ?></h2><p><?= $user['role'] === 'pegawai' ? 'Semua permohonan izin yang pernah Anda kirim.' : 'Pengajuan yang dapat Anda pantau sesuai akses peran.' ?></p></div><?php if ($user['role'] === 'pegawai'): ?><a class="button button-primary" href="#buat-pengajuan">+ Pengajuan baru</a><?php endif; ?></div>

<?php if ($user['role'] === 'pegawai'): ?>
<section class="panel" id="buat-pengajuan">
    <div class="panel-heading"><div><span class="eyebrow">FORMULIR</span><h2>Ajukan izin baru</h2></div></div>
    <p class="muted form-hint">Pengajuan akan dikirim ke Ketua Tim Anda untuk diperiksa terlebih dahulu.</p>
    <form action="actions.php" method="post" class="request-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_request">
        <label>Jenis izin
            <select name="leave_type_id" required><option value="">Pilih jenis izin</option><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>"><?= h($type['name']) ?></option><?php endforeach; ?></select>
        </label>
        <div class="form-row">
            <label>Tanggal mulai<input type="date" name="start_date" required></label>
            <label>Tanggal selesai<input type="date" name="end_date" required></label>
            <?php time_picker('start', 'Jam mulai', null, 'new-start'); ?>
            <?php time_picker('end', 'Jam selesai', null, 'new-end'); ?>
        </div>
        <label>Alasan pengajuan<textarea name="reason" minlength="15" maxlength="5000" rows="3" placeholder="Jelaskan alasan pengajuan izin Anda (minimal 15 karakter)" required></textarea></label>
        <div class="form-actions"><span class="muted">Pastikan tanggal dan informasi sudah benar.</span><button class="button button-primary" type="submit">Kirim pengajuan <span aria-hidden="true">→</span></button></div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading"><div><span class="eyebrow">RIWAYAT</span><h2>Semua pengajuan <span class="heading-count"><?= $total ?></span></h2></div></div>
    <?php if (!$requests): ?>
        <div class="empty-state"><span class="empty-icon">▤</span><strong>Belum ada pengajuan</strong><p>Pengajuan izin akan tampil di bagian ini.</p></div>
    <?php else: ?>
        <div class="request-list">
        <?php foreach ($requests as $request): ?>
            <article class="request-card" id="request-<?= (int) $request['id'] ?>">
                <div class="request-card-top">
                    <div class="request-title"><span class="request-icon">▤</span><div><h3><?= h($request['type_name']) ?></h3><span><?= h($request['request_code']) ?><?php if ($user['role'] !== 'pegawai'): ?> · <?= h($request['employee_name']) ?> (<?= h($request['team_name'] ?? 'Tanpa tim') ?>)<?php endif; ?></span></div></div>
                    <span class="status-pill <?= h(status_class($request['status'])) ?>"><?= h(status_label($request['status'])) ?></span>
                </div>
                <div class="request-meta"><span><small>PERIODE</small><?= h(date_id($request['start_date'])) ?> – <?= h(date_id($request['end_date'])) ?></span><span><small>JAM</small><?= h(time_id($request['start_time'])) ?> – <?= h(time_id($request['end_time'])) ?></span><span><small>DIAJUKAN</small><?= h(date_id($request['created_at'])) ?></span></div>
                <p class="request-reason"><?= nl2br(h($request['reason'])) ?></p>
                <?php if (!empty($logsByRequest[$request['id']])): ?>
                    <div class="timeline">
                        <?php foreach ($logsByRequest[$request['id']] as $log): ?>
                            <div class="timeline-item"><span class="timeline-dot <?= h(status_class($log['decision'])) ?>"></span><div><strong><?= h(role_label($log['stage'])) ?> · <?= h($log['approver_name']) ?></strong><small><?= h(status_label($log['decision'])) ?> · <?= h(date_id($log['created_at'])) ?></small><?php if ($log['note']): ?><p><?= nl2br(h($log['note'])) ?></p><?php endif; ?></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($user['role'] === 'pegawai' && $request['status'] === 'revision'): ?>
                    <details class="revision-editor"><summary>Perbaiki dan kirim ulang</summary>
                        <form action="actions.php" method="post" class="request-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="resubmit_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <label>Jenis izin<select name="leave_type_id" required><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" <?= (int) $type['id'] === (int) $request['leave_type_id'] ? 'selected' : '' ?>><?= h($type['name']) ?></option><?php endforeach; ?></select></label>
                            <div class="form-row">
                                <label>Tanggal mulai<input type="date" name="start_date" value="<?= h($request['start_date']) ?>" required></label>
                                <label>Tanggal selesai<input type="date" name="end_date" value="<?= h($request['end_date']) ?>" required></label>
                                <?php time_picker('start', 'Jam mulai', (string) $request['start_time'], 'revision-' . (int) $request['id'] . '-start'); ?>
                                <?php time_picker('end', 'Jam selesai', (string) $request['end_time'], 'revision-' . (int) $request['id'] . '-end'); ?>
                            </div>
                            <label>Alasan<textarea name="reason" minlength="15" maxlength="5000" rows="3" required><?= h($request['reason']) ?></textarea></label>
                            <button class="button button-primary" type="submit">Kirim perbaikan</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Navigasi halaman"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Sebelumnya</a><?php endif; ?><span>Halaman <?= $page ?> dari <?= $pages ?></span><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Berikutnya →</a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
