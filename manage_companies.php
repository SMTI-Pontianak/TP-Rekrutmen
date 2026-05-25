<?php
session_start();
require_once 'config.php';

// Only Admin (teacher) can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
$success = '';

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    
    // To safely delete a user, we must first delete all their physical CV files, then their jobs.
    $jobs_sql = "SELECT id FROM jobs WHERE user_id = $delete_user_id";
    $jobs_res = $conn->query($jobs_sql);
    
    if ($jobs_res->num_rows > 0) {
        while ($job = $jobs_res->fetch_assoc()) {
            $job_id = $job['id'];
            $apps_sql = "SELECT cv_file FROM applications WHERE job_id = $job_id";
            $apps_res = $conn->query($apps_sql);
            if ($apps_res->num_rows > 0) {
                while ($app = $apps_res->fetch_assoc()) {
                    $file_path = "uploads/" . $app['cv_file'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            $conn->query("DELETE FROM jobs WHERE id = $job_id"); // Cascade deletes apps
        }
    }
    
    if ($conn->query("DELETE FROM users WHERE id = $delete_user_id")) {
        $success = "Akun perusahaan berhasil dihapus!";
    } else {
        $error = "Gagal menghapus: " . $conn->error;
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_id'])) {
    $update_user_id = (int)$_POST['update_user_id'];
    $company_name = $conn->real_escape_string($_POST['company_name']);
    $username = $conn->real_escape_string($_POST['username']);
    
    // check if username exists for another user
    $check_sql = "SELECT id FROM users WHERE username = '$username' AND id != $update_user_id";
    if ($conn->query($check_sql)->num_rows > 0) {
        $error = "Username sudah digunakan oleh akun lain!";
    } else {
        $update_sql = "UPDATE users SET username = '$username', company_name = '$company_name' WHERE id = $update_user_id";
        if (!empty($_POST['new_password'])) {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET username = '$username', company_name = '$company_name', password = '$hashed' WHERE id = $update_user_id";
        }
        
        if ($conn->query($update_sql) === TRUE) {
            $success = "Data perusahaan berhasil diperbarui!";
        } else {
            $error = "Gagal memperbarui: " . $conn->error;
        }
    }
}

// Fetch edit data if requested
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = $conn->query("SELECT * FROM users WHERE id = $edit_id AND role = 'recruiter'");
    if ($res->num_rows > 0) {
        $edit_user = $res->fetch_assoc();
    }
}

// Fetch all recruiters
$users_sql = "SELECT * FROM users WHERE role = 'recruiter' ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Perusahaan - Bursa Kerja Khusus SMTI Pontianak</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 32px;
            margin-top: 32px;
        }
        .sidebar {
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            padding: 24px;
            border: 1px solid var(--border);
            height: fit-content;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li {
            margin-bottom: 8px;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font-weight: 500;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--primary-light);
            color: var(--primary);
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <a href="admin_dashboard.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                Bursa Kerja Khusus SMTI Pontianak
            </a>
            <div class="nav-links">
                <span style="font-weight: 600; color: var(--dark); margin-right: 16px;">Halo, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-grid" style="margin-bottom: 5rem;">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li><a href="add_job.php"><i class="fa-solid fa-plus"></i> Tambah Lowongan</a></li>
                <li><a href="manage_companies.php" class="active"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main>
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <?php if($edit_user): ?>
            <!-- Edit Form -->
            <div class="form-container" style="max-width: 100%; margin: 0 0 32px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h2 style="color: var(--dark);"><i class="fa-solid fa-pen-to-square"></i> Edit Perusahaan</h2>
                    <a href="manage_companies.php" class="btn btn-outline" style="padding: 0.4rem 1rem;"><i class="fa-solid fa-xmark"></i> Batal Edit</a>
                </div>
                <form action="manage_companies.php" method="POST">
                    <input type="hidden" name="update_user_id" value="<?php echo $edit_user['id']; ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="company_name">Nama Perusahaan</label>
                            <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo htmlspecialchars($edit_user['company_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="username">Username Login</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="new_password">Password Baru (Opsional)</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Perubahan</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <div style="padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 1.25rem;">Daftar Akun Perusahaan (Recruiter)</h2>
                    <!-- Link to public register if they want to add manually, or they can just use register.php -->
                    <a href="register.php" target="_blank" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;"><i class="fa-solid fa-plus"></i> Tambah Perusahaan</a>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Perusahaan</th>
                                <th>Username</th>
                                <th>Tgl Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users_result->num_rows > 0): ?>
                                <?php while($row = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500; color: var(--dark);">
                                            <i class="fa-regular fa-building" style="color: var(--primary); margin-right: 8px;"></i>
                                            <?php echo htmlspecialchars($row['company_name'] ?? 'Tidak diketahui'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="manage_companies.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" title="Edit Perusahaan">
                                                    <i class="fa-solid fa-pen"></i> Edit
                                                </a>
                                                <form action="manage_companies.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus perusahaan ini? SEMUA lowongan dan data pelamar terkait perusahaan ini akan ikut terhapus permanen!');" style="margin: 0;">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; cursor: pointer;" title="Hapus Perusahaan">
                                                        <i class="fa-solid fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada perusahaan yang terdaftar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
