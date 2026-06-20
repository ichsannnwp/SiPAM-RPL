<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pesan = '';
$error = '';

// Proses terima/tolak verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $pembayaran_id = (int)$_POST['pembayaran_id'];
    $aksi = $_POST['aksi'];

    $stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE id=? AND status_verifikasi='menunggu'");
    $stmt->execute([$pembayaran_id]);
    $pb = $stmt->fetch();

    if (!$pb) {
        $error = 'Data pembayaran tidak ditemukan atau sudah diproses.';
    } elseif ($aksi === 'terima') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE pembayaran SET status_verifikasi='terverifikasi', dicatat_oleh=? WHERE id=?")
                ->execute([$_SESSION['user_id'], $pembayaran_id]);
            $pdo->prepare("UPDATE tagihan SET status='lunas' WHERE id=?")
                ->execute([$pb['tagihan_id']]);
            $pdo->commit();
            $pesan = 'Pembayaran berhasil diverifikasi. Kuitansi otomatis aktif untuk pelanggan.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Gagal memproses: ' . $e->getMessage();
        }
    } elseif ($aksi === 'tolak') {
        $catatan = trim($_POST['catatan_admin'] ?? 'Bukti pembayaran tidak valid/tidak sesuai.');
        $pdo->prepare("UPDATE pembayaran SET status_verifikasi='ditolak', catatan_admin=?, dicatat_oleh=? WHERE id=?")
            ->execute([$catatan, $_SESSION['user_id'], $pembayaran_id]);
        $pesan = 'Bukti pembayaran ditolak. Pelanggan dapat mengunggah ulang.';
    }
}

// Daftar pembayaran menunggu verifikasi
$menunggu = $pdo->query("
    SELECT pb.*, t.periode, t.total_tagihan as nominal_tagihan, t.pemakaian_m3,
           p.nama_lengkap, p.kode_pelanggan, p.no_telepon
    FROM pembayaran pb
    JOIN tagihan t ON pb.tagihan_id = t.id
    JOIN pelanggan p ON pb.pelanggan_id = p.id
    WHERE pb.status_verifikasi = 'menunggu'
    ORDER BY pb.created_at ASC
")->fetchAll();

// Riwayat terverifikasi/ditolak terbaru (untuk referensi)
$riwayat = $pdo->query("
    SELECT pb.*, p.nama_lengkap, p.kode_pelanggan
    FROM pembayaran pb
    JOIN pelanggan p ON pb.pelanggan_id = p.id
    WHERE pb.metode = 'qris' AND pb.status_verifikasi != 'menunggu'
    ORDER BY pb.id DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Verifikasi Pembayaran QRIS — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Verifikasi QRIS</div>
      <div class="subtitle"><?= count($menunggu) ?> menunggu persetujuan</div>
    </div>
    <a href="qris.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">Kelola QRIS</a>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($menunggu)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" style="opacity:0.3;display:block;margin:0 auto 12px"><polyline points="20 6 9 17 4 12"/></svg>
      Tidak ada bukti pembayaran yang menunggu verifikasi.
    </div>
    <?php else: ?>

    <div class="section-title">Menunggu Verifikasi (<?= count($menunggu) ?>)</div>

    <?php foreach ($menunggu as $pb): ?>
    <div class="card" style="padding:16px;margin-bottom:12px;border-left:3px solid var(--warning)">

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div>
          <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($pb['nama_lengkap']) ?></div>
          <div style="font-size:12px;color:var(--gray-600)"><?= $pb['kode_pelanggan'] ?> · <?= $pb['no_telepon'] ?></div>
        </div>
        <span class="badge badge-warning">QRIS</span>
      </div>

      <div style="background:var(--gray-50);border-radius:8px;padding:10px 12px;margin-bottom:10px;font-size:13px">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="color:var(--gray-600)">Periode</span>
          <span style="font-weight:500"><?= date('F Y', strtotime($pb['periode'].'-01')) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="color:var(--gray-600)">Pemakaian</span>
          <span style="font-weight:500"><?= $pb['pemakaian_m3'] ?> m³</span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--gray-600)">Jumlah Bayar</span>
          <span style="font-weight:700;color:var(--primary)">Rp <?= number_format($pb['jumlah_bayar'],0,',','.') ?></span>
        </div>
      </div>

      <!-- Preview bukti -->
      <div style="margin-bottom:12px">
        <div style="font-size:12px;color:var(--gray-600);margin-bottom:6px">Bukti Pembayaran:</div>
        <?php $ext = pathinfo($pb['bukti_transfer'], PATHINFO_EXTENSION); ?>
        <?php if (in_array(strtolower($ext), ['jpg','jpeg','png'])): ?>
        <a href="../assets/uploads/bukti_transfer/<?= htmlspecialchars($pb['bukti_transfer']) ?>" target="_blank">
          <img src="../assets/uploads/bukti_transfer/<?= htmlspecialchars($pb['bukti_transfer']) ?>"
               style="width:100%;max-width:220px;border-radius:10px;border:1px solid var(--gray-200)">
        </a>
        <?php else: ?>
        <a href="../assets/uploads/bukti_transfer/<?= htmlspecialchars($pb['bukti_transfer']) ?>" target="_blank" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;width:auto;padding:8px 14px">
          📄 Lihat File PDF
        </a>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--gray-400);margin-top:6px">Diunggah: <?= date('d M Y H:i', strtotime($pb['created_at'])) ?></div>
      </div>

      <!-- Form aksi -->
      <div style="display:flex;gap:8px">
        <form method="POST" style="flex:1">
          <input type="hidden" name="pembayaran_id" value="<?= $pb['id'] ?>">
          <input type="hidden" name="aksi" value="terima">
          <button type="submit" class="btn btn-primary" style="background:var(--success);padding:10px" onclick="return confirm('Verifikasi pembayaran ini? Tagihan akan otomatis berstatus lunas.')">
            ✓ Terima
          </button>
        </form>
        <button type="button" class="btn btn-outline" style="border-color:var(--danger);color:var(--danger);padding:10px;flex:1" onclick="document.getElementById('tolak-<?= $pb['id'] ?>').style.display='block'">
          ✕ Tolak
        </button>
      </div>

      <!-- Form tolak (hidden default) -->
      <div id="tolak-<?= $pb['id'] ?>" style="display:none;margin-top:10px">
        <form method="POST">
          <input type="hidden" name="pembayaran_id" value="<?= $pb['id'] ?>">
          <input type="hidden" name="aksi" value="tolak">
          <textarea name="catatan_admin" class="form-control" rows="2" placeholder="Alasan penolakan (contoh: nominal tidak sesuai)" style="margin-bottom:8px"></textarea>
          <button type="submit" class="btn" style="background:var(--danger);color:white;padding:10px">Konfirmasi Tolak</button>
        </form>
      </div>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($riwayat)): ?>
    <div class="section-title" style="margin-top:20px">Riwayat Terbaru</div>
    <?php foreach ($riwayat as $r): ?>
    <div class="list-item">
      <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
        background:<?= $r['status_verifikasi']==='terverifikasi'?'var(--success-light)':'var(--danger-light)' ?>;
        color:<?= $r['status_verifikasi']==='terverifikasi'?'var(--success)':'var(--danger)' ?>">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18">
          <?= $r['status_verifikasi']==='terverifikasi' ? '<polyline points="20 6 9 17 4 12"/>' : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>' ?>
        </svg>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
        <div style="font-size:12px;color:var(--gray-600)"><?= $r['kode_pelanggan'] ?> · Rp <?= number_format($r['jumlah_bayar'],0,',','.') ?></div>
      </div>
      <span class="badge <?= $r['status_verifikasi']==='terverifikasi'?'badge-success':'badge-danger' ?>">
        <?= $r['status_verifikasi']==='terverifikasi'?'Diterima':'Ditolak' ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="pembayaran.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Pembayaran
    </a>
    <a href="verifikasi-pembayaran.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      Verifikasi
    </a>
    <a href="laporan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
  </nav>

</div>
</body>
</html>
