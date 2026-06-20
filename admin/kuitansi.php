<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT pb.*, t.periode, t.pemakaian_m3, t.total_tagihan, t.jatuh_tempo,
           p.nama_lengkap, p.kode_pelanggan, p.alamat, p.no_telepon,
           mt.tarif_per_m3,
           u.email as dicatat_email
    FROM pembayaran pb
    JOIN tagihan t ON pb.tagihan_id = t.id
    JOIN pelanggan p ON pb.pelanggan_id = p.id
    JOIN master_tarif mt ON t.tarif_id = mt.id
    JOIN users u ON pb.dicatat_oleh = u.id
    WHERE pb.id = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    die("Kuitansi tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Kuitansi <?= $data['no_kuitansi'] ?> — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: white; }
      .app { max-width: 100%; }
    }
  </style>
</head>
<body>
<div class="app">

  <div class="header no-print">
    <div>
      <div class="h1">Kuitansi Pembayaran</div>
      <div class="subtitle"><?= $data['no_kuitansi'] ?></div>
    </div>
    <button onclick="window.print()" style="background:rgba(255,255,255,0.2);border:none;border-radius:8px;padding:6px 12px;color:white;font-size:13px;font-weight:600;cursor:pointer">Cetak</button>
  </div>

  <div class="page" style="padding-top:20px">

    <!-- Kuitansi card -->
    <div style="background:white;border-radius:16px;overflow:hidden;box-shadow:var(--shadow-md)">

      <!-- Header kuitansi -->
      <div style="background:var(--primary);padding:20px;text-align:center;color:white">
        <div style="font-size:11px;opacity:0.8;letter-spacing:1px;text-transform:uppercase">PAM Swadaya ds Ngasem, Gebang, Masaran, Sragen</div>
        <div style="font-size:22px;font-weight:800;margin:6px 0">KUITANSI PEMBAYARAN</div>
        <div style="font-size:12px;opacity:0.9">No: <?= $data['no_kuitansi'] ?> &nbsp;·&nbsp; Tgl: <?= date('d M Y', strtotime($data['tanggal_bayar'])) ?></div>
      </div>

      <!-- Body -->
      <div style="padding:20px">

        <div style="border-bottom:1px dashed var(--gray-200);padding-bottom:14px;margin-bottom:14px">
          <?php
          $rows = [
            ['Nama Pelanggan', htmlspecialchars($data['nama_lengkap'])],
            ['No Meteran', $data['kode_pelanggan']],
            ['Periode Tagihan', date('F Y', strtotime($data['periode'].'-01'))],
            ['Pemakaian', $data['pemakaian_m3'] . ' m³'],
            ['Tarif', 'Rp ' . number_format($data['tarif_per_m3'],0,',','.') . ' / m³'],
            ['Metode', ucfirst($data['metode'])],
          ];
          foreach ($rows as [$label, $value]):
          ?>
          <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px">
            <span style="color:var(--gray-600)"><?= $label ?></span>
            <span style="font-weight:500;text-align:right"><?= $value ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700;font-size:15px">Total Dibayar</span>
          <span style="font-weight:800;font-size:22px;color:var(--success)">Rp <?= number_format($data['total_tagihan'],0,',','.') ?></span>
        </div>

      </div>

      <!-- Footer -->
      <div style="background:var(--gray-50);padding:14px 20px;text-align:center;border-top:1px solid var(--gray-200)">
        <div style="font-size:12px;color:var(--gray-600)">Terima kasih telah membayar tepat waktu</div>
        <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Dicatat oleh: <?= htmlspecialchars($data['dicatat_email']) ?></div>
      </div>

    </div>

    <!-- Tombol aksi -->
    <div style="margin-top:16px;display:flex;gap:10px" class="no-print">
      <a href="pembayaran.php" class="btn btn-outline" style="flex:1">Bayar Lagi</a>
      <a href="dashboard.php" class="btn btn-primary" style="flex:1">Dashboard</a>
    </div>

  </div>
</div>
</body>
</html>
