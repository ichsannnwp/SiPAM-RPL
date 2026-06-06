<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Auto-update status tunggakan yang sudah lewat jatuh tempo
$pdo->query("UPDATE tagihan SET status='tunggakan' WHERE status='belum_bayar' AND jatuh_tempo < CURDATE()");

// Proses kirim notifikasi eskalasi ke semua pelanggan >2 bulan
$pesan = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_eskalasi'])) {
    try {
        $ids = $_POST['pelanggan_ids'] ?? [];
        $count = 0;
        foreach ($ids as $pelanggan_id) {
            $pelanggan_id = (int)$pelanggan_id;
            // Cek sudah ada notifikasi eskalasi hari ini
            $cek = $pdo->prepare("SELECT id FROM notifikasi WHERE pelanggan_id=? AND tipe='eskalasi' AND DATE(created_at)=CURDATE()");
            $cek->execute([$pelanggan_id]);
            if ($cek->fetch()) continue;

            // Hitung total tunggakan
            $tot = $pdo->prepare("SELECT COUNT(*) as jml, SUM(total_tagihan) as nominal FROM tagihan WHERE pelanggan_id=? AND status IN ('belum_bayar','tunggakan')");
            $tot->execute([$pelanggan_id]);
            $info = $tot->fetch();

            $pesan_notif = "ESKALASI: Pelanggan memiliki tunggakan {$info['jml']} bulan senilai Rp " . number_format($info['nominal'],0,',','.') . ". Mohon segera ditindaklanjuti.";
            $stmt = $pdo->prepare("INSERT INTO notifikasi (pelanggan_id, tipe, pesan, is_sent, sent_at) VALUES (?, 'eskalasi', ?, 1, NOW())");
            $stmt->execute([$pelanggan_id, $pesan_notif]);
            $count++;
        }
        $pesan = "$count notifikasi eskalasi berhasil dicatat.";
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Ambil daftar pelanggan dengan tunggakan >= 3 bulan (sesuai SRS FR-008 VR-035)
$eskalasi_list = $pdo->query("
    SELECT p.id, p.nama_lengkap, p.kode_pelanggan, p.no_telepon,
           COUNT(t.id) as jumlah_bulan,
           SUM(t.total_tagihan) as total_nominal,
           MIN(t.periode) as periode_awal,
           MAX(t.periode) as periode_akhir
    FROM tagihan t
    JOIN pelanggan p ON t.pelanggan_id = p.id
    WHERE t.status IN ('belum_bayar','tunggakan')
    GROUP BY p.id, p.nama_lengkap, p.kode_pelanggan, p.no_telepon
    HAVING jumlah_bulan >= 3
    ORDER BY jumlah_bulan DESC, total_nominal DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Eskalasi Tunggakan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Eskalasi Tunggakan</div>
      <div class="subtitle">Tunggakan ≥ 3 bulan → Perangkat Desa</div>
    </div>
    <a href="tunggakan.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:14px;margin-bottom:16px">
      <div style="font-weight:700;color:#dc2626;margin-bottom:4px;display:flex;align-items:center;gap:6px">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Laporan Eskalasi Tunggakan Kritis
      </div>
      <div style="font-size:13px;color:#7f1d1d">
        Pelanggan berikut memiliki tunggakan ≥ 3 bulan dan perlu dilaporkan kepada Perangkat Desa untuk pengambilan keputusan pemutusan layanan.
      </div>
    </div>

    <?php if (empty($eskalasi_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" style="opacity:0.3;display:block;margin:0 auto 12px"><polyline points="20 6 9 17 4 12"/></svg>
      Tidak ada pelanggan dengan tunggakan ≥ 3 bulan.
    </div>
    <?php else: ?>

    <div class="section-title">Daftar Eskalasi (<?= count($eskalasi_list) ?> pelanggan)</div>

    <form method="POST">
      <input type="hidden" name="kirim_eskalasi" value="1">
      <?php foreach ($eskalasi_list as $p): ?>
      <div class="card" style="padding:14px;margin-bottom:8px;border-left:3px solid var(--danger)">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <input type="checkbox" name="pelanggan_ids[]" value="<?= $p['id'] ?>" checked style="margin-top:4px;width:16px;height:16px;flex-shrink:0">
          <div style="flex:1">
            <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
            <div style="font-size:12px;color:var(--gray-600);margin-top:2px"><?= $p['kode_pelanggan'] ?> · <?= $p['no_telepon'] ?></div>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
              <span style="background:#fef2f2;color:#dc2626;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px">
                <?= $p['jumlah_bulan'] ?> bulan tunggak
              </span>
              <span style="background:var(--gray-100);color:var(--gray-600);font-size:12px;padding:3px 10px;border-radius:20px">
                Rp <?= number_format($p['total_nominal'],0,',','.') ?>
              </span>
            </div>
            <div style="font-size:11px;color:var(--gray-400);margin-top:6px">
              Periode <?= $p['periode_awal'] ?> s/d <?= $p['periode_akhir'] ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary" style="background:#dc2626;margin-top:8px">
        Catat Eskalasi ke Perangkat Desa
      </button>
    </form>
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
