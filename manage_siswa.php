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
    
    // Delete all applications from this siswa
    $conn->query("DELETE FROM applications WHERE id IN (SELECT id FROM applications WHERE job_id IN (SELECT id FROM jobs))");
    
    // Delete the siswa user
    if ($conn->query("DELETE FROM users WHERE id = $delete_user_id AND role = 'siswa'")) {
        $success = "Akun siswa berhasil dihapus!";
    } else {
        $error = "Gagal menghapus: " . $conn->error;
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_id'])) {
    $update_user_id = (int)$_POST['update_user_id'];
    $username = $conn->real_escape_string($_POST['username']);
    $konsentrasi_keahlian = $_POST['konsentrasi_keahlian'];
    
    // check if username exists for another user
    $check_sql = "SELECT id FROM users WHERE username = '$username' AND id != $update_user_id";
    if ($conn->query($check_sql)->num_rows > 0) {
        $error = "Username sudah digunakan oleh akun lain!";
    } else {
        $update_sql = "UPDATE users SET username = '$username', konsentrasi_keahlian = '$konsentrasi_keahlian' WHERE id = $update_user_id";
        if (!empty($_POST['new_password'])) {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET username = '$username', konsentrasi_keahlian = '$konsentrasi_keahlian', password = '$hashed' WHERE id = $update_user_id";
        }
        if ($conn->query($update_sql) === TRUE) {
            $success = "Data siswa berhasil diperbarui!";
            header("Location: manage_siswa.php");
            exit;
        } else {
            $error = "Gagal memperbarui: " . $conn->error;
        }
    }
}

// Fetch edit data if requested
$edit_user = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = $conn->query("SELECT * FROM users WHERE id = $edit_id AND role = 'siswa'");
    if ($res->num_rows > 0) {
        $edit_user = $res->fetch_assoc();
    }
}

// Fetch all siswa
$users_sql = "SELECT * FROM users WHERE role = 'siswa' ORDER BY created_at DESC";
$users_result = $conn->query($users_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Siswa - Bursa Kerja Khusus SMTI Pontianak</title>
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
        .table-responsive {
            overflow-x: auto;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-buttons button, .action-buttons a {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            cursor: pointer;
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
                <li><a href="manage_companies.php"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <li><a href="manage_siswa.php" class="active"><i class="fa-solid fa-user-graduate"></i> Kelola Siswa</a></li>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main style="background: var(--bg-surface); border-radius: var(--radius-md); padding: 2rem; border: 1px solid var(--border);">
            <h2 style="font-size: 1.75rem; margin-bottom: 1.5rem; color: var(--dark);">
                <i class="fa-solid fa-user-graduate"></i> Kelola Siswa
            </h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Edit Form (Modal-style) -->
            <?php if ($edit_user): ?>
                <div style="background: var(--bg-main); padding: 2rem; border-radius: var(--radius-md); margin-bottom: 2rem; border-left: 4px solid var(--primary);">
                    <h3 style="margin-bottom: 1rem;">Edit Data Siswa</h3>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <input type="hidden" name="update_user_id" value="<?php echo $edit_user['id']; ?>">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Konsentrasi Keahlian</label>
                                <select name="konsentrasi_keahlian" class="form-control" required>
                                    <option value="Teknik Kimia Industri" <?php echo ($edit_user['konsentrasi_keahlian'] === 'Teknik Kimia Industri') ? 'selected' : ''; ?>>Teknik Kimia Industri</option>
                                    <option value="Teknik Pemesinan" <?php echo ($edit_user['konsentrasi_keahlian'] === 'Teknik Pemesinan') ? 'selected' : ''; ?>>Teknik Pemesinan</option>
                                    <option value="Analisis Pengujian Laboratorium" <?php echo ($edit_user['konsentrasi_keahlian'] === 'Analisis Pengujian Laboratorium') ? 'selected' : ''; ?>>Analisis Pengujian Laboratorium</option>
                                    <option value="Teknik Otomasi Industri" <?php echo ($edit_user['konsentrasi_keahlian'] === 'Teknik Otomasi Industri') ? 'selected' : ''; ?>>Teknik Otomasi Industri</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Minimal 6 karakter">
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="manage_siswa.php" class="btn btn-outline">
                                <i class="fa-solid fa-times"></i> Batalkan
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Siswa Table -->
            <div class="table-responsive">
                <table class="applications-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--bg-main);">
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border);">Username</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border);">Konsentrasi Keahlian</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 2px solid var(--border);">Terdaftar Pada</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 2px solid var(--border);">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_result->num_rows > 0): ?>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1rem;">
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo htmlspecialchars($user['konsentrasi_keahlian']); ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <div class="action-buttons" style="justify-content: center;">
                                            <a href="manage_siswa.php?edit_id=<?php echo $user['id']; ?>" class="btn btn-primary" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                                                <i class="fa-solid fa-edit"></i> Edit
                                            </a>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Yakin ingin menghapus akun siswa ini? Semua data aplikasi akan ikut terhapus!');"> 
                                                <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                                                    <i class="fa-solid fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                                    <i class="fa-regular fa-user" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    Belum ada siswa terdaftar
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <footer style="text-align: center; padding: 3rem 0; border-top: 1px solid var(--border); color: var(--text-muted); margin-top: 4rem;">
        <p>&copy; <?php echo date('Y'); ?> Bursa Kerja Khusus SMTI Pontianak. All rights reserved.</p>
    </footer>

</body>
</html>
