<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$total_pelanggan = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status = 'aktif'")->fetchColumn();
$total_belum_bayar = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'belum_bayar'")->fetchColumn();
$total_tunggakan = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'tunggakan'")->fetchColumn();
$total_pemasukan = $pdo->query("SELECT COALESCE(SUM(jumlah_bayar),0) FROM pembayaran WHERE MONTH(tanggal_bayar)=MONTH(NOW()) AND YEAR(tanggal_bayar)=YEAR(NOW())")->fetchColumn();

$tagihan_terbaru = $pdo->query("
    SELECT t.*, p.nama_lengkap, p.kode_pelanggan
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    ORDER BY t.created_at DESC LIMIT 5
")->fetchAll();

$inisial = strtoupper(substr($_SESSION['email'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Dashboard — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="h1">SiPAM</div>
      <div class="subtitle">Selamat datang, <?= htmlspecialchars($_SESSION['role']) ?></div>
    </div>
    <div class="header-avatar"><?= $inisial ?></div>
  </div>

  <!-- Content -->
  <div class="page">

    <!-- Summary grid -->
    <div class="summary-grid">
      <div class="summary-card blue">
        <div class="label">Pelanggan aktif</div>
        <div class="value"><?= $total_pelanggan ?></div>
      </div>
      <div class="summary-card green">
        <div class="label">Pemasukan bulan ini</div>
        <div class="value" style="font-size:15px">Rp <?= number_format($total_pemasukan,0,',','.') ?></div>
      </div>
      <div class="summary-card orange">
        <div class="label">Belum bayar</div>
        <div class="value"><?= $total_belum_bayar ?></div>
      </div>
      <div class="summary-card red">
        <div class="label">Tunggakan</div>
        <div class="value"><?= $total_tunggakan ?></div>
      </div>
    </div>

    <!-- Menu utama -->
    <div class="section-title">Menu</div>
    <a href="pelanggan.php" class="list-item">
      <div class="list-item-icon blue">
        <svg fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <div>
        <div class="list-item-title">Data Pelanggan</div>
        <div class="list-item-sub">Kelola data pelanggan aktif</div>
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
        <div class="list-item-title">Input Meteran</div>
        <div class="list-item-sub">Catat angka meteran bulanan</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <a href="pembayaran.php" class="list-item">
      <div class="list-item-icon green">
        <svg fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
      </div>
      <div>
        <div class="list-item-title">Pembayaran</div>
        <div class="list-item-sub">Catat pembayaran tagihan</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <a href="laporan.php" class="list-item">
      <div class="list-item-icon blue">
        <svg fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div>
        <div class="list-item-title">Laporan Keuangan</div>
        <div class="list-item-sub">Laporan bulanan & tahunan</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>

    <!-- Tagihan terbaru -->
    <?php if (!empty($tagihan_terbaru)): ?>
    <div class="section-title" style="margin-top:8px">Tagihan terbaru</div>
    <?php foreach ($tagihan_terbaru as $t): ?>
    <div class="list-item">
      <div>
        <div class="list-item-title"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
        <div class="list-item-sub"><?= $t['kode_pelanggan'] ?> · <?= $t['periode'] ?></div>
      </div>
      <div class="list-item-right">
        <div style="font-weight:600;font-size:14px">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
        <?php
          $badge = match($t['status']) {
            'lunas'      => 'badge-success',
            'tunggakan'  => 'badge-danger',
            default      => 'badge-warning'
          };
          $label = match($t['status']) {
            'lunas'      => 'Lunas',
            'tunggakan'  => 'Tunggakan',
            default      => 'Belum bayar'
          };
        ?>
        <span class="badge <?= $badge ?>" style="margin-top:4px"><?= $label ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <!-- Bottom navigation -->
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