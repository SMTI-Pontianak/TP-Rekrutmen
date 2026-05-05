<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['title']);
    
    // Auto assign company name if recruiter
    if ($_SESSION['role'] === 'recruiter') {
        $company_name = $conn->real_escape_string($_SESSION['company_name']);
    } else {
        $company_name = $conn->real_escape_string($_POST['company_name']);
    }

    $description = $conn->real_escape_string($_POST['description']);
    $requirements = $conn->real_escape_string($_POST['requirements']);

    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO jobs (user_id, title, company_name, description, requirements) 
            VALUES ('$user_id', '$title', '$company_name', '$description', '$requirements')";
    
    if ($conn->query($sql) === TRUE) {
        $success = "Lowongan berhasil ditambahkan!";
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Lowongan - TP Rekrutmen</title>
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
                <li><a href="admin_dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                <li><a href="add_job.php" class="active"><i class="fa-solid fa-plus"></i> Tambah Lowongan</a></li>
                <?php if($_SESSION['role'] === 'teacher'): ?>
                <li><a href="manage_companies.php"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <?php endif; ?>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main>
            <div class="form-container" style="max-width: 100%; margin: 0;">
                <h2 style="margin-bottom: 24px; color: var(--dark);">Tambah Lowongan Baru</h2>
                
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($success)): ?>
                    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success; ?></div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="title">Judul Posisi</label>
                            <input type="text" id="title" name="title" class="form-control" required placeholder="Contoh: Operator Mesin CNC">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="company_name">Nama Perusahaan</label>
                            <?php if($_SESSION['role'] === 'recruiter'): ?>
                                <input type="text" id="company_name" name="company_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['company_name']); ?>" readonly style="background: var(--bg-main); color: var(--text-muted); cursor: not-allowed;">
                            <?php else: ?>
                                <input type="text" id="company_name" name="company_name" class="form-control" required placeholder="Contoh: PT. Astra Honda Motor">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Deskripsi Pekerjaan</label>
                        <textarea id="description" name="description" class="form-control" required placeholder="Jelaskan tentang posisi ini, tanggung jawab, dsb..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="requirements">Persyaratan (Kualifikasi)</label>
                        <textarea id="requirements" name="requirements" class="form-control" required placeholder="Sebutkan syarat-syarat untuk melamar posisi ini..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 1rem 2rem;">
                        <i class="fa-solid fa-save"></i> Simpan Lowongan
                    </button>
                </form>
            </div>
        </main>
    </div>

</body>
</html>
