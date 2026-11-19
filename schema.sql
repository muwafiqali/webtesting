CREATE DATABASE IF NOT EXISTS izin_pegawai
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE izin_pegawai;

CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    nip VARCHAR(30) NULL UNIQUE,
    address VARCHAR(500) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'kepala', 'ketua_tim', 'pegawai') NOT NULL,
    team_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_team FOREIGN KEY (team_id) REFERENCES teams(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_users_role_team (role, team_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS leave_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NOT NULL DEFAULT '00:00:00',
    end_time TIME NOT NULL DEFAULT '00:00:00',
    reason TEXT NOT NULL,
    status ENUM('awaiting_team', 'awaiting_head', 'revision', 'approved', 'rejected')
        NOT NULL DEFAULT 'awaiting_team',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_requests_type FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_requests_user_status (user_id, status),
    INDEX idx_requests_status_created (status, created_at),
    INDEX idx_requests_period (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS approval_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    stage ENUM('ketua_tim', 'kepala') NOT NULL,
    decision ENUM('approved', 'rejected', 'revision') NOT NULL,
    note VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_approval_request FOREIGN KEY (request_id) REFERENCES leave_requests(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_approval_user FOREIGN KEY (approver_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_approval_request (request_id, created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO teams (id, name) VALUES
    (1, 'Tim Statistik'),
    (2, 'Tim Administrasi');

-- Semua akun demo memakai kata sandi: password
-- Hash dibuat dengan password_hash('password', PASSWORD_DEFAULT).
INSERT IGNORE INTO users (id, name, username, email, nip, password_hash, role, team_id) VALUES
    (1, 'Administrator Sistem', 'admin', 'admin@kantor.test', '198001012005011001', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'admin', NULL),
    (2, 'Dewi Puspitasari, S.ST.', 'kepala', 'dewi@kantor.test', '197805122000032001', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'kepala', NULL),
    (3, 'Arif Nugroho, S.Si.', 'ketua.statistik', 'arif@kantor.test', '198408152009011004', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'ketua_tim', 1),
    (4, 'Rina Lestari, A.Md.', 'rina', 'rina@kantor.test', '199401152019032006', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'pegawai', 1),
    (5, 'Budi Santoso, S.E.', 'budi', 'budi@kantor.test', '199003102018011003', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'pegawai', 1),
    (6, 'Maya Anggraini, S.Tr.', 'ketua.admin', 'maya@kantor.test', '198610222010012002', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'ketua_tim', 2),
    (7, 'Andi Pratama, A.Md.', 'andi', 'andi@kantor.test', '199512082020121005', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'pegawai', 2),
    (8, 'Sari Wulandari, S.E.', 'sari', 'sari@kantor.test', '199207052017022004', '$2y$10$1N8r92yfMjduHDN9hxpsR.SlPoV8KxrMECGXK1wGUTRg1a4lMv..y', 'pegawai', 2);

INSERT IGNORE INTO leave_types (id, name, description) VALUES
    (1, 'Cuti Tahunan', 'Pengajuan cuti tahunan pegawai.'),
    (2, 'Izin Keperluan Pribadi', 'Izin untuk keperluan pribadi.'),
    (3, 'Izin Sakit', 'Izin tidak masuk karena sakit.'),
    (4, 'Dinas Luar', 'Izin untuk melaksanakan tugas kedinasan di luar kantor.');

INSERT IGNORE INTO leave_requests
    (id, request_code, user_id, leave_type_id, start_date, end_date, reason, status, created_at)
VALUES
    (1, 'IZ-DEMO-0001', 4, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'Mengurus keperluan keluarga yang sudah terjadwal.', 'awaiting_team', DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (2, 'IZ-DEMO-0002', 5, 2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Menghadiri keperluan keluarga di luar kota.', 'awaiting_head', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (3, 'IZ-DEMO-0003', 8, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Cuti untuk keperluan keluarga.', 'approved', DATE_SUB(NOW(), INTERVAL 25 DAY)),
    (4, 'IZ-DEMO-0004', 7, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Pemulihan kesehatan sesuai anjuran dokter.', 'revision', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT IGNORE INTO approval_logs (id, request_id, approver_id, stage, decision, note, created_at) VALUES
    (1, 2, 3, 'ketua_tim', 'approved', 'Jadwal pekerjaan tim sudah disesuaikan.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (2, 3, 6, 'ketua_tim', 'approved', 'Disetujui, pekerjaan sudah dikoordinasikan.', DATE_SUB(NOW(), INTERVAL 24 DAY)),
    (3, 3, 2, 'kepala', 'approved', 'Disetujui.', DATE_SUB(NOW(), INTERVAL 23 DAY)),
    (4, 4, 6, 'ketua_tim', 'revision', 'Mohon lampirkan keterangan pendukung dan konfirmasi tanggal izin.', DATE_SUB(NOW(), INTERVAL 1 DAY));
