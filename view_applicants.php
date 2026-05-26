<?php
session_start();
require_once 'config.php';
require_once 'security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['job_id']) || empty($_GET['job_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$job_id = (int)$_GET['job_id'];

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $conn->real_escape_string($_POST['new_status']);
    
    // Validate status value
    $valid_statuses = ['pending', 'reviewed', 'accepted', 'rejected'];
    if (in_array($new_status, $valid_statuses)) {
        $update_sql = "UPDATE applications SET status = '$new_status', updated_at = NOW() WHERE id = $application_id AND job_id = $job_id";
        if ($conn->query($update_sql) === TRUE) {
            logSecurityEvent('APPLICATION_STATUS_UPDATED', ['application_id' => $application_id, 'new_status' => $new_status, 'user_id' => $_SESSION['user_id']]);
        }
    }
    
    header("Location: view_applicants.php?job_id=" . $job_id);
    exit;
}

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    
    // Get file name to delete the physical file
    $file_sql = "SELECT cv_file FROM applications WHERE id = $delete_id AND job_id = $job_id";
    $file_res = $conn->query($file_sql);
    if ($file_res->num_rows > 0) {
        $file_row = $file_res->fetch_assoc();
        $file_path = "uploads/" . $file_row['cv_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $conn->query("DELETE FROM applications WHERE id = $delete_id AND job_id = $job_id");
    }
    
    // Redirect to refresh
    header("Location: view_applicants.php?job_id=" . $job_id);
    exit;
}

// Get Job Info
$job_sql = "SELECT title, company_name, user_id FROM jobs WHERE id = $job_id";
$job_result = $conn->query($job_sql);
if($job_result->num_rows == 0) {
    header("Location: admin_dashboard.php");
    exit;
}
$job = $job_result->fetch_assoc();

// Check if recruiter owns this job
if ($_SESSION['role'] === 'recruiter' && $job['user_id'] != $_SESSION['user_id']) {
    header("Location: admin_dashboard.php");
    exit;
}

// Get Applicants
$sql = "SELECT * FROM applications WHERE job_id = $job_id ORDER BY applied_at DESC";
$result = $conn->query($sql);

// Function to get status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'label' => 'PENDING', 'icon' => 'fa-clock'],
        'reviewed' => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'label' => 'REVIEWED', 'icon' => 'fa-eye'],
        'accepted' => ['color' => '#10B981', 'bg' => '#D1FAE5', 'label' => 'ACCEPTED', 'icon' => 'fa-check'],
        'rejected' => ['color' => '#EF4444', 'bg' => '#FEE2E2', 'label' => 'REJECTED', 'icon' => 'fa-times']
    ];
    
    $badge = $badges[$status] ?? $badges['pending'];
    return $badge;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelamar - <?php echo htmlspecialchars($job['title']); ?></title>
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
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.4);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: var(--bg-surface);
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            width: 90%;
        }
        .modal-header {
            margin-bottom: 24px;
        }
        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            margin: 0 0 8px 0;
        }
        .modal-header p {
            color: var(--text-muted);
            margin: 0;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        .btn {
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .btn-secondary {
            background: var(--border);
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: #e5e7eb;
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
                <?php if($_SESSION['role'] === 'teacher'): ?>
                <li><a href="manage_companies.php"><i class="fa-solid fa-building-user"></i> Kelola Perusahaan</a></li>
                <li><a href="manage_siswa.php"><i class="fa-solid fa-users"></i> Kelola Siswa</a></li>
                <?php endif; ?>
                <li><a href="index.php" target="_blank"><i class="fa-solid fa-globe"></i> Lihat Portal</a></li>
            </ul>
        </aside>

        <main>
            <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                <a href="admin_dashboard.php" class="btn btn-outline" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
                <div>
                    <h2 style="color: var(--dark); font-size: 1.5rem;">Daftar Pelamar</h2>
                    <p style="color: var(--text-muted);"><?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?></p>
                </div>
            </div>

            <div class="table-container">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Lengkap</th>
                                <th>Jurusan</th>
                                <th>Kelas</th>
                                <th>No. WhatsApp</th>
                                <th>Status</th>
                                <th>Waktu Melamar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <?php $badge = getStatusBadge($row['status']); ?>
                                    <tr>
                                        <td style="font-weight: 500; color: var(--dark);"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($row['jurusan']); ?></td>
                                        <td>
                                            <span class="badge badge-primary" style="background: #FEF3C7; color: #D97706;">
                                                <?php echo htmlspecialchars($row['kelas']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $row['nomor_wa']); ?>" target="_blank" style="color: #10B981; font-weight: 500;">
                                                <i class="fa-brands fa-whatsapp"></i> <?php echo htmlspecialchars($row['nomor_wa']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge" style="background: <?php echo $badge['bg']; ?>; color: <?php echo $badge['color']; ?>;">
                                                <i class="fa-solid <?php echo $badge['icon']; ?>"></i>
                                                <?php echo $badge['label']; ?>
                                            </span>
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo date('d M Y, H:i', strtotime($row['applied_at'])); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <a href="uploads/<?php echo htmlspecialchars($row['cv_file']); ?>" target="_blank" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: var(--primary);">
                                                    <i class="fa-solid fa-file-pdf"></i>
                                                </a>
                                                <button type="button" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; background: #3B82F6;" onclick="openStatusModal(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>', '<?php echo htmlspecialchars($row['nama_lengkap']); ?>')">
                                                    <i class="fa-solid fa-edit"></i>
                                                </button>
                                                <form action="view_applicants.php?job_id=<?php echo $job_id; ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelamar ini? CV dan datanya akan dihapus permanen.');" style="margin: 0;">
                                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border: none; cursor: pointer; background: #EF4444; color: white;" title="Hapus Pelamar">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="fa-solid fa-users" style="font-size: 2rem; margin-bottom: 12px; display: block; color: var(--border);"></i>
                                        Belum ada yang melamar untuk posisi ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ubah Status Lamaran</h2>
                <p id="applicantName"></p>
            </div>
            <form method="POST" action="view_applicants.php?job_id=<?php echo $job_id; ?>">
                <input type="hidden" name="application_id" id="applicationId">
                <div class="form-group">
                    <label class="form-label">Status Baru</label>
                    <select name="new_status" id="newStatus" class="form-control" required>
                        <option value="pending">🕐 Pending - Menunggu Review</option>
                        <option value="reviewed">👁️ Reviewed - Sudah Dilihat</option>
                        <option value="accepted">✅ Accepted - Diterima</option>
                        <option value="rejected">❌ Rejected - Ditolak</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" name="update_status">Perbarui Status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStatusModal(applicationId, currentStatus, applicantName) {
            document.getElementById('applicationId').value = applicationId;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('applicantName').textContent = applicantName;
            document.getElementById('statusModal').classList.add('active');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        }
    </script>

</body>
</html>
