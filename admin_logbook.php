<?php
session_start();
require_once 'config.php';

// Only teacher can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

// Require application_id
if (!isset($_GET['application_id']) || !is_numeric($_GET['application_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$application_id = (int)$_GET['application_id'];

// Fetch application + siswa info
$app_sql = "SELECT a.*, u.username, j.title, j.company_name
            FROM applications a
            JOIN users u ON a.siswa_id = u.id
            JOIN jobs j ON a.job_id = j.id
            WHERE a.id = $application_id AND a.status = 'accepted'";
$app_result = $conn->query($app_sql);
if (!$app_result || $app_result->num_rows === 0) {
    header("Location: admin_dashboard.php");
    exit;
}
$app = $app_result->fetch_assoc();
$siswa_id = (int)$app['siswa_id'];

// Date range filter
$date_from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])
             ? $_GET['from'] : date('Y-m-01'); // default: first of this month
$date_to   = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])
             ? $_GET['to'] : date('Y-m-d');

// Fetch logbook sessions in range
$log_sql = "SELECT * FROM logbook_sessions
            WHERE siswa_id = $siswa_id
              AND application_id = $application_id
              AND DATE(check_in) BETWEEN '$date_from' AND '$date_to'
            ORDER BY check_in DESC";
$log_result = $conn->query($log_sql);

// Stats
$stats_sql = "SELECT
    COUNT(*) as total_sessions,
    COUNT(CASE WHEN is_overtime = 1 THEN 1 END) as overtime_sessions,
    SUM(TIMESTAMPDIFF(MINUTE, check_in, check_out)) as total_minutes
    FROM logbook_sessions
    WHERE siswa_id = $siswa_id AND application_id = $application_id AND check_out IS NOT NULL";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
$total_sessions  = (int)($stats['total_sessions'] ?? 0);
$overtime_sessions = (int)($stats['overtime_sessions'] ?? 0);
$total_minutes   = (int)($stats['total_minutes'] ?? 0);
$total_hours     = floor($total_minutes / 60);
$rem_minutes     = $total_minutes % 60;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logbook – <?php echo htmlspecialchars($app['nama_lengkap']); ?> | Admin</title>
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
            position: sticky;
            top: 90px;
        }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 8px; }
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

        /* Stats bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: var(--dark); line-height: 1; }
        .stat-label { font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.05em; }

        /* Filter bar */
        .filter-bar {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .filter-bar label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .filter-bar input[type=date] {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-main);
            background: var(--bg-main);
        }
        .filter-bar input[type=date]:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Table */
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th {
            background: var(--bg-main);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--border);
        }
        .log-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.9rem;
        }
        .log-table tr:last-child td { border-bottom: none; }
        .log-table tr:hover td { background: var(--bg-main); }
        .overtime-row td { background: #FFFBEB !important; }

        .duration-chip {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .overtime-chip {
            display: inline-block;
            background: #FEF3C7;
            color: #92400E;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 4px;
        }

        /* Photo thumb */
        .photo-thumb {
            width: 60px; height: 60px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid var(--border);
        }
        .photo-thumb:hover { transform: scale(1.08); }

        /* Student info header */
        .student-header {
            background: linear-gradient(135deg, var(--primary), #7C3AED);
            border-radius: var(--radius-md);
            padding: 1.5rem 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .student-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .student-name { font-size: 1.3rem; font-weight: 700; }
        .student-meta { font-size: 0.85rem; opacity: 0.85; margin-top: 4px; }

        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }
        .lightbox.open { display: flex; }
        .lightbox img {
            max-width: 90vw; max-height: 90vh;
            border-radius: var(--radius-md);
        }
        .lightbox-close {
            position: fixed; top: 1.5rem; right: 1.5rem;
            background: rgba(255,255,255,0.2);
            color: white; border: none;
            border-radius: 50%; width: 40px; height: 40px;
            cursor: pointer; font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center;
        }
        .lightbox-close:hover { background: rgba(255,255,255,0.35); }

        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .stats-bar { grid-template-columns: 1fr 1fr; }
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
                <span style="font-weight:600; color:var(--dark); margin-right:16px;">
                    Halo, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline" style="padding:0.5rem 1rem;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-grid" style="margin-bottom:5rem;">
        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="admin_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li><a href="add_job.php"><i class="fa-solid fa-plus"></i> Tambah Lowongan</a></li>
                <li><a href="manage_companies.php"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <li><a href="manage_siswa.php"><i class="fa-solid fa-users"></i> Kelola Siswa</a></li>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main>
            <!-- Back + title -->
            <div style="margin-bottom:1.5rem; display:flex; align-items:center; gap:16px;">
                <a href="view_applicants.php?job_id=<?php echo (int)$app['job_id']; ?>"
                   class="btn btn-outline" style="padding:0.5rem 1rem;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
                <div>
                    <h2 style="color:var(--dark); font-size:1.4rem;">
                        <i class="fa-solid fa-book-open" style="color:var(--primary);"></i>
                        Logbook Siswa
                    </h2>
                    <p style="color:var(--text-muted); font-size:0.9rem;">
                        <?php echo htmlspecialchars($app['title']); ?> – <?php echo htmlspecialchars($app['company_name']); ?>
                    </p>
                </div>
            </div>

            <!-- Student header -->
            <div class="student-header">
                <div class="student-avatar"><i class="fa-solid fa-user-graduate"></i></div>
                <div>
                    <div class="student-name"><?php echo htmlspecialchars($app['nama_lengkap']); ?></div>
                    <div class="student-meta">
                        @<?php echo htmlspecialchars($app['username']); ?>
                        &nbsp;·&nbsp; <?php echo htmlspecialchars($app['jurusan']); ?>
                        &nbsp;·&nbsp; Kelas <?php echo htmlspecialchars($app['kelas']); ?>
                    </div>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="stats-bar">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#EEF2FF;">
                        <i class="fa-solid fa-calendar-check" style="color:var(--primary);"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $total_sessions; ?></div>
                        <div class="stat-label">Total Sesi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#FEF3C7;">
                        <i class="fa-solid fa-moon" style="color:#D97706;"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $overtime_sessions; ?></div>
                        <div class="stat-label">Sesi Overtime</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#D1FAE5;">
                        <i class="fa-solid fa-clock" style="color:#059669;"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $total_hours; ?>j</div>
                        <div class="stat-label"><?php echo $rem_minutes; ?>m Total Jam</div>
                    </div>
                </div>
            </div>

            <!-- Date range filter -->
            <form method="GET" class="filter-bar">
                <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                <label for="from"><i class="fa-solid fa-filter"></i> Filter:</label>
                <label for="from">Dari</label>
                <input type="date" id="from" name="from" value="<?php echo $date_from; ?>">
                <label for="to">Sampai</label>
                <input type="date" id="to" name="to" value="<?php echo $date_to; ?>">
                <button type="submit" class="btn btn-primary" style="padding:0.5rem 1.25rem; font-size:0.9rem;">
                    <i class="fa-solid fa-magnifying-glass"></i> Filter
                </button>
                <a href="admin_logbook.php?application_id=<?php echo $application_id; ?>"
                   style="font-size:0.85rem; color:var(--text-muted);">Reset</a>
            </form>

            <!-- Logbook table -->
            <div class="table-container">
                <div style="overflow-x:auto;">
                    <?php if ($log_result && $log_result->num_rows > 0): ?>
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>Tanggal & Hari</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Durasi</th>
                                    <th>Deskripsi Pekerjaan</th>
                                    <th>Foto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $log_result->fetch_assoc()): ?>
                                    <?php
                                    $cin     = strtotime($row['check_in']);
                                    $cout    = $row['check_out'] ? strtotime($row['check_out']) : null;
                                    $is_ot   = (bool)$row['is_overtime'];
                                    $is_open = $cout === null;
                                    $row_cls = $is_ot ? 'overtime-row' : '';
                                    if (!$is_open) {
                                        $dur   = $cout - $cin;
                                        $dur_h = floor($dur / 3600);
                                        $dur_m = floor(($dur % 3600) / 60);
                                        $dur_str = $dur_h . 'j ' . $dur_m . 'm';
                                    }
                                    ?>
                                    <tr class="<?php echo $row_cls; ?>">
                                        <td>
                                            <div style="font-weight:600; color:var(--dark);">
                                                <?php echo date('d M Y', $cin); ?>
                                            </div>
                                            <div style="font-size:0.78rem; color:var(--text-muted);">
                                                <?php echo date('l', $cin); ?>
                                            </div>
                                            <?php if ($is_ot): ?>
                                                <div class="overtime-chip"><i class="fa-solid fa-moon"></i> Overtime</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-weight:600; color:#16A34A;">
                                            <?php echo date('H:i', $cin); ?>
                                        </td>
                                        <td>
                                            <?php if ($is_open): ?>
                                                <span style="color:#D97706; font-weight:600; font-size:0.85rem;">
                                                    <i class="fa-solid fa-circle-dot fa-beat"></i> Aktif
                                                </span>
                                            <?php else: ?>
                                                <span style="font-weight:600; color:#DC2626;">
                                                    <?php echo date('H:i', $cout); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_open): ?>
                                                <span class="duration-chip"><?php echo $dur_str; ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:0.82rem;">–</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width:320px;">
                                            <?php if ($row['deskripsi']): ?>
                                                <div style="font-size:0.88rem; line-height:1.6; color:var(--text-main); white-space:pre-wrap; word-break:break-word;">
                                                    <?php echo nl2br(htmlspecialchars($row['deskripsi'])); ?>
                                                </div>
                                            <?php elseif ($is_open): ?>
                                                <span style="color:var(--text-muted); font-size:0.82rem; font-style:italic;">Belum check-out</span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">–</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['foto']): ?>
                                                <img src="uploads/logbook/<?php echo htmlspecialchars($row['foto']); ?>"
                                                     class="photo-thumb"
                                                     alt="Foto kegiatan"
                                                     onclick="openLightbox(this.src)">
                                            <?php else: ?>
                                                <span style="color:var(--text-muted); font-size:0.82rem;">–</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fa-solid fa-book-open"></i></div>
                            <h3>Belum ada catatan logbook</h3>
                            <p>Siswa ini belum memiliki entri logbook dalam rentang tanggal yang dipilih.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightboxImg" src="" alt="Foto kegiatan">
    </div>

    <script>
    function openLightbox(src) {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.add('open');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
    }
    </script>

</body>
</html>
