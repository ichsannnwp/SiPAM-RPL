<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Auto-update tunggakan: tagihan belum_bayar yang sudah lewat jatuh tempo
$pdo->query("UPDATE tagihan SET status='tunggakan' WHERE status='belum_bayar' AND jatuh_tempo < CURDATE()");

// Hitung bulan tunggakan per pelanggan
$tunggakan_list = $pdo->query("
    SELECT p.id, p.nama_lengkap, p.kode_pelanggan, p.no_telepon,
           COUNT(t.id) as jumlah_bulan,
           SUM(t.total_tagihan) as total_nominal,
           MIN(t.periode) as periode_awal,
           MAX(t.periode) as periode_akhir,
           MAX(t.status) as max_status
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    WHERE t.status IN ('belum_bayar','tunggakan')
    GROUP BY p.id, p.nama_lengkap, p.kode_pelanggan, p.no_telepon
    ORDER BY jumlah_bulan DESC, total_nominal DESC
")->fetchAll();

$total_menunggak = count($tunggakan_list);
$perlu_eskalasi  = array_filter($tunggakan_list, fn($t) => $t['jumlah_bulan'] >= 2);
$total_nominal   = array_sum(array_column($tunggakan_list, 'total_nominal'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Data Tunggakan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Data Tunggakan</div>
      <div class="subtitle"><?= $total_menunggak ?> pelanggan menunggak</div>
    </div>
  </div>

  <div class="page">

    <!-- Ringkasan -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:white;border-radius:12px;padding:12px;box-shadow:var(--shadow);text-align:center">
        <div style="font-size:22px;font-weight:700;color:var(--warning)"><?= $total_menunggak ?></div>
        <div style="font-size:11px;color:var(--gray-600);margin-top:2px">Total menunggak</div>
      </div>
      <div style="background:white;border-radius:12px;padding:12px;box-shadow:var(--shadow);text-align:center">
        <div style="font-size:22px;font-weight:700;color:var(--danger)"><?= count($perlu_eskalasi) ?></div>
        <div style="font-size:11px;color:var(--gray-600);margin-top:2px">Perlu eskalasi</div>
      </div>
      <div style="background:white;border-radius:12px;padding:12px;box-shadow:var(--shadow);text-align:center">
        <div style="font-size:14px;font-weight:700;color:var(--danger)">Rp <?= number_format($total_nominal/1000,1) ?>jt</div>
        <div style="font-size:11px;color:var(--gray-600);margin-top:2px">Total nilai</div>
      </div>
    </div>

    <!-- Alert eskalasi -->
    <?php if (count($perlu_eskalasi) > 0): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px 14px;margin-bottom:12px;display:flex;gap:10px;align-items:flex-start">
      <svg fill="none" stroke="var(--danger)" stroke-width="2" viewBox="0 0 24 24" width="20" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div style="font-size:13px;color:var(--danger)"><?= count($perlu_eskalasi) ?> pelanggan memerlukan eskalasi ke Perangkat Desa (tunggakan &gt;2 bulan).</div>
    </div>

    <a href="eskalasi.php" class="btn btn-outline" style="margin-bottom:12px;border-color:var(--danger);color:var(--danger);display:flex;align-items:center;justify-content:center;gap:8px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18"><path d="M22 17H2a3 3 0 003-3V9a7 7 0 0114 0v5a3 3 0 003 3z"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      Eskalasi Massal ke Perangkat Desa
    </a>
    <?php endif; ?>

    <!-- Daftar -->
    <div class="section-title">Daftar Pelanggan Menunggak</div>

    <?php if (empty($tunggakan_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" style="opacity:0.3;display:block;margin:0 auto 12px"><polyline points="20 6 9 17 4 12"/></svg>
      Tidak ada tunggakan. Semua pelanggan sudah bayar!
    </div>
    <?php else: ?>
    <?php foreach ($tunggakan_list as $t): ?>
    <?php $eskalasi = $t['jumlah_bulan'] >= 2; ?>
    <div class="card" style="padding:14px;margin-bottom:8px;<?= $eskalasi ? 'border-left:3px solid var(--danger)' : '' ?>">
      <div style="display:flex;align-items:flex-start;gap:10px">
        <!-- Avatar -->
        <div style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;
          background:<?= $eskalasi?'var(--danger-light)':'var(--warning-light)' ?>;
          color:<?= $eskalasi?'var(--danger)':'var(--warning)' ?>">
          <?= strtoupper(substr($t['nama_lengkap'],0,2)) ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
          <div style="font-size:12px;color:var(--gray-600)"><?= $t['kode_pelanggan'] ?> · <?= $t['no_telepon'] ?></div>
          <div style="font-size:12px;color:var(--gray-600);margin-top:2px">
            <?= $t['periode_awal'] ?><?= $t['periode_awal'] !== $t['periode_akhir'] ? ' – '.$t['periode_akhir'] : '' ?>
          </div>
          <div style="display:flex;gap:6px;margin-top:6px;align-items:center">
            <span class="badge <?= $eskalasi?'badge-danger':'badge-warning' ?>"><?= $t['jumlah_bulan'] ?> bln · <?= $eskalasi?'Eskalasi':'Reminder' ?></span>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-weight:700;font-size:14px;color:var(--danger)">Rp <?= number_format($t['total_nominal'],0,',','.') ?></div>
          <a href="pembayaran.php?q=<?= urlencode($t['nama_lengkap']) ?>" style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:600;margin-top:4px;display:block">Bayar →</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

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
    <a href="tunggakan.php" class="nav-item active">
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
