<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pesan = '';
$error = '';

// Upload gambar QRIS baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_qris']) && $_SESSION['role'] === 'admin') {
    if (!isset($_FILES['gambar_qris']) || $_FILES['gambar_qris']['error'] !== UPLOAD_ERR_OK) {
        // Tampilkan kode error PHP yang sesungguhnya supaya mudah didiagnosa
        $kode_error = $_FILES['gambar_qris']['error'] ?? 'tidak ada file terkirim';
        $keterangan_error = match($kode_error) {
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload_max_filesize di php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas MAX_FILE_SIZE pada form',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian (koneksi terputus)',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk (cek izin folder)',
            UPLOAD_ERR_EXTENSION  => 'Upload dihentikan oleh ekstensi PHP',
            default => 'Kode error: ' . $kode_error,
        };
        $error = 'Gagal mengunggah gambar. ' . $keterangan_error;
    } else {
        $file = $_FILES['gambar_qris'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        $allowed_mime = ['image/jpeg', 'image/png'];
        $detected_mime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowed) || !in_array($detected_mime, $allowed_mime)) {
            $error = 'Format file harus JPG atau PNG. (Terdeteksi: ' . $detected_mime . ')';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 3MB.';
        } else {
            $nama_qris = trim($_POST['nama_qris'] ?? 'QRIS PAM Swadaya');
            $filename = 'qris_' . date('YmdHis') . '.' . $ext;

            // Pastikan folder tujuan ada (auto-create jika belum ada/terhapus)
            $folder_qris = __DIR__ . '/../assets/qris';
            if (!is_dir($folder_qris)) {
                mkdir($folder_qris, 0755, true);
            }

            $target = $folder_qris . '/' . $filename;

            // Diagnosa sebelum upload: cek folder benar2 writable
            if (!is_writable($folder_qris)) {
                $error = 'Folder assets/qris/ tidak memiliki izin tulis (not writable). Path: ' . realpath($folder_qris ?: '.') ?: $folder_qris;
            } elseif (!move_uploaded_file($file['tmp_name'], $target)) {
                $last_err = error_get_last();
                $error = 'Gagal menyimpan file ke server. Target: ' . $target
                       . ($last_err ? ' | Detail: ' . $last_err['message'] : '');
            } else {
                try {
                    $pdo->beginTransaction();
                    // Nonaktifkan QRIS lama
                    $pdo->query("UPDATE qris_setting SET is_active = 0");
                    // Simpan QRIS baru sebagai aktif
                    $stmt = $pdo->prepare("INSERT INTO qris_setting (nama_qris, gambar_qris, is_active) VALUES (?, ?, 1)");
                    $stmt->execute([$nama_qris, $filename]);
                    $pdo->commit();
                    $pesan = 'Gambar QRIS berhasil diperbarui dan diaktifkan.';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    // Hapus file yang sudah keburu tersimpan jika gagal simpan ke DB
                    if (file_exists($target)) unlink($target);
                    $error = 'Gagal menyimpan ke database: ' . $e->getMessage();
                }
            }
        }
    }
}

// Aktifkan ulang QRIS lama dari riwayat
if (isset($_GET['aktifkan'])) {
    $id = (int)$_GET['aktifkan'];
    $pdo->query("UPDATE qris_setting SET is_active = 0");
    $pdo->prepare("UPDATE qris_setting SET is_active = 1 WHERE id = ?")->execute([$id]);
    header('Location: qris.php?pesan=aktif');
    exit;
}

if (isset($_GET['pesan']) && $_GET['pesan'] === 'aktif') {
    $pesan = 'QRIS berhasil diaktifkan.';
}

// Ambil QRIS aktif saat ini
$qris_aktif = $pdo->query("SELECT * FROM qris_setting WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();

// Riwayat QRIS sebelumnya
$riwayat_qris = $pdo->query("SELECT * FROM qris_setting ORDER BY id DESC LIMIT 10")->fetchAll();

// Info diagnostik folder (ditampilkan hanya jika ada error, membantu admin troubleshoot)
$folder_qris_check = __DIR__ . '/../assets/qris';
$folder_ada = is_dir($folder_qris_check);
$folder_writable = $folder_ada && is_writable($folder_qris_check);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Kelola QRIS — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Kelola QRIS</div>
      <div class="subtitle">Atur gambar QRIS pembayaran pelanggan</div>
    </div>
    <a href="dashboard.php" style="color:white;font-size:13px;text-decoration:none;background:rgba(255,255,255,0.2);padding:6px 12px;border-radius:8px">← Kembali</a>
  </div>

  <div class="page">

    <?php if ($pesan): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <div class="alert alert-warning" style="font-size:12px">
      <strong>Info diagnosa:</strong><br>
      Folder target: <code><?= htmlspecialchars($folder_qris_check) ?></code><br>
      Folder ada? <?= $folder_ada ? '✅ Ya' : '❌ Tidak' ?><br>
      Bisa ditulis? <?= $folder_writable ? '✅ Ya' : '❌ Tidak' ?>
    </div>
    <?php endif; ?>

    <!-- QRIS Aktif -->
    <div class="section-title">QRIS Aktif Saat Ini</div>
    <div class="card" style="text-align:center;padding:20px">
      <?php if ($qris_aktif): ?>
        <img src="../assets/qris/<?= htmlspecialchars($qris_aktif['gambar_qris']) ?>"
             alt="QRIS Aktif"
             style="width:100%;max-width:260px;border-radius:12px;border:1px solid var(--gray-200)">
        <div style="margin-top:10px;font-weight:600;font-size:13px"><?= htmlspecialchars($qris_aktif['nama_qris']) ?></div>
        <div style="font-size:11px;color:var(--gray-400);margin-top:2px">Diunggah <?= date('d M Y H:i', strtotime($qris_aktif['created_at'])) ?></div>
      <?php else: ?>
        <div style="color:var(--gray-400);padding:30px 0">
          <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" style="opacity:0.3;display:block;margin:0 auto 12px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Belum ada gambar QRIS yang diunggah.
        </div>
      <?php endif; ?>
    </div>

    <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Form Upload -->
    <div class="section-title">Unggah QRIS Baru</div>
    <div class="card">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="upload_qris" value="1">
        <div class="form-group">
          <label class="form-label">Nama / Keterangan QRIS</label>
          <input type="text" name="nama_qris" class="form-control" placeholder="contoh: QRIS Bendahara PAM Ngasem" value="QRIS PAM Swadaya Ngasem">
        </div>
        <div class="form-group">
          <label class="form-label">Gambar QRIS (JPG/PNG, maks 3MB)</label>
          <input type="file" name="gambar_qris" class="form-control" accept=".jpg,.jpeg,.png" required>
        </div>
        <button type="submit" class="btn btn-primary">Unggah & Aktifkan QRIS</button>
      </form>
    </div>

    <!-- Riwayat QRIS -->
    <?php if (count($riwayat_qris) > 1): ?>
    <div class="section-title">Riwayat QRIS</div>
    <?php foreach ($riwayat_qris as $q): ?>
    <?php if ($q['id'] == ($qris_aktif['id'] ?? 0)) continue; ?>
    <div class="list-item">
      <img src="../assets/qris/<?= htmlspecialchars($q['gambar_qris']) ?>" style="width:42px;height:42px;border-radius:8px;object-fit:cover;flex-shrink:0">
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($q['nama_qris']) ?></div>
        <div style="font-size:11px;color:var(--gray-400)"><?= date('d M Y', strtotime($q['created_at'])) ?></div>
      </div>
      <a href="?aktifkan=<?= $q['id'] ?>" onclick="return confirm('Aktifkan kembali QRIS ini?')" style="font-size:12px;color:var(--primary);font-weight:600;text-decoration:none;flex-shrink:0">Aktifkan</a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
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
    <a href="verifikasi-pembayaran.php" class="nav-item">
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