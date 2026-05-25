<?php
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$job_id = (int)$_GET['id'];
$sql = "SELECT * FROM jobs WHERE id = $job_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$job = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - TP Rekrutmen</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                Bursa Kerja Khusus SMTI Pontianak
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">Kembali ke Lowongan</a>
            </div>
        </div>
    </nav>

    <main class="container" style="margin-top: 3rem; margin-bottom: 5rem;">
        <div style="background: var(--bg-surface); border-radius: var(--radius-lg); padding: 40px; box-shadow: var(--shadow-md); border: 1px solid var(--border);">
            <div style="margin-bottom: 32px; border-bottom: 1px solid var(--border); padding-bottom: 24px;">
                <div class="job-company" style="font-size: 1rem;"><?php echo htmlspecialchars($job['company_name']); ?></div>
                <h1 style="font-size: 2.5rem; margin-bottom: 16px; color: var(--dark);"><?php echo htmlspecialchars($job['title']); ?></h1>
                <div class="job-date" style="font-size: 1rem;">
                    <i class="fa-regular fa-calendar"></i> Diposting pada: <?php echo date('d F Y', strtotime($job['created_at'])); ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">
                <!-- Main Content -->
                <div>
                    <h3 style="margin-bottom: 16px; color: var(--dark); font-size: 1.25rem;">Deskripsi Pekerjaan</h3>
                    <div style="color: var(--text-main); margin-bottom: 32px; line-height: 1.8;">
                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                    </div>

                    <h3 style="margin-bottom: 16px; color: var(--dark); font-size: 1.25rem;">Persyaratan</h3>
                    <div style="color: var(--text-main); margin-bottom: 32px; line-height: 1.8;">
                        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                    </div>
                </div>

                <!-- Sidebar / Action -->
                <div>
                    <div style="background: var(--primary-light); padding: 24px; border-radius: var(--radius-md); border: 1px solid rgba(79, 70, 229, 0.2); text-align: center;">
                        <h3 style="color: var(--primary); margin-bottom: 12px; font-size: 1.125rem;">Tertarik dengan posisi ini?</h3>
                        <p style="color: var(--text-main); font-size: 0.875rem; margin-bottom: 20px;">Siapkan CV dan surat lamaran Anda dalam 1 file PDF, dan pastikan data diri Anda lengkap.</p>
                        <a href="apply.php?id=<?php echo $job['id']; ?>" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
                            <i class="fa-solid fa-paper-plane"></i> Lamar Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer style="text-align: center; padding: 3rem 0; border-top: 1px solid var(--border); color: var(--text-muted);">
        <p>&copy; <?php echo date('Y'); ?> TP Rekrutmen - Bursa Kerja Khusus. All rights reserved.</p>
    </footer>

</body>
</html>
