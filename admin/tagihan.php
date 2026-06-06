<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$periode = $_GET['periode'] ?? date('Y-m');
$status_filter = $_GET['status'] ?? '';

$where = "WHERE t.periode = ?";
$params = [$periode];
if ($status_filter) { $where .= " AND t.status = ?"; $params[] = $status_filter; }

$tagihan_list = $pdo->prepare("
    SELECT t.*, p.nama_lengkap, p.kode_pelanggan
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    $where
    ORDER BY t.status ASC, p.kode_pelanggan ASC
");
$tagihan_list->execute($params);
$tagihan_list = $tagihan_list->fetchAll();

$stats = $pdo->prepare("SELECT
    COUNT(*) as total,
    SUM(status='lunas') as lunas,
    SUM(status='belum_bayar') as belum,
    SUM(status='tunggakan') as tunggakan
    FROM tagihan WHERE periode=?");
$stats->execute([$periode]);
$stats = $stats->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Manajemen Tagihan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Manajemen Tagihan</div>
      <div class="subtitle"><?= date('F Y', strtotime($periode.'-01')) ?></div>
    </div>
  </div>

  <div class="page">

    <!-- Filter -->
    <form method="GET" style="display:flex;gap:8px;margin-bottom:12px">
      <input type="month" name="periode" value="<?= $periode ?>" class="form-control" style="flex:1">
      <select name="status" class="form-control" style="flex:1" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="lunas" <?= $status_filter==='lunas'?'selected':'' ?>>Lunas</option>
        <option value="belum_bayar" <?= $status_filter==='belum_bayar'?'selected':'' ?>>Belum Bayar</option>
        <option value="tunggakan" <?= $status_filter==='tunggakan'?'selected':'' ?>>Tunggakan</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    </form>

    <!-- Stats -->
    <div style="background:white;border-radius:var(--radius);padding:14px;margin-bottom:12px;box-shadow:var(--shadow)">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span style="font-size:13px;color:var(--gray-600)">Total Tagihan</span>
        <span style="font-weight:700"><?= $stats['total'] ?></span>
      </div>
      <div style="background:var(--gray-100);border-radius:6px;height:8px;overflow:hidden;margin-bottom:10px">
        <?php $pct = $stats['total'] > 0 ? ($stats['lunas']/$stats['total']*100) : 0; ?>
        <div style="background:var(--success);width:<?= $pct ?>%;height:100%;border-radius:6px;transition:width 0.5s"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;text-align:center">
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--success)"><?= $stats['lunas'] ?></div>
          <div style="font-size:11px;color:var(--gray-600)">Lunas</div>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--warning)"><?= $stats['belum'] ?></div>
          <div style="font-size:11px;color:var(--gray-600)">Belum bayar</div>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--danger)"><?= $stats['tunggakan'] ?></div>
          <div style="font-size:11px;color:var(--gray-600)">Tunggakan</div>
        </div>
      </div>
    </div>

    <!-- Tombol kirim reminder -->
    <?php if ($stats['belum'] > 0 || $stats['tunggakan'] > 0): ?>
    <a href="kirim-reminder.php?periode=<?= $periode ?>" class="btn btn-outline" style="margin-bottom:12px;display:flex;align-items:center;justify-content:center;gap:8px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Kirim Reminder Massal
    </a>
    <?php endif; ?>

    <!-- List tagihan -->
    <?php if (empty($tagihan_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">Belum ada tagihan periode ini.</div>
    <?php else: ?>
    <?php foreach ($tagihan_list as $t): ?>
    <div class="list-item" style="gap:10px">
      <!-- Avatar -->
      <div style="width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;
        background:<?= $t['status']==='lunas'?'var(--success-light)':($t['status']==='tunggakan'?'var(--danger-light)':'var(--warning-light)') ?>;
        color:<?= $t['status']==='lunas'?'var(--success)':($t['status']==='tunggakan'?'var(--danger)':'var(--warning)') ?>">
        <?= strtoupper(substr($t['nama_lengkap'],0,2)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
        <div style="font-size:12px;color:var(--gray-600)"><?= $t['kode_pelanggan'] ?> · <?= $t['pemakaian_m3'] ?> m³</div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-weight:700;font-size:13px">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
        <?php
          $badge = match($t['status']) { 'lunas'=>'badge-success','tunggakan'=>'badge-danger',default=>'badge-warning' };
          $label = match($t['status']) { 'lunas'=>'Lunas','tunggakan'=>'Tunggakan',default=>'Belum Bayar' };
        ?>
        <span class="badge <?= $badge ?>" style="margin-top:3px"><?= $label ?></span>
        <?php if ($t['status']==='belum_bayar'): ?>
        <div><a href="pembayaran.php?tagihan_id=<?= $t['id'] ?>" style="font-size:11px;color:var(--primary);font-weight:600;text-decoration:none">Bayar →</a></div>
        <?php endif; ?>
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
