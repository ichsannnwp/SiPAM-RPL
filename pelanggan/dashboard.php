<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data profil pelanggan berdasarkan user_id dari tabel pelanggan
$stmt_pelanggan = $pdo->prepare("SELECT id, nama_lengkap, kode_pelanggan, alamat FROM pelanggan WHERE user_id = ?");
$stmt_pelanggan->execute([$user_id]);
$pelanggan = $stmt_pelanggan->fetch();

$nama_user = $pelanggan ? $pelanggan['nama_lengkap'] : $_SESSION['email'];
$inisial = strtoupper(substr($nama_user, 0, 1));

$total_belum_bayar = 0;
$tagihan_terbaru = [];

if ($pelanggan) {
    $pelanggan_id = $pelanggan['id'];
    
    // Hitung jumlah tagihan aktif (belum_bayar / tunggakan)
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM tagihan WHERE pelanggan_id = ? AND status IN ('belum_bayar', 'tunggakan')");
    $stmt_count->execute([$pelanggan_id]);
    $total_belum_bayar = $stmt_count->fetchColumn();

    // Tarik 5 histori tagihan terbaru pelanggan terkait
    $stmt_tagihan = $pdo->prepare("SELECT * FROM tagihan WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 5");
    $stmt_tagihan->execute([$pelanggan_id]);
    $tagihan_terbaru = $stmt_tagihan->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Dashboard Pelanggan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">SiPAM</div>
      <div class="subtitle">Halo, <?= htmlspecialchars($nama_user) ?></div>
    </div>
    <div class="header-avatar"><?= $inisial ?></div>
  </div>

  <div class="page">
    
    <?php if ($pelanggan): ?>
    <div style="background: white; padding: 16px; border-radius: 16px; margin-bottom: 16px; border: 1px solid var(--gray-200);">
        <div style="font-size: 11px; color: var(--gray-500); font-weight: 700; letter-spacing: 0.5px;">KODE PELANGGAN</div>
        <div style="font-size: 20px; font-weight: 800; color: #1a56db; margin-bottom: 6px;"><?= $pelanggan['kode_pelanggan'] ?></div>
        <div style="font-size: 13px; color: var(--gray-600);"><?= htmlspecialchars($pelanggan['alamat']) ?></div>
    </div>
    <?php endif; ?>

    <div class="summary-grid" style="grid-template-columns: 1fr;">
      <div class="summary-card <?= $total_belum_bayar > 0 ? 'orange' : 'green' ?>">
        <div class="label">Tagihan Perlu Diselesaikan</div>
        <div class="value"><?= $total_belum_bayar ?> Tagihan</div>
      </div>
    </div>

    <div class="section-title" style="margin-top:24px">Riwayat Tagihan Bulanan</div>
    
    <?php if (!empty($tagihan_terbaru)): ?>
        <?php foreach ($tagihan_terbaru as $t): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title">Periode <?= htmlspecialchars($t['periode']) ?></div>
            <div class="list-item-sub">Konsumsi: <?= $t['pemakaian_m3'] ?> m³</div>
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
    <?php else: ?>
        <div style="text-align: center; color: var(--gray-400); padding: 40px 0;">Tidak ditemukan riwayat pembayaran tagihan.</div>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="../auth/logout.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Keluar
    </a>
  </nav>

</div>
</body>
</html>