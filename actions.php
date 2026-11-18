<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}
verify_csrf();

function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function validate_request_fields(): array
{
    $typeId = filter_var($_POST['leave_type_id'] ?? null, FILTER_VALIDATE_INT);
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if (!$typeId || !valid_date($startDate) || !valid_date($endDate) || $endDate < $startDate) {
        throw new InvalidArgumentException('Jenis izin dan rentang tanggal harus diisi dengan benar.');
    }
    if (strlen($reason) < 15 || strlen($reason) > 5000) {
        throw new InvalidArgumentException('Alasan izin harus berisi 15–5.000 karakter.');
    }
    return [$typeId, $startDate, $endDate, $reason];
}

function ensure_no_overlap(PDO $pdo, int $userId, string $startDate, string $endDate, ?int $excludeId = null): void
{
    $sql = "SELECT COUNT(*) FROM leave_requests
            WHERE user_id = ? AND status <> 'rejected'
              AND start_date <= ? AND end_date >= ?";
    $params = [$userId, $endDate, $startDate];
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    if ((int) $statement->fetchColumn() > 0) {
        throw new InvalidArgumentException('Tanggal izin bertabrakan dengan pengajuan Anda yang masih aktif.');
    }
}

$action = (string) ($_POST['action'] ?? '');
$pdo = db();

try {
    if ($action === 'create_request') {
        if ($user['role'] !== 'pegawai') {
            throw new InvalidArgumentException('Hanya pegawai yang dapat membuat pengajuan izin.');
        }
        [$typeId, $startDate, $endDate, $reason] = validate_request_fields();
        $typeCheck = $pdo->prepare('SELECT id FROM leave_types WHERE id = ? AND is_active = 1');
        $typeCheck->execute([$typeId]);
        if (!$typeCheck->fetchColumn()) {
            throw new InvalidArgumentException('Jenis izin yang dipilih tidak tersedia.');
        }
        ensure_no_overlap($pdo, (int) $user['id'], $startDate, $endDate);
        $code = 'IZ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $statement = $pdo->prepare(
            "INSERT INTO leave_requests (request_code, user_id, leave_type_id, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, 'awaiting_team')"
        );
        $statement->execute([$code, $user['id'], $typeId, $startDate, $endDate, $reason]);
        flash('success', 'Pengajuan izin berhasil dikirim ke Ketua Tim.');
        redirect('requests.php');
    }

    if ($action === 'resubmit_request') {
        if ($user['role'] !== 'pegawai') {
            throw new InvalidArgumentException('Akses tidak diizinkan.');
        }
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$requestId) {
            throw new InvalidArgumentException('Pengajuan tidak valid.');
        }
        [$typeId, $startDate, $endDate, $reason] = validate_request_fields();
        $typeCheck = $pdo->prepare('SELECT id FROM leave_types WHERE id = ? AND is_active = 1');
        $typeCheck->execute([$typeId]);
        if (!$typeCheck->fetchColumn()) {
            throw new InvalidArgumentException('Jenis izin yang dipilih tidak tersedia.');
        }
        $ownerCheck = $pdo->prepare(
            "SELECT id FROM leave_requests WHERE id = ? AND user_id = ? AND status = 'revision'"
        );
        $ownerCheck->execute([$requestId, $user['id']]);
        if (!$ownerCheck->fetchColumn()) {
            throw new InvalidArgumentException('Pengajuan tidak ditemukan atau tidak sedang menunggu perbaikan.');
        }
        ensure_no_overlap($pdo, (int) $user['id'], $startDate, $endDate, (int) $requestId);
        $statement = $pdo->prepare(
            "UPDATE leave_requests
             SET leave_type_id = ?, start_date = ?, end_date = ?, reason = ?, status = 'awaiting_team'
             WHERE id = ? AND user_id = ? AND status = 'revision'"
        );
        $statement->execute([$typeId, $startDate, $endDate, $reason, $requestId, $user['id']]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Pengajuan berubah. Muat ulang halaman lalu coba kembali.');
        }
        flash('success', 'Perbaikan berhasil dikirim kembali ke Ketua Tim.');
        redirect('requests.php');
    }

    if ($action === 'review_request') {
        if (!in_array($user['role'], ['ketua_tim', 'kepala'], true)) {
            throw new InvalidArgumentException('Anda tidak memiliki hak persetujuan.');
        }
        $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($_POST['decision'] ?? '');
        $note = trim((string) ($_POST['note'] ?? ''));
        if (!$requestId || !in_array($decision, ['approved', 'rejected', 'revision'], true)) {
            throw new InvalidArgumentException('Keputusan persetujuan tidak valid.');
        }
        if ($decision !== 'approved' && strlen($note) < 8) {
            throw new InvalidArgumentException('Catatan minimal 8 karakter untuk penolakan atau permintaan perbaikan.');
        }

        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'SELECT r.id, r.status, u.team_id
             FROM leave_requests r JOIN users u ON u.id = r.user_id
             WHERE r.id = ? FOR UPDATE'
        );
        $statement->execute([$requestId]);
        $request = $statement->fetch();
        $expectedStatus = $user['role'] === 'ketua_tim' ? 'awaiting_team' : 'awaiting_head';
        if (!$request || $request['status'] !== $expectedStatus) {
            throw new InvalidArgumentException('Pengajuan tidak ditemukan atau sudah diproses.');
        }
        if ($user['role'] === 'ketua_tim' && (int) $request['team_id'] !== (int) $user['team_id']) {
            throw new InvalidArgumentException('Pengajuan ini bukan milik tim Anda.');
        }

        if ($decision === 'approved') {
            $nextStatus = $user['role'] === 'ketua_tim' ? 'awaiting_head' : 'approved';
        } else {
            $nextStatus = $decision;
        }
        $update = $pdo->prepare('UPDATE leave_requests SET status = ? WHERE id = ? AND status = ?');
        $update->execute([$nextStatus, $requestId, $expectedStatus]);
        if ($update->rowCount() !== 1) {
            throw new InvalidArgumentException('Pengajuan telah berubah. Muat ulang halaman lalu coba kembali.');
        }
        $log = $pdo->prepare(
            'INSERT INTO approval_logs (request_id, approver_id, stage, decision, note)
             VALUES (?, ?, ?, ?, ?)'
        );
        $log->execute([$requestId, $user['id'], $user['role'], $decision, $note ?: null]);
        $pdo->commit();
        flash('success', 'Keputusan berhasil disimpan.');
        redirect('approvals.php');
    }

    throw new InvalidArgumentException('Aksi tidak dikenali.');
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    $target = $action === 'review_request' ? 'approvals.php' : 'requests.php';
    redirect($target);
}
