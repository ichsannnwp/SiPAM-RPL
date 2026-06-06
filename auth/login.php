<?php
session_start();
require_once '../config/db.php';

// Jika tidak sengaja tersangkut session lama, bersihkan otomatis jika mengakses login dengan parameter ?clean
if (isset($_GET['clean'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']); 

    try {
        // 1. Cek koneksi & cari user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Cek kecocokan password biner
            if (password_verify($password, $user['password'])) {
                
                // Regenerasi session agar bersih
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                // 3. Tes Pengalihan (Redirect)
                if ($user['role'] === 'pelanggan') {
                    header('Location: ../pelanggan/dashboard.php');
                } else {
                    header('Location: ../admin/dashboard.php');
                }
                exit;
            } else {
                $error = "Password SALAH. Karakter yang Anda ketik tidak cocok dengan hash di database.";
            }
        } else {
            $error = "Email '" . htmlspecialchars($email) . "' TIDAK DITEMUKAN di tabel users.";
        }
    } catch (PDOException $e) {
        $error = "Error Sistem Database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login SiPAM Debug Mode</title>
  <style>
    body { background: #111827; color: #f3f4f6; font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
    .box { background: #1f2937; padding: 30px; border-radius: 12px; width: 100%; max-width: 360px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid #374151; }
    .input-group { margin-bottom: 15px; }
    .input-group label { display: block; margin-bottom: 5px; font-size: 14px; color: #9ca3af; }
    .form-control { width: 100%; padding: 10px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; box-sizing: border-box; }
    .btn { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
    .btn:hover { background: #1d4ed8; }
    .error-box { background: #7f1d1d; border: 1px solid #f87171; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; }
  </style>
</head>
<body>

<div class="box">
  <h3 style="margin-top:0;">SiPAM Login (Debug Mode)</h3>
  
  <?php if ($error): ?>
    <div class="error-box"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="input-group">
      <label>Email</label>
      <input type="email" name="email" class="form-control" value="warga@sipam.com" required>
    </div>
    <div class="input-group">
      <label>Password</label>
      <input type="password" name="password" class="form-control" placeholder="Ketik: warga123" required>
    </div>
    <button type="submit" class="btn">Masuk & Tes Alur</button>
  </form>
  
  <p style="text-align:center; font-size:11px; margin-top:20px;">
    <a href="login.php?clean=1" style="color:#60a5fa; text-decoration:none;">⚠️ Klik di sini untuk Reset Paksa Session Browser</a>
  </p>
</div>

</body>
</html>