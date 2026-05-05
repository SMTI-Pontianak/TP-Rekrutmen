<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle Job Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job_id'])) {
    $delete_job_id = (int)$_POST['delete_job_id'];
    
    // Check ownership first if user is recruiter
    $can_delete = true;
    if ($_SESSION['role'] === 'recruiter') {
        $check_sql = "SELECT id FROM jobs WHERE id = $delete_job_id AND user_id = {$_SESSION['user_id']}";
        $check_res = $conn->query($check_sql);
        if ($check_res->num_rows == 0) {
            $can_delete = false;
        }
    }
    
    if ($can_delete) {
        // First, delete all physical CV files associated with this job
        $apps_sql = "SELECT cv_file FROM applications WHERE job_id = $delete_job_id";
        $apps_res = $conn->query($apps_sql);
        if ($apps_res->num_rows > 0) {
            while ($app = $apps_res->fetch_assoc()) {
                $file_path = "uploads/" . $app['cv_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        
        // Delete the job (DB will cascade delete the application rows)
        $conn->query("DELETE FROM jobs WHERE id = $delete_job_id");
    }
    
    // Redirect to refresh
    header("Location: admin_dashboard.php");
    exit;
}

// Fetch stats
$where_clause = "";
if ($_SESSION['role'] === 'recruiter') {
    $where_clause = "WHERE user_id = {$_SESSION['user_id']}";
}

$jobs_count_sql = "SELECT COUNT(*) as count FROM jobs $where_clause";
$jobs_count = $conn->query($jobs_count_sql)->fetch_assoc()['count'];

if ($_SESSION['role'] === 'recruiter') {
    $apps_count_sql = "SELECT COUNT(*) as count FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.user_id = {$_SESSION['user_id']}";
} else {
    $apps_count_sql = "SELECT COUNT(*) as count FROM applications";
}
$apps_count = $conn->query($apps_count_sql)->fetch_assoc()['count'];

// Fetch jobs with application count
$sql = "SELECT j.*, COUNT(a.id) as app_count 
        FROM jobs j 
        LEFT JOIN applications a ON j.id = a.job_id ";
if ($_SESSION['role'] === 'recruiter') {
    $sql .= " WHERE j.user_id = {$_SESSION['user_id']} ";
}
$sql .= " GROUP BY j.id ORDER BY j.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - TP Rekrutmen</title>
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
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            padding: 24px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-full);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .stat-info h3 {
            font-size: 2rem;
            margin-bottom: 4px;
        }
        .stat-info p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <a href="admin_dashboard.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                TP<span>Rekrutmen</span> - Admin
            </a>
            <div class="nav-links">
                <span style="font-weight: 600; color: var(--dark); margin-right: 16px;">Halo, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="btn btn-outline" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-grid">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li><a href="add_job.php"><i class="fa-solid fa-plus"></i> Tambah Lowongan</a></li>
                <?php if($_SESSION['role'] === 'teacher'): ?>
                <li><a href="manage_companies.php"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <?php endif; ?>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main>
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jobs_count; ?></h3>
                        <p>Total Lowongan</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ECFDF5; color: #10B981;"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $apps_count; ?></h3>
                        <p>Total Pelamar</p>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div style="padding: 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="font-size: 1.25rem;">Daftar Lowongan Pekerjaan</h2>
                    <a href="add_job.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;"><i class="fa-solid fa-plus"></i> Tambah Baru</a>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Judul Posisi</th>
                                <th>Perusahaan</th>
                                <th>Tanggal Posting</th>
                                <th>Jml Pelamar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 500; color: var(--dark);"><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <span class="badge badge-primary" style="font-size: 0.85rem; padding: 4px 12px;">
                                                <?php echo $row['app_count']; ?> Pelamar
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="view_applicants.php?job_id=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fa-solid fa-eye"></i> Lihat Pelamar</a>
                                                <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus lowongan ini? Semua data pelamar beserta file CV mereka akan ikut terhapus permanen!');" style="margin: 0;">
                                                    <input type="hidden" name="delete_job_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; cursor: pointer;" title="Hapus Lowongan">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 32px; color: var(--text-muted);">Belum ada data lowongan.</td>
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
