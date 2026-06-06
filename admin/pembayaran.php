<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$tagihan_id = isset($_GET['tagihan_id']) ? (int)$_GET['tagihan_id'] : 0;
$tagihan = null;
$pesan = '';
$error = '';

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayar'])) {
    try {
        $tid = (int)$_POST['tagihan_id'];
        $t = $pdo->prepare("SELECT * FROM tagihan WHERE id=?");
        $t->execute([$tid]);
        $tagihan_data = $t->fetch();

        if (!$tagihan_data) throw new Exception("Tagihan tidak ditemukan.");
        if ($tagihan_data['status'] === 'lunas') throw new Exception("Tagihan ini sudah lunas.");

        $no_kuitansi = 'KUI-' . date('Ymd') . '-' . str_pad($tid, 4, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO pembayaran (tagihan_id, pelanggan_id, jumlah_bayar, metode, no_kuitansi, tanggal_bayar, dicatat_oleh) VALUES (?,?,?,?,?,NOW(),?)");
        $stmt->execute([
            $tid,
            $tagihan_data['pelanggan_id'],
            $tagihan_data['total_tagihan'],
            $_POST['metode'],
            $no_kuitansi,
            $_SESSION['user_id']
        ]);

        $pdo->prepare("UPDATE tagihan SET status='lunas' WHERE id=?")->execute([$tid]);

        header("Location: kuitansi.php?id=" . $pdo->lastInsertId());
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil data tagihan jika ada tagihan_id
if ($tagihan_id) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_lengkap, p.kode_pelanggan, m.angka_bulan_ini, m.angka_bulan_lalu,
               mt.tarif_per_m3
        FROM tagihan t
        JOIN pelanggan p ON t.pelanggan_id = p.id
        JOIN meteran m ON t.meteran_id = m.id
        JOIN master_tarif mt ON t.tarif_id = mt.id
        WHERE t.id=? AND t.status != 'lunas'
    ");
    $stmt->execute([$tagihan_id]);
    $tagihan = $stmt->fetch();
}

// Daftar tagihan belum bayar jika tidak ada tagihan_id
$daftar_tagihan = [];
if (!$tagihan) {
    $search = trim($_GET['q'] ?? '');
    $where = "WHERE t.status IN ('belum_bayar','tunggakan')";
    $params = [];
    if ($search) { $where .= " AND (p.nama_lengkap LIKE ? OR p.kode_pelanggan LIKE ?)"; $params = ["%$search%","%$search%"]; }
    $stmt = $pdo->prepare("SELECT t.*, p.nama_lengkap, p.kode_pelanggan FROM tagihan t JOIN pelanggan p ON t.pelanggan_id=p.id $where ORDER BY t.status DESC, t.jatuh_tempo ASC LIMIT 30");
    $stmt->execute($params);
    $daftar_tagihan = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Catat Pembayaran — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1"><?= $tagihan ? 'Catat Pembayaran' : 'Pembayaran' ?></div>
      <div class="subtitle"><?= $tagihan ? htmlspecialchars($tagihan['nama_lengkap']) : 'Pilih tagihan untuk dibayar' ?></div>
    </div>
    <?php if ($tagihan): ?>
    <a href="pembayaran.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
    <?php endif; ?>
  </div>

  <div class="page">

    <?php if ($error): ?>
      <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($tagihan): ?>
    <!-- Form pembayaran -->
    <div class="card">
      <div style="font-size:12px;color:var(--gray-600);margin-bottom:2px">Form Pembayaran</div>
      <div style="font-weight:700;font-size:16px;margin-bottom:12px"><?= htmlspecialchars($tagihan['nama_lengkap']) ?></div>

      <div style="background:var(--gray-50);border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="color:var(--gray-600)">No Meteran</span>
          <span style="font-weight:500"><?= $tagihan['kode_pelanggan'] ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="color:var(--gray-600)">No Tagihan</span>
          <span style="font-weight:500">TGH-<?= date('Y') ?>-<?= str_pad($tagihan['id'],4,'0',STR_PAD_LEFT) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="color:var(--gray-600)">Pemakaian</span>
          <span style="font-weight:500"><?= $tagihan['pemakaian_m3'] ?> m³</span>
        </div>
        <div style="display:flex;justify-content:space-between;border-top:1px solid var(--gray-200);padding-top:8px;margin-top:4px">
          <span style="color:var(--gray-600)">Nominal</span>
          <span style="font-weight:700;font-size:16px;color:var(--success)">Rp <?= number_format($tagihan['total_tagihan'],0,',','.') ?></span>
        </div>
      </div>

      <form method="POST">
        <input type="hidden" name="proses_bayar" value="1">
        <input type="hidden" name="tagihan_id" value="<?= $tagihan['id'] ?>">

        <div class="form-group">
          <label class="form-label">Tanggal Bayar</label>
          <input type="date" class="form-control" value="<?= date('Y-m-d') ?>" readonly>
        </div>

        <div class="form-group">
          <label class="form-label">Metode Pembayaran</label>
          <select name="metode" class="form-control">
            <option value="tunai">Tunai</option>
            <option value="transfer">Transfer</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">Konfirmasi & Terbitkan Kuitansi</button>
      </form>
    </div>

    <?php else: ?>
    <!-- Daftar tagihan belum bayar -->
    <form method="GET" style="margin-bottom:12px">
      <input class="form-control" type="text" name="q" placeholder="Cari nama atau kode pelanggan..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="background:white">
    </form>

    <?php if (empty($daftar_tagihan)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">Tidak ada tagihan yang perlu dibayar.</div>
    <?php else: ?>
    <div class="section-title">Tagihan Perlu Dibayar (<?= count($daftar_tagihan) ?>)</div>
    <?php foreach ($daftar_tagihan as $t): ?>
    <a href="?tagihan_id=<?= $t['id'] ?>" class="list-item" style="text-decoration:none">
      <div style="width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;
        background:<?= $t['status']==='tunggakan'?'var(--danger-light)':'var(--warning-light)' ?>;
        color:<?= $t['status']==='tunggakan'?'var(--danger)':'var(--warning)' ?>">
        <?= strtoupper(substr($t['nama_lengkap'],0,2)) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['nama_lengkap']) ?></div>
        <div style="font-size:12px;color:var(--gray-600)"><?= $t['kode_pelanggan'] ?> · <?= $t['periode'] ?></div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-weight:700;font-size:13px;color:var(--primary)">Rp <?= number_format($t['total_tagihan'],0,',','.') ?></div>
        <?php $badge = $t['status']==='tunggakan'?'badge-danger':'badge-warning'; $label = $t['status']==='tunggakan'?'Tunggakan':'Belum Bayar'; ?>
        <span class="badge <?= $badge ?>" style="margin-top:3px"><?= $label ?></span>
      </div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
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
