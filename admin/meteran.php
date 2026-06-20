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

// Proses input meteran + auto kalkulasi tagihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_meteran'])) {
    try {
        $pdo->beginTransaction();
        $saved = 0;

        foreach ($_POST['angka_ini'] as $pelanggan_id => $angka_ini) {
            $angka_ini = trim($angka_ini);
            if ($angka_ini === '' || !is_numeric($angka_ini)) continue;

            $angka_lalu = (int)$_POST['angka_lalu'][$pelanggan_id];
            $angka_ini  = (int)$angka_ini;
            $selisih    = max(0, $angka_ini - $angka_lalu);

            // Cek sudah ada meteran periode ini
            $cek = $pdo->prepare("SELECT id FROM meteran WHERE pelanggan_id=? AND periode=?");
            $cek->execute([$pelanggan_id, $periode]);
            if ($cek->fetch()) continue; // skip duplikasi

            // Simpan meteran
            $stmt = $pdo->prepare("INSERT INTO meteran (pelanggan_id, angka_bulan_ini, angka_bulan_lalu, periode, tanggal_input) VALUES (?,?,?,?,?)");
            $stmt->execute([$pelanggan_id, $angka_ini, $angka_lalu, $periode, date('Y-m-d')]);
            $meteran_id = $pdo->lastInsertId();

            // Ambil tarif aktif
            $tarif = $pdo->query("SELECT id, tarif_per_m3 FROM master_tarif WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
            if (!$tarif) continue;

            // Cek free_period
            $plg = $pdo->prepare("SELECT free_period FROM pelanggan WHERE id=?");
            $plg->execute([$pelanggan_id]);
            $plg_data = $plg->fetch();

            $total = $plg_data['free_period'] ? 0 : ($selisih * $tarif['tarif_per_m3']);
            $jatuh_tempo = date('Y-m-d', strtotime(date('Y-m-01', strtotime($periode . '-01')) . ' +1 month -1 day'));

            // Simpan tagihan
            $stmt2 = $pdo->prepare("INSERT INTO tagihan (pelanggan_id, meteran_id, tarif_id, periode, pemakaian_m3, total_tagihan, status, jatuh_tempo) VALUES (?,?,?,?,?,?,?,?)");
            $stmt2->execute([$pelanggan_id, $meteran_id, $tarif['id'], $periode, $selisih, $total, 'belum_bayar', $jatuh_tempo]);

            // Update free_period jadi false setelah bulan pertama
            if ($plg_data['free_period']) {
                $pdo->prepare("UPDATE pelanggan SET free_period=0 WHERE id=?")->execute([$pelanggan_id]);
            }

            $saved++;
        }

        $pdo->commit();
        $pesan = "$saved data meteran berhasil disimpan dan tagihan otomatis dibuat.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Ambil tarif aktif
$tarif_aktif = $pdo->query("SELECT tarif_per_m3 FROM master_tarif WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
$tarif_per_m3 = $tarif_aktif ? $tarif_aktif['tarif_per_m3'] : 2500;

// Ambil semua pelanggan aktif beserta data meteran bulan ini
$pelanggan_list = $pdo->prepare("
    SELECT p.id, p.kode_pelanggan, p.nama_lengkap, p.free_period,
           m.id as meteran_id, m.angka_bulan_ini, m.angka_bulan_lalu,
           (SELECT angka_bulan_ini FROM meteran WHERE pelanggan_id=p.id ORDER BY id DESC LIMIT 1) as angka_terakhir
    FROM pelanggan p
    LEFT JOIN meteran m ON m.pelanggan_id=p.id AND m.periode=?
    WHERE p.status='aktif'
    ORDER BY p.kode_pelanggan ASC
");
$pelanggan_list->execute([$periode]);
$pelanggan_list = $pelanggan_list->fetchAll();

$sudah_input = array_filter($pelanggan_list, fn($p) => $p['meteran_id']);
$belum_input = array_filter($pelanggan_list, fn($p) => !$p['meteran_id']);

// Hitung estimasi total tagihan bulan ini
$estimasi = 0;
foreach ($pelanggan_list as $p) {
    if ($p['meteran_id'] && !$p['free_period']) {
        $selisih = max(0, $p['angka_bulan_ini'] - $p['angka_bulan_lalu']);
        $estimasi += $selisih * $tarif_per_m3;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Input Meteran — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app">

  <div class="header">
    <div>
      <div class="h1">Input Meteran Bulanan</div>
      <div class="subtitle"><?= date('F Y', strtotime($periode.'-01')) ?> · Rp <?= number_format($tarif_per_m3,0,',','.') ?>/m³</div>
    </div>
  </div>

  <div class="page">

    <!-- Pilih periode -->
    <div class="card" style="display:flex;align-items:center;gap:10px;padding:12px 14px">
      <svg fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24" width="20"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <form method="GET" style="flex:1;display:flex;gap:8px">
        <input type="month" name="periode" value="<?= $periode ?>" class="form-control" style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
      </form>
    </div>

    <?php if ($pesan): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Warning input -->
    <?php if (date('d') > 5): ?>
    <div class="alert alert-warning" style="display:flex;gap:8px;align-items:flex-start">
      <span>⚠️</span>
      <span>Input melebihi periode standar (tanggal 1–5). Pastikan semua angka meteran sudah dicek sebelum menyimpan.</span>
    </div>
    <?php endif; ?>

    <!-- Ringkasan -->
    <div class="summary-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:12px">
      <div class="summary-card blue" style="padding:10px 12px">
        <div class="label">Total</div>
        <div class="value" style="font-size:22px"><?= count($pelanggan_list) ?></div>
      </div>
      <div class="summary-card green" style="padding:10px 12px">
        <div class="label">Sudah</div>
        <div class="value" style="font-size:22px"><?= count($sudah_input) ?></div>
      </div>
      <div class="summary-card orange" style="padding:10px 12px">
        <div class="label">Belum</div>
        <div class="value" style="font-size:22px"><?= count($belum_input) ?></div>
      </div>
    </div>

    <?php if (!empty($belum_input)): ?>
    <!-- Form input meteran -->
    <div class="section-title">Pencatatan — <?= date('F Y', strtotime($periode.'-01')) ?> (<?= count($belum_input) ?> dari <?= count($pelanggan_list) ?>)</div>

    <form method="POST">
      <input type="hidden" name="simpan_meteran" value="1">
      <?php foreach ($belum_input as $p): ?>
      <?php $angka_lalu = $p['angka_terakhir'] ?? 0; ?>
      <div class="card" style="padding:14px;margin-bottom:8px" id="card-<?= $p['id'] ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
          <div>
            <div style="font-weight:700;font-size:14px;color:var(--primary)"><?= $p['kode_pelanggan'] ?></div>
            <div style="font-size:13px;color:var(--gray-800)"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
          </div>
          <?php if ($p['free_period']): ?>
            <span class="badge badge-info">Gratis</span>
          <?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <div style="font-size:11px;color:var(--gray-600);margin-bottom:4px">Bln Lalu</div>
            <div style="font-size:16px;font-weight:600;color:var(--gray-600)"><?= number_format($angka_lalu) ?></div>
            <input type="hidden" name="angka_lalu[<?= $p['id'] ?>]" value="<?= $angka_lalu ?>">
          </div>
          <div>
            <div style="font-size:11px;color:var(--gray-600);margin-bottom:4px">Bln Ini</div>
            <input 
              type="number" 
              name="angka_ini[<?= $p['id'] ?>]" 
              class="form-control" 
              placeholder="Ketik..." 
              min="<?= $angka_lalu ?>"
              oninput="hitungSelisih(<?= $p['id'] ?>, <?= $angka_lalu ?>, <?= $tarif_per_m3 ?>, <?= $p['free_period'] ? 1 : 0 ?>)"
              style="padding:8px 10px;font-size:15px"
            >
          </div>
        </div>
        <div id="preview-<?= $p['id'] ?>" style="margin-top:8px;padding:8px;background:var(--gray-50);border-radius:8px;display:none;font-size:13px">
          <span style="color:var(--gray-600)">Selisih: </span><span id="selisih-<?= $p['id'] ?>">0</span> m³ &nbsp;·&nbsp;
          <span style="color:var(--primary);font-weight:600" id="nominal-<?= $p['id'] ?>">Rp 0</span>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Estimasi -->
      <div style="background:var(--primary);color:white;padding:14px 16px;border-radius:var(--radius);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:13px;opacity:0.85">Estimasi Total Tagihan</div>
        <div style="font-size:18px;font-weight:700" id="estimasi-total">Rp <?= number_format($estimasi,0,',','.') ?></div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-bottom:8px">Hitung & Simpan Semua</button>
    </form>
    <?php endif; ?>

    <!-- Sudah diinput -->
    <?php if (!empty($sudah_input)): ?>
    <div class="section-title" style="margin-top:8px">Sudah Dicatat (<?= count($sudah_input) ?>)</div>
    <?php foreach ($sudah_input as $p): ?>
    <?php $selisih = max(0, $p['angka_bulan_ini'] - $p['angka_bulan_lalu']); ?>
    <div class="list-item" style="opacity:0.8">
      <div style="width:38px;height:38px;border-radius:8px;background:var(--success-light);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg fill="none" stroke="var(--success)" stroke-width="2.5" viewBox="0 0 24 24" width="18"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div style="flex:1">
        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['nama_lengkap']) ?></div>
        <div style="font-size:12px;color:var(--gray-600)"><?= $p['kode_pelanggan'] ?> · <?= $p['angka_bulan_lalu'] ?> → <?= $p['angka_bulan_ini'] ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:600;font-size:13px;color:var(--primary)"><?= $selisih ?> m³</div>
        <?php if (!$p['free_period']): ?>
        <div style="font-size:12px;color:var(--gray-600)">Rp <?= number_format($selisih * $tarif_per_m3,0,',','.') ?></div>
        <?php else: ?>
        <span class="badge badge-info" style="font-size:11px">Gratis</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($pelanggan_list)): ?>
    <div style="text-align:center;color:var(--gray-400);padding:40px 0">Tidak ada pelanggan aktif.</div>
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
    <a href="meteran.php" class="nav-item active">
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
<script>
function hitungSelisih(id, lalu, tarif, gratis) {
  const ini = parseInt(document.querySelector(`[name="angka_ini[${id}]"]`).value) || 0;
  const selisih = Math.max(0, ini - lalu);
  const nominal = gratis ? 0 : selisih * tarif;
  document.getElementById(`selisih-${id}`).textContent = selisih.toLocaleString('id-ID');
  document.getElementById(`nominal-${id}`).textContent = 'Rp ' + nominal.toLocaleString('id-ID');
  document.getElementById(`preview-${id}`).style.display = 'block';
}
</script>
</body>
</html>
