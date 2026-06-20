<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$periode = $_GET['periode'] ?? date('Y-m');
$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_reminder'])) {
    try {
        $ids = $_POST['tagihan_ids'] ?? [];
        $count = 0;
        foreach ($ids as $tagihan_id) {
            $tagihan_id = (int)$tagihan_id;
            // Ambil data tagihan
            $stmt = $pdo->prepare("SELECT t.*, p.id as pid, p.nama_lengkap, p.no_telepon FROM tagihan t JOIN pelanggan p ON t.pelanggan_id=p.id WHERE t.id=?");
            $stmt->execute([$tagihan_id]);
            $t = $stmt->fetch();
            if (!$t) continue;

            // Cek sudah ada reminder hari ini untuk tagihan ini
            $cek = $pdo->prepare("SELECT id FROM notifikasi WHERE pelanggan_id=? AND tagihan_id=? AND tipe='reminder' AND DATE(created_at)=CURDATE()");
            $cek->execute([$t['pid'], $tagihan_id]);
            if ($cek->fetch()) continue;

            $pesan_notif = "Pengingat: Tagihan air PAM periode " . date('F Y', strtotime($t['periode'].'-01')) . " senilai Rp " . number_format($t['total_tagihan'],0,',','.') . " belum dibayar. Jatuh tempo: " . date('d M Y', strtotime($t['jatuh_tempo'])) . ". Segera bayar ke bendahara PAM.";

            $stmt2 = $pdo->prepare("INSERT INTO notifikasi (pelanggan_id, tagihan_id, tipe, pesan, is_sent, sent_at) VALUES (?, ?, 'reminder', ?, 1, NOW())");
            $stmt2->execute([$t['pid'], $tagihan_id, $pesan_notif]);
            $count++;
        }
        $pesan = "$count reminder berhasil dicatat.";
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Ambil tagihan belum bayar periode ini
$tagihan_list = $pdo->prepare("
    SELECT t.*, p.nama_lengkap, p.kode_pelanggan, p.no_telepon
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id=p.id
    WHERE t.periode=? AND t.status IN ('belum_bayar','tunggakan')
    ORDER BY t.status DESC, p.kode_pelanggan ASC
");
$tagihan_list->execute([$periode]);
$tagihan_list = $tagihan_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Kirim Reminder — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Kirim Reminder</div>
      <div class="subtitle"><?= date('F Y', strtotime($periode.'-01')) ?> · <?= count($tagihan_list) ?> belum bayar</div>
    </div>
    <a href="tagihan.php?periode=<?= $periode ?>" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($tagihan_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">
      Tidak ada tagihan yang perlu diingatkan periode ini.
    </div>
    <?php else: ?>

    <div style="background:var(--warning-light);border:1px solid #fcd34d;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#92400e">
      <strong>Catatan:</strong> Reminder akan dicatat ke dalam log notifikasi sistem. Sampaikan informasi ini kepada pelanggan melalui WhatsApp atau kunjungan langsung.
    </div>

    <form method="POST">
      <input type="hidden" name="kirim_reminder" value="1">

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div class="section-title" style="margin:0">Pilih Penerima</div>
        <label style="font-size:13px;color:var(--primary);cursor:pointer;font-weight:600">
          <input type="checkbox" id="checkAll" onchange="toggleAll(this)" checked> Pilih Semua
        </label>
      </div>

      <?php foreach ($tagihan_list as $t): ?>
      <div class="list-item" style="gap:10px;padding:12px 14px">
        <input type="checkbox" name="tagihan_ids[]" value="<?= $t['id'] ?>" class="item-check" checked style="width:16px;height:16px;flex-shrink:0">
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
          <div style="font-size:12px;color:var(--gray-600)"><?= $t['kode_pelanggan'] ?> · <?= $t['no_telepon'] ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-weight:700;font-size:13px">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
          <?php $badge = $t['status']==='tunggakan'?'badge-danger':'badge-warning'; $label = $t['status']==='tunggakan'?'Tunggakan':'Belum Bayar'; ?>
          <span class="badge <?= $badge ?>" style="margin-top:3px"><?= $label ?></span>
        </div>
      </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary" style="margin-top:12px">
        Kirim Reminder ke Pelanggan Terpilih
      </button>
    </form>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="tagihan.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Tagihan
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
<script>
function toggleAll(cb) {
  document.querySelectorAll('.item-check').forEach(el => el.checked = cb.checked);
}
</script>
</body>
</html>
