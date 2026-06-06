<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$mode   = $_GET['mode'] ?? 'bulanan';
$bulan  = $_GET['bulan'] ?? date('Y-m');
$tahun  = $_GET['tahun'] ?? date('Y');

if ($mode === 'bulanan') {
    $periode = $bulan;
    // Pemasukan dari pembayaran bulan ini
    $pemasukan_tagihan = $pdo->prepare("SELECT COALESCE(SUM(pb.jumlah_bayar),0) FROM pembayaran pb JOIN tagihan t ON pb.tagihan_id=t.id WHERE t.periode=?");
    $pemasukan_tagihan->execute([$periode]);
    $pemasukan_tagihan = $pemasukan_tagihan->fetchColumn();

    $pemasukan_instalasi = $pdo->prepare("SELECT COUNT(*)*500000 FROM pelanggan WHERE DATE_FORMAT(tanggal_bergabung,'%Y-%m')=?");
    $pemasukan_instalasi->execute([$periode]);
    $pemasukan_instalasi = $pemasukan_instalasi->fetchColumn();

    $total_pemasukan = $pemasukan_tagihan + $pemasukan_instalasi;

    // Pengeluaran tetap (estimasi)
    $pengeluaran = ['Upah petugas (2 org)' => 400000, 'Kuras toren/tandon' => 400000, 'Bahan perbaikan pipa' => 250000, 'Operasional lain-lain' => 150000];
    $total_pengeluaran = array_sum($pengeluaran);
    $laba = $total_pemasukan - $total_pengeluaran;

    // Rincian pemasukan
    $tunggakan_bulan = $pdo->prepare("SELECT COALESCE(SUM(pb.jumlah_bayar),0) FROM pembayaran pb JOIN tagihan t ON pb.tagihan_id=t.id WHERE t.status='lunas' AND t.periode != ? AND DATE_FORMAT(pb.tanggal_bayar,'%Y-%m')=?");
    $tunggakan_bulan->execute([$periode, $periode]);
    $pelunasan_tunggakan = $tunggakan_bulan->fetchColumn();

    // Statistik tagihan
    $stats = $pdo->prepare("SELECT COUNT(*) as total, SUM(status='lunas') as lunas, SUM(status='belum_bayar') as belum, SUM(status='tunggakan') as tunggakan FROM tagihan WHERE periode=?");
    $stats->execute([$periode]);
    $stats = $stats->fetch();

} else {
    // Laporan tahunan - rekap per bulan
    $bulanan = [];
    for ($m = 1; $m <= 12; $m++) {
        $p = $tahun . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        $pem = $pdo->prepare("SELECT COALESCE(SUM(pb.jumlah_bayar),0) FROM pembayaran pb JOIN tagihan t ON pb.tagihan_id=t.id WHERE t.periode=?");
        $pem->execute([$p]);
        $bulanan[$p] = $pem->fetchColumn();
    }
    $total_tahunan = array_sum($bulanan);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Laporan Keuangan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    @media print { .no-print { display:none!important; } }
  </style>
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Laporan Keuangan</div>
      <div class="subtitle"><?= $mode==='bulanan' ? date('F Y', strtotime($bulan.'-01')) : 'Tahun '.$tahun ?></div>
    </div>
    <button onclick="window.print()" style="background:rgba(255,255,255,0.2);border:none;border-radius:8px;padding:6px 10px;color:white;font-size:12px;font-weight:600;cursor:pointer" class="no-print">PDF</button>
  </div>

  <div class="page">

    <!-- Toggle bulanan/tahunan -->
    <div style="display:flex;background:white;border-radius:10px;padding:4px;margin-bottom:12px;box-shadow:var(--shadow)" class="no-print">
      <a href="?mode=bulanan&bulan=<?= $bulan ?>" style="flex:1;text-align:center;padding:8px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;
        <?= $mode==='bulanan'?'background:var(--primary);color:white':'color:var(--gray-600)' ?>">Bulanan</a>
      <a href="?mode=tahunan&tahun=<?= $tahun ?>" style="flex:1;text-align:center;padding:8px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;
        <?= $mode==='tahunan'?'background:var(--primary);color:white':'color:var(--gray-600)' ?>">Tahunan</a>
    </div>

    <!-- Filter -->
    <form method="GET" style="display:flex;gap:8px;margin-bottom:12px" class="no-print">
      <input type="hidden" name="mode" value="<?= $mode ?>">
      <?php if ($mode === 'bulanan'): ?>
      <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control">
      <?php else: ?>
      <select name="tahun" class="form-control">
        <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
    </form>

    <?php if ($mode === 'bulanan'): ?>

    <!-- Total Pemasukan -->
    <div style="background:var(--primary);color:white;padding:18px;border-radius:16px;margin-bottom:10px">
      <div style="font-size:12px;opacity:0.8">Total Pemasukan</div>
      <div style="font-size:28px;font-weight:800;margin:4px 0">Rp <?= number_format($total_pemasukan,0,',','.') ?></div>
      <div style="display:flex;align-items:center;gap:6px;font-size:12px;opacity:0.8">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        <?= $stats['lunas'] ?> tagihan lunas dari <?= $stats['total'] ?>
      </div>
    </div>

    <!-- Total Pengeluaran -->
    <div style="background:white;border-radius:16px;padding:18px;margin-bottom:10px;box-shadow:var(--shadow);border-left:4px solid var(--danger)">
      <div style="font-size:12px;color:var(--gray-600)">Total Pengeluaran</div>
      <div style="font-size:26px;font-weight:800;color:var(--danger);margin:4px 0">Rp <?= number_format($total_pengeluaran,0,',','.') ?></div>
    </div>

    <!-- Laba -->
    <div style="background:white;border-radius:16px;padding:18px;margin-bottom:16px;box-shadow:var(--shadow);border-left:4px solid var(--success)">
      <div style="font-size:12px;color:var(--gray-600)">Laba Bersih</div>
      <div style="font-size:26px;font-weight:800;color:var(--success);margin:4px 0">Rp <?= number_format($laba,0,',','.') ?></div>
      <a href="tagihan.php?periode=<?= $bulan ?>" style="font-size:12px;color:var(--primary);text-decoration:none">Lihat detail tagihan →</a>
    </div>

    <!-- Rincian Pemasukan -->
    <div class="section-title">Rincian Pemasukan</div>
    <div class="card">
      <?php
      $r_pemasukan = [
        'Pembayaran tagihan air' => $pemasukan_tagihan,
        'Biaya instalasi baru'   => $pemasukan_instalasi,
        'Pelunasan tunggakan'    => $pelunasan_tunggakan,
      ];
      $subtotal = 0;
      foreach ($r_pemasukan as $label => $nominal):
        $subtotal += $nominal;
      ?>
      <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px">
        <span style="color:var(--gray-700)"><?= $label ?></span>
        <span style="font-weight:600">Rp <?= number_format($nominal,0,',','.') ?></span>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--gray-200);padding-top:10px;font-size:14px;font-weight:700">
        <span>Total Pemasukan</span>
        <span style="color:var(--primary)">Rp <?= number_format($total_pemasukan,0,',','.') ?></span>
      </div>
    </div>

    <!-- Rincian Pengeluaran -->
    <div class="section-title">Rincian Pengeluaran</div>
    <div class="card">
      <?php foreach ($pengeluaran as $label => $nominal): ?>
      <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px">
        <span style="color:var(--gray-700)"><?= $label ?></span>
        <span style="font-weight:600">Rp <?= number_format($nominal,0,',','.') ?></span>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;border-top:1px solid var(--gray-200);padding-top:10px;font-size:14px;font-weight:700">
        <span>Total Pengeluaran</span>
        <span style="color:var(--danger)">Rp <?= number_format($total_pengeluaran,0,',','.') ?></span>
      </div>
    </div>

    <?php else: ?>
    <!-- Laporan Tahunan -->
    <div style="background:var(--primary);color:white;padding:18px;border-radius:16px;margin-bottom:16px">
      <div style="font-size:12px;opacity:0.8">Total Pemasukan <?= $tahun ?></div>
      <div style="font-size:28px;font-weight:800;margin:4px 0">Rp <?= number_format($total_tahunan,0,',','.') ?></div>
    </div>

    <div class="section-title">Rekap Per Bulan</div>
    <div class="card" style="padding:12px">
      <?php $max_val = max(array_values($bulanan)) ?: 1; ?>
      <?php foreach ($bulanan as $p => $nominal): ?>
      <?php $pct = $nominal/$max_val*100; ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span style="color:var(--gray-700);font-weight:500"><?= date('M Y', strtotime($p.'-01')) ?></span>
          <span style="font-weight:700;color:<?= $nominal>0?'var(--primary)':'var(--gray-400)' ?>">Rp <?= number_format($nominal,0,',','.') ?></span>
        </div>
        <div style="background:var(--gray-100);border-radius:4px;height:6px">
          <div style="background:var(--primary);width:<?= $pct ?>%;height:100%;border-radius:4px;transition:width 0.5s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav no-print">
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
    <a href="tunggakan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Tunggakan
    </a>
    <a href="laporan.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
  </nav>

</div>
</body>
</html>
