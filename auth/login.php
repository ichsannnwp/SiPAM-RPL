<?php
session_start();
require_once '../config/db.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'pelanggan') {
        header('Location: ../pelanggan/dashboard.php');
    } else {
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                if ($user['role'] === 'pelanggan') {
                    header('Location: ../pelanggan/dashboard.php');
                } else {
                    header('Location: ../admin/dashboard.php');
                }
                exit;
            } else {
                $error = 'Email atau password salah. Silakan coba lagi.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Hubungi administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Masuk — SiPAM</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: linear-gradient(135deg, #1a56db 0%, #1e40af 50%, #1e3a8a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: white;
      border-radius: 20px;
      padding: 36px 28px 32px;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    }
    .logo {
      text-align: center;
      margin-bottom: 28px;
    }
    .logo-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #1a56db, #1e40af);
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
      box-shadow: 0 8px 20px rgba(26,86,219,0.35);
    }
    .logo-icon svg { width: 34px; height: 34px; }
    .logo h1 { font-size: 22px; font-weight: 800; color: #1f2937; }
    .logo p { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .form-control {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      font-size: 14px;
      color: #1f2937;
      background: #f9fafb;
      transition: border-color 0.2s, background 0.2s;
      outline: none;
    }
    .form-control:focus { border-color: #1a56db; background: white; }
    .btn-login {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #1a56db, #1e40af);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: opacity 0.2s, transform 0.1s;
      letter-spacing: 0.3px;
    }
    .btn-login:hover { opacity: 0.92; }
    .btn-login:active { transform: scale(0.98); }
    .error-box {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 12px 14px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 18px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .footer-note {
      text-align: center;
      margin-top: 22px;
      font-size: 11px;
      color: #9ca3af;
      line-height: 1.6;
    }
    .divider {
      border: none;
      border-top: 1px solid #f3f4f6;
      margin: 24px 0 20px;
    }
  </style>
</head>
<body>

<div class="card">

  <div class="logo">
    <div class="logo-icon">
      <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
        <path d="M12 6v6l4 2"/>
      </svg>
    </div>
    <h1>SiPAM</h1>
    <p>Sistem PAM Swadaya Masyarakat</p>
    <p style="font-size:11px;color:#9ca3af;margin-top:2px">Desa Ngasem · Kec. Masaran</p>
  </div>

  <?php if ($error): ?>
  <div class="error-box">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" class="form-control"
             placeholder="Masukkan email Anda"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             required autofocus>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" class="form-control"
             placeholder="Masukkan password Anda"
             required>
    </div>
    <button type="submit" class="btn-login">Masuk ke Sistem</button>
  </form>

  <hr class="divider">
  <div class="footer-note">
    PAM Swadaya Masyarakat Desa Ngasem<br>
    Kecamatan Masaran, Kabupaten Sragen
  </div>

</div>

</body>
</html>
