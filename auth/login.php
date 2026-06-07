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
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, sans-serif;
      background: #0f172a;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 20%, rgba(12,110,242,0.35) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 80%, rgba(99,102,241,0.25) 0%, transparent 60%);
      z-index: 0;
    }
    body::after {
      content: '';
      position: fixed;
      inset: 0;
      background-image: radial-gradient(circle, rgba(255,255,255,0.04) 1px, transparent 1px);
      background-size: 28px 28px;
      z-index: 0;
    }
    .card {
      background: rgba(255,255,255,0.97);
      border-radius: 24px;
      padding: 40px 30px 34px;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 24px 80px rgba(0,0,0,0.40), 0 2px 0 rgba(255,255,255,0.1) inset;
      position: relative;
      z-index: 1;
      border: 1px solid rgba(255,255,255,0.15);
    }
    .logo { text-align: center; margin-bottom: 30px; }
    .logo-icon {
      width: 68px; height: 68px;
      background: linear-gradient(135deg, #0c6ef2 0%, #1a4fd8 100%);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
      box-shadow: 0 12px 28px rgba(12,110,242,0.45), 0 2px 4px rgba(12,110,242,0.2);
    }
    .logo-icon svg { width: 34px; height: 34px; }
    .logo h1 { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
    .logo p { font-size: 13px; color: #64748b; margin-top: 5px; font-weight: 500; }
    .logo p:last-child { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px; letter-spacing: 0.1px; }
    .form-control {
      width: 100%;
      padding: 12px 14px;
      border: 1.5px solid #e2e8f0;
      border-radius: 11px;
      font-size: 14px;
      color: #1e293b;
      background: #f8fafc;
      transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
      outline: none;
      font-family: inherit;
      font-weight: 500;
    }
    .form-control:focus { border-color: #0c6ef2; background: white; box-shadow: 0 0 0 3px rgba(12,110,242,0.12); }
    .btn-login {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #0c6ef2, #1a4fd8);
      color: white;
      border: none;
      border-radius: 13px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: opacity 0.2s, transform 0.12s, box-shadow 0.2s;
      letter-spacing: -0.1px;
      font-family: inherit;
      box-shadow: 0 6px 20px rgba(12,110,242,0.35);
    }
    .btn-login:hover { box-shadow: 0 8px 28px rgba(12,110,242,0.45); }
    .btn-login:active { transform: scale(0.97); opacity: 0.9; }
    .error-box {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      padding: 12px 14px;
      border-radius: 11px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 18px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .footer-note { text-align: center; margin-top: 22px; font-size: 11.5px; color: #94a3b8; line-height: 1.7; font-weight: 500; }
    .divider { border: none; border-top: 1px solid #f1f5f9; margin: 24px 0 20px; }
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
