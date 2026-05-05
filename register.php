<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validations
    if ($password !== $confirm_password) {
        $error = "Password tidak cocok!";
    } else {
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = '$username'";
        $check_res = $conn->query($check_sql);
        if ($check_res->num_rows > 0) {
            $error = "Username sudah digunakan. Silakan pilih username lain.";
        } else {
            // Insert
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_sql = "INSERT INTO users (username, password, role, company_name) VALUES ('$username', '$hashed_password', 'recruiter', '$company_name')";
            
            if ($conn->query($insert_sql) === TRUE) {
                $success = "Pendaftaran berhasil! Anda sekarang bisa login.";
            } else {
                $error = "Terjadi kesalahan: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Perusahaan - TP Rekrutmen</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-main), var(--primary-light));
            padding: 2rem 0;
        }
        .login-card {
            background: var(--bg-surface);
            padding: 48px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-header i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-building"></i>
            <h1 style="font-size: 1.75rem; color: var(--dark);">Pendaftaran Perusahaan</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 8px;">Daftar untuk mulai memposting lowongan kerja</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
            <div style="text-align: center; margin-top: 24px;">
                <a href="login.php" class="btn btn-primary" style="width: 100%; justify-content: center;">Ke Halaman Login</a>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                
                <div class="form-group">
                    <label class="form-label" for="company_name">Nama Perusahaan / Instansi</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-building" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="company_name" name="company_name" class="form-control" style="padding-left: 48px;" required placeholder="Contoh: PT. Maju Bersama">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username Login</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-user" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="username" name="username" class="form-control" style="padding-left: 48px;" required placeholder="Pilih username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="password" id="password" name="password" class="form-control" style="padding-left: 48px;" required placeholder="Buat password">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Konfirmasi Password</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" style="padding-left: 48px;" required placeholder="Ulangi password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.125rem; margin-top: 8px;">
                    Daftar Sekarang
                </button>

                <div style="text-align: center; margin-top: 24px; font-size: 0.9rem; color: var(--text-muted);">
                    Sudah punya akun? <a href="login.php" style="font-weight: 600;">Login di sini</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
