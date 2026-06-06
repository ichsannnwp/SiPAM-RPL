<?php
session_start();
// Proteksi halaman: Hanya admin atau desa yang boleh masuk
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
  <style>
    body { background: #f3f4f6; font-family: sans-serif; padding: 20px; margin: 0; }
    .form-container { background: white; padding: 24px; border-radius: 12px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; font-size: 14px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; }
    .btn-submit { width: 100%; padding: 12px; background: #1a56db; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
    .btn-submit:hover { background: #1d4ed8; }
    .btn-back { display: inline-block; margin-bottom: 16px; color: #1a56db; text-decoration: none; font-size: 14px; }
  </style>
</head>
<body>

<div class="form-container">
  <a href="dashboard.php" class="btn-back">← Kembali ke Dashboard</a>
  <h2 style="margin: 0 0 6px 0;">Daftarkan Warga Baru</h2>
  <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px;">Data akun login dan profil pelanggan akan tersinkronisasi otomatis.</p>

  <?php if (isset($_GET['status']) && $_GET['status'] == 'sukses'): ?>
    <div style="background:#def7ec; color:#03543f; padding:12px; border-radius:6px; margin-bottom:16px; font-size:14px;">
      ✅ Warga baru berhasil didaftarkan secara dinamis!
    </div>
  <?php elseif (isset($_GET['error'])): ?>
    <div style="background:#fde8e8; color:#9b1c1c; padding:12px; border-radius:6px; margin-bottom:16px; font-size:14px;">
      ❌ Gagal: <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <form action="proses-tambah.php" method="POST">
    <!-- DATA AKUN (Tabel users) -->
    <div class="form-group">
      <label>Email Akun Warga</label>
      <input type="email" name="email" class="form-control" placeholder="contoh: budi@sipam.com" required>
    </div>
    <div class="form-group">
      <label>Password Login Warga</label>
      <input type="password" name="password" class="form-control" placeholder="Masukkan password untuk warga" required>
    </div>

    <hr style="border:0; border-top:1px solid #e5e7eb; margin:20px 0;">

    <!-- DATA PROFIL (Tabel pelanggan) -->
    <div class="form-group">
      <label>Kode Pelanggan (Nomor Meteran)</label>
      <input type="text" name="kode_pelanggan" class="form-control" placeholder="contoh: PLG-003" required>
    </div>
    <div class="form-group">
      <label>NIK (No. KTP)</label>
      <input type="text" name="nik" class="form-control" maxlength="16" placeholder="Format 16 digit angka" required>
    </div>
    <div class="form-group">
      <label>Nama Lengkap Warga</label>
      <input type="text" name="nama_lengkap" class="form-control" placeholder="bukan nama panggilan" required>
    </div>
    <div class="form-group">
      <label>No. Telepon / WhatsApp</label>
      <input type="text" name="no_telepon" class="form-control" placeholder="contoh: 0812345678" required>
    </div>
    <div class="form-group">
      <label>Alamat Rumah</label>
      <textarea name="alamat" class="form-control" rows="3" placeholder="Nama jalan, RT/RW, Dusun" required></textarea>
    </div>

    <button type="submit" class="btn-submit">Simpan & Daftarkan Warga</button>
  </form>
</div>

</body>
</html>