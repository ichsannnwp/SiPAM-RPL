<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
  $role = $_SESSION['role'];
  header('Location: ' . ($role === 'pelanggan' ? '../pelanggan/dashboard.php' : '../admin/dashboard.php'));
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = trim($_POST['email']);
  $password = $_POST['password'];
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch();
  if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
    header('Location: ' . ($user['role'] === 'pelanggan' ? '../pelanggan/dashboard.php' : '../admin/dashboard.php'));
    exit;
  } else {
    $error = 'Email atau password salah.';
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Login — SiPAM</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    body { background: var(--primary); align-items: flex-end; }
    .login-wrap {
      width: 100%; max-width: 430px;
      background: white;
      border-radius: 24px 24px 0 0;
      padding: 32px 24px 40px;
      min-height: 70vh;
    }
    .login-top {
      text-align: center;
      padding: 40px 0 32px;
      color: white;
      width: 100%; max-width: 430px;
    }
    .login-top h1 { font-size: 36px; font-weight: 800; letter-spacing: -1px; }
    .login-top p  { font-size: 14px; opacity: 0.85; margin-top: 6px; }
    body { flex-direction: column; }
  </style>
</head>
<body>
  <div class="login-top">
    <h1>SiPAM</h1>
    <p>Sistem PAM Swadaya Masyarakat</p>
  </div>
  <div class="login-wrap">
    <h2 style="font-size:20px;margin-bottom:6px">Selamat datang</h2>
    <p style="color:var(--gray-600);font-size:14px;margin-bottom:24px">Masuk untuk melanjutkan</p>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" placeholder="admin@sipam.com" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:8px">Masuk</button>
    </form>
  </div>
</body>
</html>