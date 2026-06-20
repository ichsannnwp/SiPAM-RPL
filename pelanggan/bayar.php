<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT id, nama_lengkap, kode_pelanggan FROM pelanggan WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    die("Akun Anda belum terhubung ke data pelanggan. Hubungi admin.");
}

$pid = $pelanggan['id'];
$tagihan_id = (int)($_GET['tagihan_id'] ?? 0);
$pesan = '';
$error = '';

// Proses upload bukti pembayaran QRIS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
    $tid = (int)$_POST['tagihan_id'];

    // Pastikan tagihan ini benar milik pelanggan yang login & belum lunas
    $cek = $pdo->prepare("SELECT * FROM tagihan WHERE id=? AND pelanggan_id=? AND status != 'lunas'");
    $cek->execute([$tid, $pid]);
    $tagihan_data = $cek->fetch();

    if (!$tagihan_data) {
        $error = 'Tagihan tidak ditemukan atau sudah lunas.';
    } elseif (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
        $kode_error = $_FILES['bukti_transfer']['error'] ?? 'tidak ada file terkirim';
        $keterangan_error = match($kode_error) {
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload_max_filesize di php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas MAX_FILE_SIZE pada form',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian (koneksi terputus)',
            UPLOAD_ERR_NO_FILE    => 'Mohon unggah foto/screenshot bukti pembayaran.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk (cek izin folder)',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP',
            default => 'Mohon unggah foto/screenshot bukti pembayaran.',
        };
        $error = $keterangan_error;
    } else {
        $file = $_FILES['bukti_transfer'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowed_mime = ['image/jpeg', 'image/png', 'application/pdf'];
        $detected_mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowed) || !in_array($detected_mime, $allowed_mime)) {
            $error = 'Format file harus JPG, PNG, atau PDF.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 3MB.';
        } else {
            // Cek apakah sudah ada pengajuan yang masih menunggu untuk tagihan ini
            $cekMenunggu = $pdo->prepare("SELECT id FROM pembayaran WHERE tagihan_id=? AND status_verifikasi='menunggu'");
            $cekMenunggu->execute([$tid]);
            if ($cekMenunggu->fetch()) {
                $error = 'Sudah ada bukti pembayaran yang sedang menunggu verifikasi untuk tagihan ini.';
            } else {
                $filename = 'bukti_' . $tid . '_' . date('YmdHis') . '.' . $ext;

                // Pastikan folder tujuan ada (auto-create jika belum ada/terhapus)
                $folder_upload = __DIR__ . '/../assets/uploads/bukti_transfer';
                if (!is_dir($folder_upload)) {
                    mkdir($folder_upload, 0755, true);
                }

                $target = $folder_upload . '/' . $filename;

                if (!is_writable($folder_upload)) {
                    $error = 'Folder upload tidak memiliki izin tulis. Hubungi admin (path: assets/uploads/bukti_transfer/).';
                } elseif (!move_uploaded_file($file['tmp_name'], $target)) {
                    $error = 'Gagal menyimpan file ke server. Hubungi admin untuk memeriksa izin folder upload.';
                } else {
                    try {
                        $no_kuitansi = 'KUI-' . date('Ymd') . '-' . str_pad($tid, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);
                        $stmt = $pdo->prepare("INSERT INTO pembayaran (tagihan_id, pelanggan_id, jumlah_bayar, metode, bukti_transfer, status_verifikasi, no_kuitansi, tanggal_bayar) VALUES (?,?,?,?,?,?,?,NOW())");
                        $stmt->execute([
                            $tid,
                            $pid,
                            $tagihan_data['total_tagihan'],
                            'qris',
                            $filename,
                            'menunggu',
                            $no_kuitansi
                        ]);
                        $pesan = 'Bukti pembayaran berhasil diunggah! Mohon tunggu verifikasi dari admin/bendahara (1x24 jam). Kuitansi akan tersedia otomatis setelah diverifikasi.';
                    } catch (PDOException $e) {
                        if (file_exists($target)) unlink($target);
                        $error = 'Gagal menyimpan data: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Ambil tagihan yang dipilih (atau tagihan aktif terbaru jika tidak ada parameter)
if ($tagihan_id) {
    $stmt = $pdo->prepare("SELECT * FROM tagihan WHERE id=? AND pelanggan_id=? AND status != 'lunas'");
    $stmt->execute([$tagihan_id, $pid]);
    $tagihan = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM tagihan WHERE pelanggan_id=? AND status IN ('belum_bayar','tunggakan') ORDER BY periode ASC LIMIT 1");
    $stmt->execute([$pid]);
    $tagihan = $stmt->fetch();
}

// Cek status pengajuan terbaru untuk tagihan ini
$pengajuan_terbaru = null;
if ($tagihan) {
    $stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE tagihan_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tagihan['id']]);
    $pengajuan_terbaru = $stmt->fetch();
}

// Jika sudah diverifikasi, arahkan langsung ke kuitansi
if ($pengajuan_terbaru && $pengajuan_terbaru['status_verifikasi'] === 'terverifikasi') {
    header('Location: kuitansi.php?id=' . $pengajuan_terbaru['id']);
    exit;
}

// Ambil gambar QRIS aktif
$qris = $pdo->query("SELECT * FROM qris_setting WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Bayar via QRIS — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Pembayaran QRIS</div>
      <div class="subtitle"><?= htmlspecialchars($pelanggan['nama_lengkap']) ?></div>
    </div>
    <a href="dashboard.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$tagihan): ?>
    <div style="background:var(--success-light);border-radius:16px;padding:20px;text-align:center">
      <svg fill="none" stroke="var(--success)" stroke-width="2" viewBox="0 0 24 24" width="40" style="margin-bottom:10px"><polyline points="20 6 9 17 4 12"/></svg>
      <div style="font-weight:700;color:var(--success);font-size:15px">Semua tagihan sudah lunas!</div>
      <div style="font-size:13px;color:var(--success);opacity:0.85;margin-top:4px">Tidak ada tagihan yang perlu dibayar saat ini.</div>
    </div>

    <?php elseif ($pengajuan_terbaru && $pengajuan_terbaru['status_verifikasi'] === 'menunggu'): ?>
    <!-- Status menunggu verifikasi -->
    <div style="background:var(--warning-light);border:1px solid #fcd34d;border-radius:16px;padding:20px;text-align:center;margin-bottom:14px">
      <svg fill="none" stroke="var(--warning)" stroke-width="2" viewBox="0 0 24 24" width="40" style="margin-bottom:10px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <div style="font-weight:700;color:#92400e;font-size:15px">Menunggu Verifikasi</div>
      <div style="font-size:13px;color:#78350f;opacity:0.9;margin-top:4px">Bukti pembayaran Anda sedang diperiksa oleh admin/bendahara. Kuitansi akan otomatis terbit setelah disetujui.</div>
      <div style="font-size:11px;color:#92400e;margin-top:8px">Diunggah: <?= date('d M Y H:i', strtotime($pengajuan_terbaru['created_at'])) ?></div>
    </div>

    <div class="card" style="padding:14px">
      <div style="font-size:12px;color:var(--gray-600);margin-bottom:4px">Tagihan <?= date('F Y', strtotime($tagihan['periode'].'-01')) ?></div>
      <div style="font-size:20px;font-weight:800">Rp <?= number_format($tagihan['total_tagihan'],0,',','.') ?></div>
    </div>

    <?php else: ?>

    <?php if ($pengajuan_terbaru && $pengajuan_terbaru['status_verifikasi'] === 'ditolak'): ?>
    <div class="alert alert-danger" style="margin-bottom:14px">
      ❌ Bukti pembayaran sebelumnya <strong>ditolak</strong><?= $pengajuan_terbaru['catatan_admin'] ? ': ' . htmlspecialchars($pengajuan_terbaru['catatan_admin']) : '.' ?> Mohon unggah ulang bukti yang valid.
    </div>
    <?php endif; ?>

    <!-- Info tagihan -->
    <div class="card" style="padding:16px;margin-bottom:14px">
      <div style="font-size:12px;color:var(--gray-600);margin-bottom:4px">Tagihan Periode <?= date('F Y', strtotime($tagihan['periode'].'-01')) ?></div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:24px;font-weight:800;color:var(--gray-800)">Rp <?= number_format($tagihan['total_tagihan'],0,',','.') ?></div>
        <span class="badge <?= $tagihan['status']==='tunggakan'?'badge-danger':'badge-warning' ?>">
          <?= $tagihan['status']==='tunggakan'?'Tunggakan':'Belum Bayar' ?>
        </span>
      </div>
      <div style="font-size:12px;color:var(--gray-600);margin-top:6px">Pemakaian: <?= $tagihan['pemakaian_m3'] ?> m³ · Jatuh tempo: <?= date('d M Y', strtotime($tagihan['jatuh_tempo'])) ?></div>
    </div>

    <!-- QRIS -->
    <div class="section-title">Scan QRIS untuk Membayar</div>
    <?php if ($qris): ?>
    <div class="card" style="text-align:center;padding:24px 20px">
      <img src="../assets/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>"
           alt="QRIS Pembayaran"
           id="qrisImage"
           style="width:100%;max-width:280px;border-radius:14px;border:1px solid var(--gray-200);box-shadow:var(--shadow)">
      <div style="font-size:13px;font-weight:600;margin-top:12px"><?= htmlspecialchars($qris['nama_qris']) ?></div>
      <div style="font-size:12px;color:var(--gray-600);margin-top:2px">Scan dengan aplikasi e-wallet / m-banking apa pun</div>

      <a href="../assets/qris/<?= htmlspecialchars($qris['gambar_qris']) ?>"
         download="QRIS-SiPAM-<?= $pelanggan['kode_pelanggan'] ?>.<?= pathinfo($qris['gambar_qris'], PATHINFO_EXTENSION) ?>"
         class="btn btn-outline" style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh Gambar QRIS
      </a>
    </div>
    <?php else: ?>
    <div class="card" style="text-align:center;padding:30px;color:var(--gray-400)">
      QRIS belum tersedia. Silakan bayar langsung ke bendahara PAM Swadaya.
    </div>
    <?php endif; ?>

    <!-- Form upload bukti -->
    <div class="section-title">Unggah Bukti Pembayaran</div>
    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="upload_bukti" value="1">
        <input type="hidden" name="tagihan_id" value="<?= $tagihan['id'] ?>">

        <div class="form-group">
          <label class="form-label">Screenshot / Foto Bukti Transfer</label>
          <input type="file" name="bukti_transfer" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Format JPG, PNG, atau PDF — maksimal 3MB</div>
        </div>

        <div style="background:var(--primary-light);color:var(--primary);padding:10px 12px;border-radius:8px;font-size:12px;margin-bottom:14px">
          💡 Pastikan nominal pada bukti transfer sesuai dengan total tagihan: <strong>Rp <?= number_format($tagihan['total_tagihan'],0,',','.') ?></strong>
        </div>

        <button type="submit" class="btn btn-primary">Kirim Bukti Pembayaran</button>
      </form>
    </div>
    <?php endif; ?>

  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
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