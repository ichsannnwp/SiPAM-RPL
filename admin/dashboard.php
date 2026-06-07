<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Rekapitulasi data
$total_pelanggan    = $pdo->query("SELECT COUNT(*) FROM pelanggan WHERE status = 'aktif'")->fetchColumn();
$total_belum_bayar  = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'belum_bayar'")->fetchColumn();
$total_tunggakan    = $pdo->query("SELECT COUNT(*) FROM tagihan WHERE status = 'tunggakan'")->fetchColumn();
$total_pemasukan    = $pdo->query("SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran")->fetchColumn();

// Hitung pelanggan perlu eskalasi (>= 3 bulan)
$perlu_eskalasi = $pdo->query("
    SELECT COUNT(DISTINCT pelanggan_id) FROM (
        SELECT pelanggan_id, COUNT(*) as jml
        FROM tagihan WHERE status IN ('belum_bayar','tunggakan')
        GROUP BY pelanggan_id HAVING jml >= 3
    ) x
")->fetchColumn();

// Auto-update tunggakan yang sudah lewat jatuh tempo
$pdo->query("UPDATE tagihan SET status='tunggakan' WHERE status='belum_bayar' AND jatuh_tempo < CURDATE()");

// 5 tagihan terbaru
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    body { background: #0f172a; }
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 50% at 15% 0%, rgba(12,110,242,0.30) 0%, transparent 55%),
        radial-gradient(ellipse 60% 40% at 85% 10%, rgba(99,102,241,0.20) 0%, transparent 50%);
      z-index: 0;
      pointer-events: none;
    }
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background-image: radial-gradient(circle, rgba(255,255,255,0.035) 1px, transparent 1px);
      background-size: 28px 28px;
      z-index: 0;
      pointer-events: none;
    }
    .app { position: relative; z-index: 1; }
    .header {
      background: rgba(0, 65, 194, 0.85) !important;
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      box-shadow: 0 2px 20px rgba(0,0,0,0.35) !important;
    }
  </style>
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">SiPAM Manajemen</div>
      <div class="subtitle">Peran: <strong><?= htmlspecialchars(strtoupper($_SESSION['role'])) ?></strong> · <?= date('d M Y') ?></div>
    </div>
    <a href="profil.php" class="header-avatar" style="text-decoration:none;color:white"><?= $inisial ?></a>
  </div>

  <div class="page">

    <!-- Statistik -->
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

    <?php if ($perlu_eskalasi > 0): ?>
    <a href="eskalasi.php" style="display:flex;align-items:center;gap:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px 14px;margin-bottom:12px;text-decoration:none">
      <svg fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" width="20" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div>
        <div style="font-weight:700;color:#dc2626;font-size:13px"><?= $perlu_eskalasi ?> pelanggan perlu eskalasi</div>
        <div style="font-size:12px;color:#7f1d1d">Tunggakan ≥ 3 bulan → Tap untuk proses</div>
      </div>
      <svg width="16" height="16" fill="none" stroke="#dc2626" stroke-width="2" viewBox="0 0 24 24" style="margin-left:auto;flex-shrink:0"><path d="M9 18l6-6-6-6"/></svg>
    </a>
    <?php endif; ?>

    <div class="section-title">Menu Utama</div>

    <a href="tambah-pelanggan.php" class="list-item" style="border-left:4px solid #10b981">
      <div class="list-item-icon" style="background:#def7ec">
        <svg fill="none" stroke="#03543f" stroke-width="2" viewBox="0 0 24 24" width="24"><path d="M18 9v6m3-3h-6M5 12a4 4 0 108 0 4 4 0 00-8 0v0zM2 20a7 7 0 0110.3 0"/></svg>
      </div>
      <div>
        <div class="list-item-title" style="color:#03543f;font-weight:700">Tambah Pelanggan Baru</div>
        <div class="list-item-sub">Daftarkan akun warga & meteran baru</div>
      </div>
      <div class="list-item-right">
        <svg width="16" height="16" fill="none" stroke="#03543f" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
    </a>
  
    <a href="pelanggan.php" class="list-item"  style="border-left:4px solid #1a56db">
      <div class="list-item-icon blue">
        <svg fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      </div>
      <div>
        <div class="list-item-title">Data Pelanggan</div>
        <div class="list-item-sub">Lihat, edit, dan kelola data warga</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <a href="meteran.php" class="list-item" style="border-left:4px solid #d97706">
      <div class="list-item-icon orange">
        <svg fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      </div>
      <div>
        <div class="list-item-title">Input Meteran Bulanan</div>
        <div class="list-item-sub">Catat angka pemakaian air bulanan</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <a href="tagihan.php" class="list-item" style="border-left:4px solid #7c3aed">
      <div class="list-item-icon" style="background:#ede9fe">
        <svg fill="none" stroke="#7c3aed" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div>
        <div class="list-item-title">Manajemen Tagihan</div>
        <div class="list-item-sub">Kelola status tagihan pelanggan</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <a href="pembayaran.php" class="list-item" style="border-left:4px solid #059669">
      <div class="list-item-icon" style="background:#d1fae5">
        <svg fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <div>
        <div class="list-item-title">Catat Pembayaran</div>
        <div class="list-item-sub">Input pembayaran & terbitkan kuitansi</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <a href="tunggakan.php" class="list-item" style="border-left:4px solid #ff0000">
      <div class="list-item-icon" style="background:var(--danger-light)">
        <svg fill="none" stroke="var(--danger)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div>
        <div class="list-item-title">Data Tunggakan</div>
        <div class="list-item-sub">Pantau & eskalasi tunggakan kritis</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <a href="laporan.php" class="list-item" style="border-left:4px solid #4967f0">
      <div class="list-item-icon" style="background:var(--primary-light)">
        <svg fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div>
        <div class="list-item-title">Laporan Keuangan</div>
        <div class="list-item-sub">Laporan bulanan & tahunan PAM</div>
      </div>
      <div class="list-item-right"><svg width="16" height="16" fill="none" stroke="var(--gray-400)" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></div>
    </a>

    <?php if (!empty($tagihan_terbaru)): ?>
    <div class="section-title" style="margin-top:24px">Tagihan Terbaru</div>
    <?php foreach ($tagihan_terbaru as $t): ?>
    <div class="list-item">
      <div>
        <div class="list-item-title"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
        <div class="list-item-sub"><?= $t['kode_pelanggan'] ?> · Periode <?= htmlspecialchars($t['periode']) ?></div>
      </div>
      <div class="list-item-right">
        <div style="font-weight:600;font-size:14px">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
        <?php
          $badge = match($t['status']) { 'lunas'=>'badge-success','tunggakan'=>'badge-danger',default=>'badge-warning' };
          $label = match($t['status']) { 'lunas'=>'Lunas','tunggakan'=>'Tunggakan',default=>'Belum Bayar' };
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
    <a href="tunggakan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Tunggakan
    </a>
    <a href="laporan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
  </nav>

</div>
</body>
</html>
