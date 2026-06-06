<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pesan = '';
$error = '';

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $pass_lama  = $_POST['password_lama'] ?? '';
    $pass_baru  = $_POST['password_baru'] ?? '';
    $pass_ulang = $_POST['password_ulang'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($pass_lama, $user['password'])) {
        $error = 'Password lama salah.';
    } elseif (strlen($pass_baru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($pass_baru !== $pass_ulang) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($pass_baru, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        $pesan = 'Password berhasil diperbarui.';
    }
}

// Data tarif aktif
$tarif = $pdo->query("SELECT * FROM master_tarif WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();

// Update tarif (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tarif']) && $_SESSION['role'] === 'admin') {
    $tarif_baru = (float)$_POST['tarif_baru'];
    if ($tarif_baru <= 0) {
        $error = 'Tarif harus lebih besar dari 0.';
    } else {
        $pdo->query("UPDATE master_tarif SET is_active=0");
        $pdo->prepare("INSERT INTO master_tarif (tarif_per_m3, berlaku_mulai, is_active) VALUES (?, CURDATE(), 1)")
            ->execute([$tarif_baru]);
        $pesan = 'Tarif berhasil diperbarui.';
        $tarif = $pdo->query("SELECT * FROM master_tarif WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
    }
}

$inisial = strtoupper(substr($_SESSION['email'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Profil & Pengaturan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Profil & Pengaturan</div>
      <div class="subtitle">Kelola akun dan konfigurasi sistem</div>
    </div>
    <div class="header-avatar"><?= $inisial ?></div>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Info akun -->
    <div class="card" style="padding:18px;margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
        <div style="width:52px;height:52px;border-radius:14px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:22px;color:var(--primary)"><?= $inisial ?></div>
        <div>
          <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($_SESSION['email']) ?></div>
          <span class="badge badge-info" style="margin-top:4px"><?= strtoupper($_SESSION['role']) ?></span>
        </div>
      </div>
    </div>

    <!-- Ganti Password -->
    <div class="section-title">Ganti Password</div>
    <div class="card">
      <form method="POST">
        <input type="hidden" name="ganti_password" value="1">
        <div class="form-group">
          <label class="form-label">Password Lama</label>
          <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password saat ini" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password Baru</label>
          <input type="password" name="password_baru" class="form-control" placeholder="Minimal 6 karakter" required>
        </div>
        <div class="form-group">
          <label class="form-label">Konfirmasi Password Baru</label>
          <input type="password" name="password_ulang" class="form-control" placeholder="Ulangi password baru" required>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Password</button>
      </form>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Pengaturan Tarif -->
    <div class="section-title">Master Tarif Air</div>
    <div class="card">
      <div style="background:var(--gray-50);border-radius:8px;padding:12px;margin-bottom:14px">
        <div style="font-size:12px;color:var(--gray-600)">Tarif Aktif Saat Ini</div>
        <div style="font-size:22px;font-weight:800;color:var(--primary);margin-top:2px">Rp <?= number_format($tarif['tarif_per_m3'] ?? 2500,0,',','.') ?> / m³</div>
        <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Berlaku sejak <?= $tarif ? date('d M Y', strtotime($tarif['berlaku_mulai'])) : '-' ?></div>
      </div>
      <form method="POST">
        <input type="hidden" name="update_tarif" value="1">
        <div class="form-group">
          <label class="form-label">Tarif Baru per m³ (Rp)</label>
          <input type="number" name="tarif_baru" class="form-control" placeholder="contoh: 2500" value="<?= $tarif['tarif_per_m3'] ?? 2500 ?>" min="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Tarif</button>
      </form>
    </div>
    <?php endif; ?>

    <a href="../auth/logout.php" class="btn" style="background:#fef2f2;color:var(--danger);font-weight:600;margin-top:4px">
      Keluar dari Sistem
    </a>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="pelanggan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Pelanggan
    </a>
    <a href="meteran.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      Meteran
    </a>
    <a href="notifikasi.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Notifikasi
    </a>
    <a href="profil.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profil
    </a>
  </nav>

</div>
</body>
</html>
