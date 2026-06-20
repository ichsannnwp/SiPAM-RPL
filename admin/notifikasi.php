<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil semua notifikasi beserta info pelanggan
$notif_list = $pdo->query("
    SELECT n.*, p.nama_lengkap, p.kode_pelanggan,
           t.periode, t.total_tagihan
    FROM notifikasi n
    JOIN pelanggan p ON n.pelanggan_id = p.id
    LEFT JOIN tagihan t ON n.tagihan_id = t.id
    ORDER BY n.created_at DESC
    LIMIT 100
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Log Notifikasi — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Log Notifikasi</div>
      <div class="subtitle"><?= count($notif_list) ?> notifikasi tercatat</div>
    </div>
  </div>

  <div class="page">

    <?php if (empty($notif_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" style="opacity:0.3;display:block;margin:0 auto 12px"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Belum ada notifikasi tercatat.
    </div>
    <?php else: ?>
    <?php foreach ($notif_list as $n): ?>
    <?php
      $tipe_color = match($n['tipe']) {
        'reminder' => 'var(--warning)',
        'eskalasi' => 'var(--danger)',
        default    => 'var(--primary)'
      };
      $tipe_bg = match($n['tipe']) {
        'reminder' => 'var(--warning-light)',
        'eskalasi' => 'var(--danger-light)',
        default    => 'var(--primary-light)'
      };
      $tipe_label = match($n['tipe']) {
        'reminder' => 'Reminder',
        'eskalasi' => 'Eskalasi',
        default    => 'Info'
      };
    ?>
    <div class="card" style="padding:14px;margin-bottom:8px">
      <div style="display:flex;align-items:flex-start;gap:10px">
        <div style="width:36px;height:36px;border-radius:8px;background:<?= $tipe_bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg fill="none" stroke="<?= $tipe_color ?>" stroke-width="2" viewBox="0 0 24 24" width="18">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
          </svg>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($n['nama_lengkap']) ?></div>
            <span style="background:<?= $tipe_bg ?>;color:<?= $tipe_color ?>;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;flex-shrink:0;margin-left:8px"><?= $tipe_label ?></span>
          </div>
          <div style="font-size:12px;color:var(--gray-600);margin-top:2px"><?= $n['kode_pelanggan'] ?></div>
          <div style="font-size:13px;color:var(--gray-700);margin-top:6px;line-height:1.5"><?= htmlspecialchars($n['pesan']) ?></div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:6px">
            <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
            <?php if ($n['is_sent']): ?>
            · <span style="color:var(--success)">✓ Terkirim</span>
            <?php endif; ?>
          </div>
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
    <a href="notifikasi.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Notifikasi
    </a>
    <a href="laporan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
  </nav>

</div>
</body>
</html>
