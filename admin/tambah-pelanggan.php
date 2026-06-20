<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tambah Pelanggan Baru — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Daftar Pelanggan Baru</div>
      <div class="subtitle">Input data akun dan profil warga</div>
    </div>
    <a href="dashboard.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
  </div>

  <div class="page">

    <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
      <div class="alert alert-success">✅ Warga baru berhasil didaftarkan! Pelanggan sudah bisa login.</div>
    <?php elseif (isset($_GET['error'])): ?>
      <div class="alert alert-danger">❌ Gagal: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="section-title" style="margin-top:0;margin-bottom:14px">Data Akun Login</div>
      <form action="proses-tambah.php" method="POST">

        <div class="form-group">
          <label class="form-label">Email Akun Warga</label>
          <input type="email" name="email" class="form-control" placeholder="contoh: budi@sipam.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password Login Warga</label>
          <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
        </div>

        <div style="border-top:1px solid var(--gray-200);margin:18px 0 16px"></div>
        <div class="section-title" style="margin-top:0;margin-bottom:14px">Data Profil Pelanggan</div>

        <div class="form-group">
          <label class="form-label">Kode Pelanggan</label>
          <input type="text" name="kode_pelanggan" class="form-control" placeholder="contoh: PLG-001" required>
        </div>
        <div class="form-group">
          <label class="form-label">NIK (No. KTP — 16 digit)</label>
          <input type="text" name="nik" class="form-control" maxlength="16" minlength="16" pattern="\d{16}" inputmode="numeric" placeholder="Format 16 digit angka" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama sesuai KTP" required minlength="3">
        </div>
        <div class="form-group">
          <label class="form-label">No. Telepon / WhatsApp</label>
          <input type="text" name="no_telepon" class="form-control" placeholder="contoh: 08123456789" inputmode="numeric" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat Rumah</label>
          <textarea name="alamat" class="form-control" rows="3" placeholder="Nama jalan, RT/RW, Dusun" required></textarea>
        </div>

        <div style="background:var(--primary-light);border-radius:8px;padding:10px 12px;margin-bottom:16px;font-size:13px;color:var(--primary)">
          💡 Pelanggan baru otomatis mendapat <strong>bebas tagihan bulan pertama</strong> sesuai kebijakan PAM.
        </div>

        <button type="submit" class="btn btn-primary">Simpan & Daftarkan Pelanggan</button>
      </form>
    </div>
  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="pelanggan.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Pelanggan
    </a>
    <a href="meteran.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      Meteran
    </a>
    <a href="laporan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
    <a href="../auth/logout.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Keluar
    </a>
  </nav>

</div>
</body>
</html>
