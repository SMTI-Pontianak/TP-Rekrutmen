<?php
require_once 'config.php';
session_start();

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

// Check if siswa's konsentrasi matches job's konsentrasi
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'siswa') {
    $siswa_sql = "SELECT konsentrasi_keahlian FROM users WHERE id = {$_SESSION['user_id']}";
    $siswa_result = $conn->query($siswa_sql);
    $siswa_data = $siswa_result->fetch_assoc();
    
    if ($siswa_data['konsentrasi_keahlian'] !== $job['konsentrasi_keahlian']) {
        header("Location: index.php");
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = $conn->real_escape_string($_POST['nama_lengkap']);
    $jurusan = $conn->real_escape_string($_POST['jurusan']);
    $kelas = $conn->real_escape_string($_POST['kelas']);
    $nomor_wa = $conn->real_escape_string($_POST['nomor_wa']);

    // Handle File Upload
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $fileName = basename($_FILES["cv_file"]["name"]);
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Generate a secure, unique filename to avoid overwrites, but keep original name info
    $newFileName = time() . "_" . preg_replace("/[^a-zA-Z0-9]/", "_", $nama_lengkap) . "." . $fileType;
    $target_file = $target_dir . $newFileName;

    // Check if file is a PDF
    if($fileType != "pdf") {
        $error = "Maaf, hanya file PDF yang diperbolehkan.";
    } else {
        if (move_uploaded_file($_FILES["cv_file"]["tmp_name"], $target_file)) {
            // Insert into database
            $insert_sql = "INSERT INTO applications (job_id, nama_lengkap, jurusan, kelas, nomor_wa, cv_file) 
                           VALUES ('$job_id', '$nama_lengkap', '$jurusan', '$kelas', '$nomor_wa', '$newFileName')";
            
            if ($conn->query($insert_sql) === TRUE) {
                $success = "Lamaran Anda berhasil dikirim! Silakan tunggu informasi selanjutnya.";
            } else {
                $error = "Error: " . $conn->error;
            }
        } else {
            $error = "Terjadi kesalahan saat mengunggah file Anda.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Lamaran - <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="nav-brand">
                <i class="fa-solid fa-briefcase"></i>
                Bursa Kerja Khusus SMTI Pontianak
            </a>
            <div class="nav-links">
                <a href="job_details.php?id=<?php echo $job['id']; ?>" class="nav-link">Batal / Kembali</a>
            </div>
        </div>
    </nav>

    <main class="container" style="margin-top: 3rem; margin-bottom: 5rem;">
        <div class="section-header" style="margin-bottom: 2rem;">
            <h2 style="font-size: 2rem; color: var(--dark);">Kirim Lamaran Pekerjaan</h2>
            <p class="section-subtitle">Posisi: <strong><?php echo htmlspecialchars($job['title']); ?></strong> di <?php echo htmlspecialchars($job['company_name']); ?></p>
        </div>

        <div class="form-container">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $success; ?>
                </div>
                <div style="text-align: center; margin-top: 24px;">
                    <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
                </div>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $job_id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label" for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required placeholder="Masukkan nama lengkap Anda">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="jurusan">Jurusan</label>
                        <select id="jurusan" name="jurusan" class="form-control" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <option value="Teknik Pemesinan">Teknik Pemesinan</option>
                            <option value="Kimia Industri">Kimia Industri</option>
                            <option value="Analisis Pengujian Laboratorium">Analisis Pengujian Laboratorium</option>
                            <option value="Teknik Otomasi Industri">Teknik Otomasi Industri</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kelas">Kelas</label>
                        <select id="kelas" name="kelas" class="form-control" required>
                            <option value="">-- Pilih Kelas --</option>
                            <option value="XII TP 1">XII TP 1</option>
                            <option value="XII TP 2">XII TP 2</option>
                            <option value="XII TP 3">XII TP 3</option>
                            <option value="XII TP 4">XII TP 4</option>
                            <option value="Lainnya">Lainnya / Alumni</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="nomor_wa">Nomor WhatsApp Aktif</label>
                        <input type="tel" id="nomor_wa" name="nomor_wa" class="form-control" required placeholder="Contoh: 081234567890">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Berkas Lamaran (CV & Surat Lamaran)</label>
                        <div class="alert" style="background: var(--primary-light); color: var(--primary); border: 1px solid rgba(79, 70, 229, 0.2); font-size: 0.875rem; padding: 12px; margin-bottom: 12px;">
                            <i class="fa-solid fa-circle-info"></i> 
                            <strong>PENTING:</strong> Hasil scan CV dan Surat Lamaran harus dijadikan 1 file PDF. Beri nama file menggunakan NAMA LENGKAP Anda.
                        </div>
                        <div class="file-upload" id="drop-area">
                            <input type="file" id="cv_file" name="cv_file" accept=".pdf" required onchange="updateFileName(this)">
                            <div class="file-icon"><i class="fa-solid fa-file-pdf"></i></div>
                            <div class="file-text" id="file-name-display">
                                Klik di sini atau seret file PDF Anda
                                <span class="hint">Maksimal ukuran file: 5MB</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem; font-size: 1.125rem; margin-top: 1rem;">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Lamaran
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updateFileName(input) {
            const display = document.getElementById('file-name-display');
            if (input.files && input.files[0]) {
                display.innerHTML = `<strong>File terpilih:</strong> ${input.files[0].name}`;
            } else {
                display.innerHTML = `Klik di sini atau seret file PDF Anda <span class="hint">Maksimal ukuran file: 5MB</span>`;
            }
        }
    </script>
</body>
</html>
