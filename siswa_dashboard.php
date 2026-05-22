<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: login.php");
    exit;
}

// Fetch all available jobs
$jobs_sql = "SELECT * FROM jobs ORDER BY created_at DESC";
$jobs_result = $conn->query($jobs_sql);

// Fetch applications made by this siswa
$apps_sql = "SELECT a.*, j.title, j.company_name 
             FROM applications a 
             JOIN jobs j ON a.job_id = j.id 
             ORDER BY a.applied_at DESC";
$apps_result = $conn->query($apps_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - TP Rekrutmen</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }
        .dashboard-card {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--border);
        }
        .dashboard-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .job-item {
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            transition: var(--transition);
        }
        .job-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        .job-company {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .job-title-item {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 12px;
        }
        .job-date-item {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .applications-table {
            width: 100%;
            border-collapse: collapse;
        }
        .applications-table th {
            background: var(--bg-main);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
        }
        .applications-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }
        .empty-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="siswa_dashboard.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                TP<span>Rekrutmen</span>
            </a>
            <div class="nav-links">
                <span style="color: var(--text-main); margin-right: 1.5rem;">
                    <i class="fa-solid fa-user-graduate"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container" style="margin: 2rem 0; margin-bottom: 4rem;">
        
        <!-- Welcome Section -->
        <div style="background: linear-gradient(135deg, var(--primary), var(--primary-hover)); border-radius: var(--radius-lg); padding: 2rem; color: white; margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
            <p style="font-size: 1.1rem;">Cari dan lamar lowongan kerja dari perusahaan terkemuka</p>
        </div>

        <div class="dashboard-container">
            
            <!-- Available Jobs Section -->
            <div class="dashboard-card">
                <h2 class="dashboard-title">
                    <i class="fa-solid fa-briefcase"></i> Lowongan Tersedia
                </h2>
                
                <?php if ($jobs_result->num_rows > 0): ?>
                    <div class="jobs-grid">
                        <?php while ($job = $jobs_result->fetch_assoc()): ?>
                            <div class="job-item">
                                <div class="job-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                <div class="job-title-item"><?php echo htmlspecialchars($job['title']); ?></div>
                                <div class="job-date-item">
                                    <i class="fa-regular fa-calendar"></i>
                                    <?php echo date('d M Y', strtotime($job['created_at'])); ?>
                                </div>
                                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline" style="width: 100%; justify-content: center;">
                                    <i class="fa-solid fa-eye"></i> Lihat Detail
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fa-regular fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>Belum ada lowongan tersedia saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Applications Section -->
            <div class="dashboard-card">
                <h2 class="dashboard-title">
                    <i class="fa-solid fa-file-lines"></i> Lamaran Saya
                </h2>
                
                <?php if ($apps_result->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="applications-table">
                            <thead>
                                <tr>
                                    <th>Posisi</th>
                                    <th>Perusahaan</th>
                                    <th>Nama Lengkap</th>
                                    <th>Tanggal Lamar</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($app = $apps_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                                        <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['nama_lengkap']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($app['applied_at'])); ?></td>
                                        <td><span class="status-badge status-pending">Diproses</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <i class="fa-regular fa-file" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>Anda belum melamar posisi apapun.</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Lihat lowongan tersedia di atas dan mulai lamar sekarang!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>

    <footer style="text-align: center; padding: 3rem 0; border-top: 1px solid var(--border); color: var(--text-muted);">
        <p>&copy; <?php echo date('Y'); ?> TP Rekrutmen - Bursa Kerja Khusus. All rights reserved.</p>
    </footer>

</body>
</html>
