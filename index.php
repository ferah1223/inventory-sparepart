<?php
// index.php - Halaman Login
require_once 'config/database.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND aktif = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Inventaris Bengkel Jaya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card animate-in">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-motorcycle"></i>
            </div>
            <h1>Inventaris Spare Part</h1>
            <p>Bengkel Jaya — Sistem Manajemen Gudang</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= sanitize($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div style="position:relative;">
                        <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username"
                               value="<?= sanitize($_POST['username'] ?? '') ?>" style="padding-left:40px;" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div style="position:relative;">
                        <i class="fas fa-lock" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password" style="padding-left:40px;" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-accent w-full" style="margin-top:8px;">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>

            <div class="text-center mt-2" style="padding-top:16px;border-top:1px solid var(--border);">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Default: <strong>admin</strong> / <strong>password</strong>
                </small>
            </div>
        </div>
    </div>
</body>
</html>
