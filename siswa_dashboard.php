<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: login.php");
    exit;
}

// Fetch siswa's konsentrasi keahlian
$siswa_sql = "SELECT konsentrasi_keahlian FROM users WHERE id = {$_SESSION['user_id']}";
$siswa_result = $conn->query($siswa_sql);
$siswa_data = $siswa_result->fetch_assoc();
$siswa_konsentrasi = $siswa_data['konsentrasi_keahlian'];

// Fetch jobs matching siswa's konsentrasi keahlian
$jobs_sql = "SELECT * FROM jobs WHERE konsentrasi_keahlian = '$siswa_konsentrasi' ORDER BY created_at DESC";
$jobs_result = $conn->query($jobs_sql);

// Fetch applications made by this siswa
$apps_sql = "SELECT a.*, j.title, j.company_name 
             FROM applications a 
             JOIN jobs j ON a.job_id = j.id 
             WHERE a.siswa_id = {$_SESSION['user_id']}
             ORDER BY a.applied_at DESC";
$apps_result = $conn->query($apps_sql);

// Fetch accepted application for logbook
$accepted_app_sql = "SELECT a.id, j.title, j.company_name
                     FROM applications a
                     JOIN jobs j ON a.job_id = j.id
                     WHERE a.siswa_id = {$_SESSION['user_id']} AND a.status = 'accepted'
                     ORDER BY a.applied_at DESC LIMIT 1";
$accepted_app_result = $conn->query($accepted_app_sql);
$accepted_app = ($accepted_app_result && $accepted_app_result->num_rows > 0)
                ? $accepted_app_result->fetch_assoc()
                : null;

// If accepted, get today's logbook session status
$logbook_active = null;
$today_sessions_count = 0;
if ($accepted_app) {
    $today = date('Y-m-d');
    $sid   = (int)$_SESSION['user_id'];
    $aid   = (int)$accepted_app['id'];
    $lb_active_sql = "SELECT id, check_in FROM logbook_sessions
                      WHERE siswa_id = $sid AND application_id = $aid
                        AND check_out IS NULL LIMIT 1";
    $lb_active_res = $conn->query($lb_active_sql);
    if ($lb_active_res && $lb_active_res->num_rows > 0) {
        $logbook_active = $lb_active_res->fetch_assoc();
    }
    $lb_count_sql = "SELECT COUNT(*) as c FROM logbook_sessions
                     WHERE siswa_id = $sid AND application_id = $aid
                       AND DATE(check_in) = '$today' AND check_out IS NOT NULL";
    $lb_count_res = $conn->query($lb_count_sql);
    if ($lb_count_res) {
        $today_sessions_count = (int)($lb_count_res->fetch_assoc()['c']);
    }
}

// Count active applications
$count_active_sql = "SELECT COUNT(*) as count FROM applications WHERE siswa_id = {$_SESSION['user_id']} AND status IN ('pending', 'reviewed', 'accepted')";
$count_active_result = $conn->query($count_active_sql);
$count_active = $count_active_result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - Bursa Kerja Khusus SMTI Pontianak</title>
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }
        .status-reviewed {
            background: #DBEAFE;
            color: #1E40AF;
        }
        .status-accepted {
            background: #D1FAE5;
            color: #065F46;
        }
        .status-rejected {
            background: #FEE2E2;
            color: #7F1D1D;
        }
        .empty-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        /* Logbook card */
        .logbook-banner {
            background: linear-gradient(135deg, #10B981, #059669);
            border-radius: var(--radius-lg);
            padding: 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .logbook-banner::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .logbook-status-dot {
            display: inline-block;
            width: 10px; height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .logbook-status-dot.active { background: #86EFAC; box-shadow: 0 0 0 3px rgba(134,239,172,0.4); animation: pulse 1.5s infinite; }
        .logbook-status-dot.idle   { background: rgba(255,255,255,0.5); }
        @keyframes pulse { 0%,100%{box-shadow:0 0 0 3px rgba(134,239,172,0.4);} 50%{box-shadow:0 0 0 6px rgba(134,239,172,0.15);} }
        .btn-logbook {
            background: white;
            color: #059669;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-full);
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            transition: var(--transition);
            flex-shrink: 0;
        }
        .btn-logbook:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            color: #047857;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="siswa_dashboard.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                Bursa Kerja Khusus SMTI Pontianak
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
            <p style="font-size: 1.1rem;">Konsentrasi: <strong><?php echo htmlspecialchars($siswa_konsentrasi); ?></strong></p>
            <p style="font-size: 0.95rem; margin-top: 0.5rem;">Cari dan lamar lowongan kerja dari perusahaan terkemuka yang sesuai dengan konsentrasi Anda</p>
        </div>

        <?php if ($accepted_app): ?>
        <!-- ── Logbook Banner (only for accepted students) ── -->
        <div class="logbook-banner">
            <div>
                <div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.1em; opacity:0.8; margin-bottom:6px;">
                    <i class="fa-solid fa-building"></i>
                    <?php echo htmlspecialchars($accepted_app['company_name']); ?> &middot;
                    <?php echo htmlspecialchars($accepted_app['title']); ?>
                </div>
                <h2 style="font-size:1.4rem; margin-bottom:6px;"><i class="fa-solid fa-book-open"></i> Logbook Harian</h2>
                <div style="font-size:0.9rem; opacity:0.9;">
                    <?php if ($logbook_active): ?>
                        <span class="logbook-status-dot active"></span>
                        Sesi aktif sejak <?php echo date('H:i', strtotime($logbook_active['check_in'])); ?> – jangan lupa Check Out!
                    <?php elseif ($today_sessions_count > 0): ?>
                        <span class="logbook-status-dot idle"></span>
                        <?php echo $today_sessions_count; ?> sesi selesai hari ini
                    <?php else: ?>
                        <span class="logbook-status-dot idle"></span>
                        Belum ada sesi hari ini. Yuk mulai check-in!
                    <?php endif; ?>
                </div>
            </div>
            <a href="logbook.php" class="btn-logbook">
                <i class="fa-solid fa-right-to-bracket"></i>
                <?php if ($logbook_active): ?>Lanjutkan Logbook<?php else: ?>Buka Logbook<?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

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
                    <span style="margin-left: auto; background: var(--primary); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500;"><?php echo $count_active; ?>/5 Aktif</span>
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
                                    <?php 
                                    $status_class = 'status-' . $app['status'];
                                    $status_labels = [
                                        'pending' => '🕐 Pending',
                                        'reviewed' => '👁️ Reviewed',
                                        'accepted' => '✅ Accepted',
                                        'rejected' => '❌ Rejected'
                                    ];
                                    $status_label = $status_labels[$app['status']] ?? 'Unknown';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($app['title']); ?></td>
                                        <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['nama_lengkap']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($app['applied_at'])); ?></td>
                                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
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
