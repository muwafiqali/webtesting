<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_role(['ketua_tim', 'kepala']);
$pdo = db();

if ($user['role'] === 'ketua_tim') {
    $query = $pdo->prepare(
        "SELECT r.*, lt.name AS type_name, u.name AS employee_name, u.nip,
                t.name AS team_name
         FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
         JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id
         WHERE r.status = 'awaiting_team' AND u.team_id = ?
         ORDER BY r.created_at ASC LIMIT 100"
    );
    $query->execute([$user['team_id']]);
    $stage = 'Ketua Tim';
    $pendingCountQuery = $pdo->prepare("SELECT COUNT(*) FROM leave_requests r JOIN users u ON u.id = r.user_id WHERE r.status = 'awaiting_team' AND u.team_id = ?");
    $pendingCountQuery->execute([$user['team_id']]);
    $pendingCount = (int) $pendingCountQuery->fetchColumn();
} else {
    $query = $pdo->query(
        "SELECT r.*, lt.name AS type_name, u.name AS employee_name, u.nip,
                t.name AS team_name
         FROM leave_requests r JOIN leave_types lt ON lt.id = r.leave_type_id
         JOIN users u ON u.id = r.user_id LEFT JOIN teams t ON t.id = u.team_id
         WHERE r.status = 'awaiting_head'
         ORDER BY r.created_at ASC LIMIT 100"
    );
    $stage = 'Kepala Kantor';
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'awaiting_head'")->fetchColumn();
}
$requests = $query->fetchAll();
page_header('Persetujuan', 'approvals', $user, $pendingCount);
?>
<div class="page-intro"><div><span class="eyebrow">ANTREAN TINDAKAN</span><h2>Menunggu persetujuan Anda</h2><p>Periksa informasi pengajuan sebelum memberikan keputusan sebagai <?= h($stage) ?>.</p></div><span class="status-pill status-waiting"><?= count($requests) ?> menunggu</span></div>

<?php if (!$requests): ?>
    <section class="panel"><div class="empty-state"><span class="empty-icon">✓</span><strong>Antrean sudah kosong</strong><p>Belum ada pengajuan yang menunggu persetujuan Anda.</p></div></section>
<?php else: ?>
    <div class="approval-list">
    <?php foreach ($requests as $request): ?>
        <article class="panel approval-card">
            <div class="approval-card-head"><div class="request-title"><span class="request-icon">▤</span><div><h2><?= h($request['type_name']) ?></h2><span><?= h($request['request_code']) ?></span></div></div><span class="status-pill status-waiting">Perlu ditinjau</span></div>
            <div class="employee-summary"><span class="avatar"><?= h(substr($request['employee_name'], 0, 1)) ?></span><div><strong><?= h($request['employee_name']) ?></strong><small>NIP <?= h($request['nip'] ?: '-') ?> · <?= h($request['team_name'] ?? 'Tanpa tim') ?></small></div></div>
            <div class="approval-details"><div><small>PERIODE IZIN</small><strong><?= h(date_id($request['start_date'])) ?> – <?= h(date_id($request['end_date'])) ?></strong></div><div><small>TANGGAL PENGAJUAN</small><strong><?= h(date_id($request['created_at'])) ?></strong></div></div>
            <div class="reason-box"><small>ALASAN PEGAWAI</small><p><?= nl2br(h($request['reason'])) ?></p></div>
            <form action="actions.php" method="post" class="review-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="review_request"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <label>Catatan keputusan <span class="muted">(wajib untuk menolak atau meminta perbaikan)</span><textarea name="note" rows="2" maxlength="1000" placeholder="Tambahkan catatan untuk pegawai"></textarea></label>
                <div class="review-actions"><button class="button button-danger-outline" type="submit" name="decision" value="rejected" onclick="return confirm('Tolak pengajuan ini?')">Tolak</button><button class="button button-outline" type="submit" name="decision" value="revision">Minta perbaikan</button><button class="button button-primary" type="submit" name="decision" value="approved" onclick="return confirm('Setujui pengajuan ini?')">Setujui <span aria-hidden="true">→</span></button></div>
            </form>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
