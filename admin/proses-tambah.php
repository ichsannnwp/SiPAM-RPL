<?php
session_start();
require_once '../config/db.php';

// Proteksi akses skrip
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Menangkap data dari form secara dinamis
$email          = trim($_POST['email']);
$password_mentah= trim($_POST['password']);
$kode_pelanggan = trim($_POST['kode_pelanggan']);
$nik            = trim($_POST['nik']);
$nama_lengkap   = trim($_POST['nama_lengkap']);
$no_telepon     = trim($_POST['no_telepon']);
$alamat         = trim($_POST['alamat']);

try {
    // Memulai transaksi database agar jika salah satu gagal, semua dibatalkan (aman dari korup data)
    $pdo->beginTransaction();

    // 1. Enkripsi password dinamis kiriman admin menggunakan Bcrypt bawaan PHP
    $password_hash = password_hash($password_mentah, PASSWORD_DEFAULT);

    // 2. Simpan ke tabel users dengan role otomatis 'pelanggan'
    $stmt_user = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'pelanggan')");
    $stmt_user->execute([$email, $password_hash]);
    
    // 3. Ambil ID user yang barusan sukses ter-generate secara otomatis
    $user_id_terbaru = $pdo->lastInsertId();

    // 4. Simpan ke tabel pelanggan, hubungkan menggunakan $user_id_terbaru
    $stmt_plg = $pdo->prepare("INSERT INTO pelanggan (kode_pelanggan, nik, nama_lengkap, alamat, no_telepon, status, user_id) VALUES (?, ?, ?, ?, ?, 'aktif', ?)");
    $stmt_plg->execute([$kode_pelanggan, $nik, $nama_lengkap, $alamat, $no_telepon, $user_id_terbaru]);

    // Jika semua proses di atas lancar tanpa hambatan, kunci perubahan ke database
    $pdo->commit();
    
    // Redirect kembali ke form dengan status sukses
    header('Location: tambah-pelanggan.php?status=sukses');
    exit;

} catch (PDOException $e) {
    // Jika di tengah jalan ada error (misal NIK atau Email duplikat), batalkan semua rencana penyimpanan
    $pdo->rollBack();
    
    // Kembalikan ke halaman form dengan membawa pesan error sistem
    header('Location: tambah-pelanggan.php?error=' . urlencode($e->getMessage()));
    exit;
}