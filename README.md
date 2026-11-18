# IzinKantor

Portal internal pengajuan izin pegawai berbasis PHP dan MySQL/MariaDB. Alur persetujuan: **Pegawai → Ketua Tim → Kepala Kantor**.

## Menjalankan di Laragon

1. Pastikan Apache dan MySQL berjalan.
2. Buka phpMyAdmin atau HeidiSQL, lalu impor file `schema.sql`. File ini membuat database `izin_pegawai`, tabel, dan data demo.
3. Buka `http://localhost/bpstrenggalek/`.
4. Masuk menggunakan salah satu akun demo di bawah. Kata sandi seluruh akun demo adalah `password`.

| Peran | Username |
|---|---|
| Admin | `admin` |
| Kepala Kantor | `kepala` |
| Ketua Tim Statistik | `ketua.statistik` |
| Ketua Tim Administrasi | `ketua.admin` |
| Pegawai Tim Statistik | `rina`, `budi` |
| Pegawai Tim Administrasi | `andi`, `sari` |

## Konfigurasi database

Default koneksi untuk Laragon adalah host `127.0.0.1`, database `izin_pegawai`, username `root`, dan kata sandi kosong. Atur environment variable `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` bila konfigurasi MySQL berbeda.

## Fitur prototipe

- Login dengan session dan password hash.
- Dashboard ringkasan dengan cakupan data sesuai peran.
- Pegawai dapat membuat pengajuan dan mengirim ulang pengajuan yang diminta perbaikannya.
- Ketua Tim hanya dapat memproses pengajuan anggota timnya; Kepala Kantor memproses pengajuan yang telah lolos tahap Ketua Tim.
- Setiap keputusan dicatat dalam riwayat persetujuan.
- Admin dapat membuat akun dan menetapkan peran/tim.
- Formulir menggunakan token CSRF, query database menggunakan prepared statements, dan akses halaman diperiksa di server.

Prototipe ini belum mencakup unggah dokumen, notifikasi email, pengaturan kuota cuti, maupun pemulihan kata sandi. Ganti kata sandi akun demo dan aktifkan HTTPS sebelum aplikasi digunakan dengan data kantor sungguhan.
