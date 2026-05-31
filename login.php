<?php
session_start();
require_once 'config.php';
require_once 'security_helper.php';

if (isset($_SESSION['user_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
$selected_role = isset($_POST['role']) ? $_POST['role'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $selected_role = $_POST['role'];

    // Security Check 1: IP-based rate limiting (5 attempts per 5 minutes)
    if (checkRateLimit('login', 5, 5)) {
        $wait_time = getRateLimitWaitTime('login', 5, 5);
        $error = "Terlalu banyak percobaan login. Silahkan coba lagi dalam " . formatDuration($wait_time) . ".";
        logSecurityEvent('RATE_LIMIT_EXCEEDED', ['action' => 'login', 'username' => $username]);
    } 
    // Security Check 2: Account lockout (5 failed attempts = 15 minute lockout)
    else if (isAccountLocked($username, 5, 15)) {
        $lock_time = getAccountLockTime($username, 5, 15);
        $error = "Akun Anda terkunci karena terlalu banyak percobaan gagal. Silahkan coba lagi dalam " . formatDuration($lock_time) . ".";
        logSecurityEvent('ACCOUNT_LOCKED', ['username' => $username]);
    } else {
        $sql = "SELECT id, username, password, role, company_name FROM users WHERE username = '$username'";
        $result = $conn->query($sql);

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            
            // Check if selected role matches user's actual role
            if ($row['role'] !== $selected_role) {
                $error = "Role tidak sesuai dengan akun ini!";
                recordFailedLogin($username);
                recordRateLimitAttempt('login');
                logSecurityEvent('FAILED_LOGIN', ['username' => $username, 'reason' => 'role_mismatch']);
            } else if (password_verify($password, $row['password'])) {
                // Successful login
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['company_name'] = $row['company_name'];
                
                // Clear failed login attempts on successful login
                clearFailedLogins($username);
                logSecurityEvent('SUCCESSFUL_LOGIN', ['username' => $username, 'role' => $row['role']]);
                
                // Redirect based on role
                if ($row['role'] === 'siswa') {
                    header("Location: siswa_dashboard.php");
                } else {
                    header("Location: admin_dashboard.php");
                }
                exit;
            } else {
                $error = "Password salah!";
                recordFailedLogin($username);
                recordRateLimitAttempt('login');
                logSecurityEvent('FAILED_LOGIN', ['username' => $username, 'reason' => 'wrong_password']);
            }
        } else {
            $error = "Username tidak ditemukan!";
            recordRateLimitAttempt('login');
            logSecurityEvent('FAILED_LOGIN', ['username' => $username, 'reason' => 'username_not_found']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bursa Kerja Khusus SMTI Pontianak</title>
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
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .role-btn {
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--bg-main);
            color: var(--text-main);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .role-btn:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .role-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .form-section {
            display: none;
        }
        .form-section.show {
            display: block;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-chalkboard-user"></i>
            <h1 style="font-size: 1.75rem; color: var(--dark);">Login</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 8px;">Pilih role Anda untuk masuk</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Role Selection Step -->
        <div id="roleSelectionStep" class="<?php echo empty($selected_role) ? 'show' : ''; ?>">
            <p style="color: var(--dark); font-weight: 600; margin-bottom: 16px;">Masuk Sebagai:</p>
            <div class="role-selector">
                <button type="button" class="role-btn" onclick="selectRole('teacher', this)">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    Admin
                </button>
                <button type="button" class="role-btn" onclick="selectRole('recruiter', this)">
                    <i class="fa-solid fa-briefcase"></i>
                    Recruiter
                </button>
                <button type="button" class="role-btn" style="grid-column: 1 / -1;" onclick="selectRole('siswa', this)">
                    <i class="fa-solid fa-user-graduate"></i>
                    Siswa
                </button>
            </div>
            <div style="text-align: center; margin-top: 24px;">
                <a href="index.php" style="font-size: 0.875rem; color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
            </div>
        </div>

        <!-- Login Form Step -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="loginForm">
            <div id="formSection" class="form-section <?php echo !empty($selected_role) ? 'show' : ''; ?>">
                <input type="hidden" name="role" id="selectedRole" value="<?php echo htmlspecialchars($selected_role); ?>">

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

                <div style="text-align: center; margin-top: 24px; font-size: 0.9rem; color: var(--text-muted);" id="registerLink">
                    <!-- This will be updated by JavaScript based on role -->
                </div>

                <div style="text-align: center; margin-top: 16px;">
                    <a href="#" onclick="backToRoleSelection(); return false;" style="font-size: 0.875rem; color: var(--text-muted);"><i class="fa-solid fa-arrow-left"></i> Ganti Role</a>
                </div>
            </div>
        </form>
    </div>

    <script>
        function selectRole(role, button) {
            // Update hidden input
            document.getElementById('selectedRole').value = role;
            
            // Update button styles
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            // Hide role selection and show form
            document.getElementById('roleSelectionStep').classList.remove('show');
            document.getElementById('formSection').classList.add('show');
            
            // Update register link based on role
            const registerLink = document.getElementById('registerLink');
            if (role === 'recruiter') {
                registerLink.innerHTML = 'Belum mendaftarkan Perusahaan Anda? <a href="register.php" style="font-weight: 600;">Daftar di sini</a>';
            } else if (role === 'siswa') {
                registerLink.innerHTML = 'Belum punya akun? <a href="siswa_register.php" style="font-weight: 600;">Daftar di sini</a>';
            } else {
                registerLink.innerHTML = '';
            }
        }
        
        function backToRoleSelection() {
            document.getElementById('selectedRole').value = '';
            document.getElementById('roleSelectionStep').classList.add('show');
            document.getElementById('formSection').classList.remove('show');
            document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
        }
    </script>

</body>
</html>
