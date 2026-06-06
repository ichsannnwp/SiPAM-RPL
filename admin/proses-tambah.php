<?php
session_start();
require_once '../config/db.php';

// Proteksi akses
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$email          = trim($_POST['email'] ?? '');
$password_mentah= trim($_POST['password'] ?? '');
$kode_pelanggan = trim($_POST['kode_pelanggan'] ?? '');
$nik            = trim($_POST['nik'] ?? '');
$nama_lengkap   = trim($_POST['nama_lengkap'] ?? '');
$no_telepon     = trim($_POST['no_telepon'] ?? '');
$alamat         = trim($_POST['alamat'] ?? '');

// Validasi dasar
if (strlen($nik) !== 16 || !ctype_digit($nik)) {
    header('Location: tambah-pelanggan.php?error=' . urlencode('NIK harus 16 digit angka'));
    exit;
}
if (strlen($nama_lengkap) < 3) {
    header('Location: tambah-pelanggan.php?error=' . urlencode('Nama minimal 3 karakter'));
    exit;
}
if (strlen($no_telepon) < 10 || !ctype_digit($no_telepon)) {
    header('Location: tambah-pelanggan.php?error=' . urlencode('Format nomor telepon tidak valid (minimal 10 digit angka)'));
    exit;
}
if (empty($alamat)) {
    header('Location: tambah-pelanggan.php?error=' . urlencode('Alamat wajib diisi'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Hash password dengan bcrypt
    $password_hash = password_hash($password_mentah, PASSWORD_BCRYPT);

    // Simpan ke tabel users dengan role pelanggan
    $stmt_user = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'pelanggan')");
    $stmt_user->execute([$email, $password_hash]);
    $user_id_terbaru = $pdo->lastInsertId();

    // Simpan ke tabel pelanggan
    $stmt_plg = $pdo->prepare("INSERT INTO pelanggan (kode_pelanggan, nik, nama_lengkap, alamat, no_telepon, status, free_period, tanggal_bergabung, user_id) VALUES (?, ?, ?, ?, ?, 'aktif', 1, CURDATE(), ?)");
    $stmt_plg->execute([$kode_pelanggan, $nik, $nama_lengkap, $alamat, $no_telepon, $user_id_terbaru]);

    $pdo->commit();
    header('Location: tambah-pelanggan.php?status=sukses');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    $pesan_error = $e->getMessage();
    // Pesan ramah pengguna untuk duplikat
    if (strpos($pesan_error, 'Duplicate entry') !== false) {
        if (strpos($pesan_error, 'email') !== false) {
            $pesan_error = 'Email sudah digunakan oleh pelanggan lain';
        } elseif (strpos($pesan_error, 'nik') !== false) {
            $pesan_error = 'NIK sudah terdaftar sebagai pelanggan aktif';
        } elseif (strpos($pesan_error, 'kode_pelanggan') !== false) {
            $pesan_error = 'Kode pelanggan sudah digunakan';
        }
    }
    header('Location: tambah-pelanggan.php?error=' . urlencode($pesan_error));
    exit;
}
