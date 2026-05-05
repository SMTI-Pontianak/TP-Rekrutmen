<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role, company_name FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['company_name'] = $row['company_name'];
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TP Rekrutmen</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-main), var(--primary-light));
        }
        .login-card {
            background: var(--bg-surface);
            padding: 48px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 450px;
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
            <i class="fa-solid fa-chalkboard-user"></i>
            <h1 style="font-size: 1.75rem; color: var(--dark);">Login Admin / Recruiter</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 8px;">Masuk ke sistem pengelolaan rekrutmen</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-user" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="username" name="username" class="form-control" style="padding-left: 48px;" required placeholder="Masukkan username">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="password" id="password" name="password" class="form-control" style="padding-left: 48px;" required placeholder="Masukkan password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.125rem; margin-top: 16px;">
                Login
            </button>

            <div style="text-align: center; margin-top: 24px; font-size: 0.9rem; color: var(--text-muted);">
                Belum mendaftarkan Perusahaan Anda? <a href="register.php" style="font-weight: 600;">Daftar di sini</a>
            </div>

            <div style="text-align: center; margin-top: 16px;">
                <a href="index.php" style="font-size: 0.875rem; color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
            </div>
        </form>
    </div>

</body>
</html>
