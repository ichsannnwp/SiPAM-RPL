<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: ../auth/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, nama_lengkap, kode_pelanggan, alamat, no_telepon, free_period, status FROM pelanggan WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$pelanggan = $stmt->fetch();

$nama_user = $pelanggan ? $pelanggan['nama_lengkap'] : $_SESSION['email'];
$inisial = strtoupper(substr($nama_user, 0, 1));

$tagihan_aktif = null;
$riwayat = [];
if ($pelanggan) {
    $pid = $pelanggan['id'];
    $stmt2 = $pdo->prepare("SELECT * FROM tagihan WHERE pelanggan_id=? AND status IN ('belum_bayar','tunggakan') ORDER BY periode DESC LIMIT 1");
    $stmt2->execute([$pid]);
    $tagihan_aktif = $stmt2->fetch();

    $stmt3 = $pdo->prepare("SELECT t.*, pb.tanggal_bayar FROM tagihan t LEFT JOIN pembayaran pb ON t.id=pb.tagihan_id WHERE t.pelanggan_id=? ORDER BY t.periode DESC LIMIT 6");
    $stmt3->execute([$pid]);
    $riwayat = $stmt3->fetchAll();
}
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

  <div class="header">
    <div>
      <div class="h1">SiPAM</div>
      <div class="subtitle">Halo, <?= htmlspecialchars($nama_user) ?></div>
    </div>
    <div class="header-avatar"><?= $inisial ?></div>
  </div>

  <div class="page">

    <?php if ($pelanggan): ?>
    <!-- Info pelanggan -->
    <div style="background:linear-gradient(135deg,#1a56db,#1d4ed8);color:white;padding:18px;border-radius:16px;margin-bottom:12px">
      <div style="font-size:11px;opacity:0.7;letter-spacing:0.5px">KODE PELANGGAN</div>
      <div style="font-size:22px;font-weight:800;margin:2px 0"><?= $pelanggan['kode_pelanggan'] ?></div>
      <div style="font-size:12px;opacity:0.8"><?= htmlspecialchars($pelanggan['alamat']) ?></div>
      <?php if ($pelanggan['free_period']): ?>
      <div style="margin-top:8px;background:rgba(255,255,255,0.15);border-radius:6px;padding:6px 10px;font-size:12px;display:inline-block">
        ✨ Periode bebas tagihan aktif
      </div>
      <?php endif; ?>
    </div>

    <!-- Tagihan aktif -->
    <?php if ($tagihan_aktif): ?>
    <div style="background:white;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:var(--shadow);border-left:4px solid <?= $tagihan_aktif['status']==='tunggakan'?'var(--danger)':'var(--warning)' ?>">
      <div style="font-size:12px;color:var(--gray-600);margin-bottom:6px">Tagihan <?= date('F Y', strtotime($tagihan_aktif['periode'].'-01')) ?></div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div style="font-size:24px;font-weight:800;color:var(--gray-800)">Rp <?= number_format($tagihan_aktif['total_tagihan'],0,',','.') ?></div>
          <div style="font-size:12px;color:var(--gray-600);margin-top:2px">Pemakaian: <?= $tagihan_aktif['pemakaian_m3'] ?> m³</div>
        </div>
        <span class="badge <?= $tagihan_aktif['status']==='tunggakan'?'badge-danger':'badge-warning' ?>">
          <?= $tagihan_aktif['status']==='tunggakan'?'Tunggakan':'Belum Bayar' ?>
        </span>
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--gray-600)">Jatuh tempo: <?= date('d M Y', strtotime($tagihan_aktif['jatuh_tempo'])) ?></div>
      <div style="margin-top:10px;background:var(--primary-light);color:var(--primary);padding:10px 12px;border-radius:8px;font-size:13px;text-align:center;font-weight:600">
        Bayar ke bendahara PAM Swadaya
      </div>
    </div>
    <?php else: ?>
    <div style="background:var(--success-light);border-radius:16px;padding:16px;margin-bottom:12px;display:flex;gap:12px;align-items:center">
      <svg fill="none" stroke="var(--success)" stroke-width="2" viewBox="0 0 24 24" width="28"><polyline points="20 6 9 17 4 12"/></svg>
      <div>
        <div style="font-weight:600;color:var(--success)">Semua tagihan lunas!</div>
        <div style="font-size:12px;color:var(--success);opacity:0.8">Tidak ada tagihan yang perlu dibayar.</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Riwayat -->
    <div class="section-title">Riwayat Tagihan</div>
    <?php if (empty($riwayat)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:24px 0;font-size:13px">Belum ada riwayat tagihan.</div>
    <?php else: ?>
    <?php foreach ($riwayat as $r): ?>
    <div class="list-item">
      <div style="width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
        background:<?= $r['status']==='lunas'?'var(--success-light)':($r['status']==='tunggakan'?'var(--danger-light)':'var(--warning-light)') ?>;
        color:<?= $r['status']==='lunas'?'var(--success)':($r['status']==='tunggakan'?'var(--danger)':'var(--warning)') ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px"><?= date('F Y', strtotime($r['periode'].'-01')) ?></div>
        <div style="font-size:12px;color:var(--gray-600)"><?= $r['pemakaian_m3'] ?> m³<?= $r['tanggal_bayar'] ? ' · Dibayar '.date('d M Y', strtotime($r['tanggal_bayar'])) : '' ?></div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-weight:700;font-size:13px">Rp <?= number_format($r['total_tagihan'],0,',','.') ?></div>
        <?php $badge=match($r['status']){'lunas'=>'badge-success','tunggakan'=>'badge-danger',default=>'badge-warning'};
              $label=match($r['status']){'lunas'=>'Lunas','tunggakan'=>'Tunggakan',default=>'Belum Bayar'}; ?>
        <span class="badge <?= $badge ?>" style="margin-top:3px"><?= $label ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php else: ?>
    <div class="alert alert-warning">Akun Anda belum terhubung ke data pelanggan. Hubungi admin.</div>
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
