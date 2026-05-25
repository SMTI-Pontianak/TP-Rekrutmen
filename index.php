<?php
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch jobs
$sql = "SELECT * FROM jobs ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bursa Kerja Khusus (BKK) - TP Rekrutmen</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Include FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                TP<span>Rekrutmen</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">Lowongan</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'siswa'): ?>
                        <a href="siswa_dashboard.php" class="nav-link"><i class="fa-solid fa-user-graduate"></i> Dashboard</a>
                        <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.95rem;">Logout</a>
                    <?php else: ?>
                        <a href="admin_dashboard.php" class="nav-link"><i class="fa-solid fa-chart-line"></i> Admin</a>
                        <a href="logout.php" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.95rem;">Logout</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline"><i class="fa-solid fa-user-lock"></i> Login</a>
                    <a href="siswa_register.php" class="btn btn-primary"><i class="fa-solid fa-user-graduate"></i> Daftar Siswa</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Temukan Karir Impianmu Setelah Lulus</h1>
            <p>Platform bursa kerja khusus untuk alumni dan siswa tingkat akhir. Jelajahi berbagai peluang kerja dari perusahaan mitra kami dan mulai karirmu hari ini.</p>
            <a href="#lowongan" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Cari Lowongan</a>
        </div>
    </section>

    <!-- Job Listings -->
    <main class="container" id="lowongan">
        <div class="section-header">
            <h2 class="section-title">Lowongan Terbaru</h2>
            <p class="section-subtitle">Daftar lowongan pekerjaan yang sedang buka dari perusahaan-perusahaan terkemuka.</p>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="job-grid">
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="job-card">
                        <div class="job-company"><?php echo htmlspecialchars($row['company_name']); ?></div>
                        <h3 class="job-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <div class="job-desc">
                            <?php echo nl2br(htmlspecialchars(substr($row['description'], 0, 150))) . '...'; ?>
                        </div>
                        <div class="job-footer">
                            <div class="job-date">
                                <i class="fa-regular fa-calendar"></i>
                                <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                            </div>
                            <a href="job_details.php?id=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Lihat Detail</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fa-regular fa-folder-open"></i>
                </div>
                <h3>Belum ada lowongan tersedia</h3>
                <p>Saat ini belum ada lowongan pekerjaan yang dibuka. Silakan cek kembali nanti.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer style="text-align: center; padding: 3rem 0; border-top: 1px solid var(--border); margin-top: 4rem; color: var(--text-muted);">
        <p>&copy; <?php echo date('Y'); ?> TP Rekrutmen - Bursa Kerja Khusus. All rights reserved.</p>
    </footer>

</body>
</html>
