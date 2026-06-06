<?php
session_start();
require_once '../config/db.php';

// Proteksi halaman: Hanya Admin atau perangkat Desa
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Menghitung rekapitulasi data berdasarkan nama kolom sipam_db asli
$total_pelanggan = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status = 'aktif'")->fetchColumn();
$total_belum_bayar = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'belum_bayar'")->fetchColumn();
$total_tunggakan = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'tunggakan'")->fetchColumn();

// Menghitung total omset pendapatan dari riwayat pembayaran lunas
$total_pemasukan = $pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran")->fetchColumn();

// Mengambil 5 tagihan terbaru beserta profile pelanggan untuk feed
$tagihan_terbaru = $pdo->query("
    SELECT t.*, p.nama_lengkap, p.kode_pelanggan
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    ORDER BY t.id DESC LIMIT 5
")->fetchAll();

$inisial = strtoupper(substr($_SESSION['email'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Dashboard Admin — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">SiPAM Manajemen</div>
      <div class="subtitle">Otoritas akses: <strong><?= htmlspecialchars(strtoupper($_SESSION['role'])) ?></strong></div>
    </div>
    <div class="header-avatar"><?= $inisial ?></div>
  </div>

  <div class="page">

    <div class="summary-grid">
      <div class="summary-card blue">
        <div class="label">Pelanggan Aktif</div>
        <div class="value"><?= $total_pelanggan ?></div>
      </div>
      <div class="summary-card green">
        <div class="label">Total Pemasukan</div>
        <div class="value" style="font-size:14px">Rp <?= number_format($total_pemasukan,0,',','.') ?></div>
      </div>
      <div class="summary-card orange">
        <div class="label">Belum Bayar</div>
        <div class="value"><?= $total_belum_bayar ?></div>
      </div>
      <div class="summary-card red">
        <div class="label">Tunggakan</div>
        <div class="value"><?= $total_tunggakan ?></div>
      </div>
    </div>

    <div class="section-title">Menu Utama Fitur</div>
    
    <a href="tambah-pelanggan.php" class="list-item" style="border-left: 4px solid #10b981;">
      <div class="list-item-icon" style="background: #def7ec;">
        <svg fill="none" stroke="#03543f" stroke-width="2" viewBox="0 0 24 24" width="24" height="24"><path d="M18 9v6m3-3h-6M5 12a4 4 0 108 0 4 4 0 00-8 0v0zM2 20a7 7 0 0110.3 0"/></svg>
      </div>
      <div>
        <div class="list-item-title" style="color: #03543f; font-weight: 700;">Tambah Pelanggan Baru</div>
        <div class="list-item-sub">Daftarkan akun warga & meteran baru</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="#03543f" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <a href="pelanggan.php" class="list-item">
      <div class="list-item-icon blue">
        <svg fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <div>
        <div class="list-item-title">Data Master Pelanggan</div>
        <div class="list-item-sub">Lihat daftar, edit profil, & status warga</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <a href="meteran.php" class="list-item">
      <div class="list-item-icon orange">
        <svg fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      </div>
      <div>
        <div class="list-item-title">Input Catat Meteran</div>
        <div class="list-item-sub">Input volume pemakaian air bulanan</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <?php if (!empty($tagihan_terbaru)): ?>
    <div class="section-title" style="margin-top:24px">Arus Tagihan Terbaru</div>
    <?php foreach ($tagihan_terbaru as $t): ?>
    <div class="list-item">
      <div>
        <div class="list-item-title"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
        <div class="list-item-sub"><?= $t['kode_pelanggan'] ?> · Periode <?= htmlspecialchars($t['periode']) ?></div>
      </div>
      <div class="list-item-right">
        <div style="font-weight:600;font-size:14px">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
        <?php
          $badge = match($t['status']) {
            'lunas'        => 'badge-success',
            'tunggakan'    => 'badge-danger',
            default        => 'badge-warning'
          };
          $label = match($t['status']) {
            'lunas'        => 'Lunas',
            'tunggakan'    => 'Tunggakan',
            default        => 'Belum Dibayar'
          };
        ?>
        <span class="badge <?= $badge ?>" style="margin-top:4px"><?= $label ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item active">
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
    <a href="../auth/logout.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Keluar
    </a>
  </nav>

</div>
</body>
</html>