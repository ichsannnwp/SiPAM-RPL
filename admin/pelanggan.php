<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'desa'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Hapus pelanggan
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    try {
        $pdo->beginTransaction();
        // Hapus user terkait dulu
        $plg = $pdo->prepare("SELECT user_id FROM pelanggan WHERE id = ?");
        $plg->execute([$id]);
        $data = $plg->fetch();
        $pdo->prepare("DELETE FROM pelanggan WHERE id = ?")->execute([$id]);
        if ($data && $data['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$data['user_id']]);
        }
        $pdo->commit();
        header('Location: pelanggan.php?pesan=hapus');
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: pelanggan.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

// Edit status pelanggan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $pdo->prepare("UPDATE pelanggan SET nama_lengkap=?, no_telepon=?, alamat=?, status=? WHERE id=?");
    $stmt->execute([
        trim($_POST['nama_lengkap']),
        trim($_POST['no_telepon']),
        trim($_POST['alamat']),
        $_POST['status'],
        (int)$_POST['edit_id']
    ]);
    header('Location: pelanggan.php?pesan=edit');
    exit;
}

// Pencarian
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE p.nama_lengkap LIKE ? OR p.kode_pelanggan LIKE ? OR p.nik LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT p.*, u.email 
    FROM pelanggan p 
    LEFT JOIN users u ON p.user_id = u.id 
    $where 
    ORDER BY p.id DESC
");
$stmt->execute($params);
$pelanggan_list = $stmt->fetchAll();

// Data untuk modal edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM pelanggan WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $edit_data = $s->fetch();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Data Pelanggan — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Data Pelanggan</div>
      <div class="subtitle"><?= count($pelanggan_list) ?> pelanggan terdaftar</div>
    </div>
    <a href="tambah-pelanggan.php" style="background:rgba(255,255,255,0.2);border-radius:8px;padding:6px 12px;color:white;text-decoration:none;font-size:13px;font-weight:600">+ Tambah</a>
  </div>

  <div class="page">

    <?php if (isset($_GET['pesan'])): ?>
      <div class="alert alert-success">
        <?= $_GET['pesan'] === 'hapus' ? 'Pelanggan berhasil dihapus.' : 'Data pelanggan berhasil diperbarui.' ?>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" style="margin-bottom:12px">
      <input 
        class="form-control" 
        type="text" 
        name="q" 
        placeholder="Cari nama, kode, atau NIK..." 
        value="<?= htmlspecialchars($search) ?>"
        style="background:white"
      >
    </form>

    <!-- List pelanggan -->
    <?php if (empty($pelanggan_list)): ?>
      <div style="text-align:center;color:var(--gray-400);padding:40px 0">
        <?= $search ? 'Tidak ada hasil untuk "'.$search.'"' : 'Belum ada pelanggan terdaftar.' ?>
      </div>
    <?php else: ?>
      <?php foreach ($pelanggan_list as $p): ?>
      <div class="card" style="padding:14px;margin-bottom:8px">
        <div style="display:flex;align-items:flex-start;gap:12px">
          <!-- Avatar -->
          <div style="width:42px;height:42px;border-radius:10px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--primary);font-size:16px;flex-shrink:0">
            <?= strtoupper(substr($p['nama_lengkap'], 0, 1)) ?>
          </div>
          <!-- Info -->
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
            <div style="font-size:12px;color:var(--gray-600);margin-top:2px"><?= $p['kode_pelanggan'] ?> · <?= htmlspecialchars($p['no_telepon']) ?></div>
            <div style="font-size:12px;color:var(--gray-600);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['alamat']) ?></div>
            <div style="margin-top:6px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
              <?php
                $badge = match($p['status']) {
                  'aktif'    => 'badge-success',
                  'nonaktif' => 'badge-danger',
                  default    => 'badge-warning'
                };
              ?>
              <span class="badge <?= $badge ?>"><?= ucfirst($p['status']) ?></span>
              <?php if ($p['free_period']): ?>
                <span class="badge badge-info">Bebas tagihan</span>
              <?php endif; ?>
            </div>
          </div>
          <!-- Actions -->
          <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0">
            <a href="?edit=<?= $p['id'] ?>" style="font-size:12px;color:var(--primary);font-weight:500;text-decoration:none">Edit</a>
            <a href="?hapus=<?= $p['id'] ?>" 
               onclick="return confirm('Hapus pelanggan <?= htmlspecialchars(addslashes($p['nama_lengkap'])) ?>? Aksi ini tidak bisa dibatalkan.')"
               style="font-size:12px;color:var(--danger);font-weight:500;text-decoration:none">Hapus</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <!-- Modal Edit -->
  <?php if ($edit_data): ?>
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;display:flex;align-items:flex-end;justify-content:center">
    <div style="background:white;width:100%;max-width:430px;border-radius:24px 24px 0 0;padding:24px;max-height:85vh;overflow-y:auto">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
        <h3 style="margin:0;font-size:17px">Edit Pelanggan</h3>
        <a href="pelanggan.php" style="color:var(--gray-400);text-decoration:none;font-size:22px;line-height:1">&times;</a>
      </div>
      <form method="POST">
        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input class="form-control" type="text" name="nama_lengkap" value="<?= htmlspecialchars($edit_data['nama_lengkap']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">No. Telepon</label>
          <input class="form-control" type="text" name="no_telepon" value="<?= htmlspecialchars($edit_data['no_telepon']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="alamat" rows="3" required><?= htmlspecialchars($edit_data['alamat']) ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control" name="status">
            <option value="aktif"    <?= $edit_data['status']==='aktif'    ? 'selected':'' ?>>Aktif</option>
            <option value="nonaktif" <?= $edit_data['status']==='nonaktif' ? 'selected':'' ?>>Nonaktif</option>
            <option value="calon"    <?= $edit_data['status']==='calon'    ? 'selected':'' ?>>Calon</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Beranda
    </a>
    <a href="pelanggan.php" class="nav-item active">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Pelanggan
    </a>
    <a href="meteran.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      Meteran
    </a>
    <a href="laporan.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Laporan
    </a>
    <a href="../auth/logout.php" class="nav-item">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      Keluar
    </a>
  </nav>

</div>
</body>
</html>