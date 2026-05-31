<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Must be logged in as siswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$siswa_id = (int)$_SESSION['user_id'];
$action   = $_POST['action'] ?? '';

// -----------------------------------------------------------------
// Helper: find the siswa's accepted application
// -----------------------------------------------------------------
function getAcceptedApplication($conn, $siswa_id) {
    $sql = "SELECT a.id, a.job_id, j.company_name, j.title
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            WHERE a.siswa_id = $siswa_id AND a.status = 'accepted'
            ORDER BY a.applied_at DESC
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

$accepted = getAcceptedApplication($conn, $siswa_id);
if (!$accepted) {
    echo json_encode(['success' => false, 'message' => 'Anda belum diterima di perusahaan manapun.']);
    exit;
}

$application_id = (int)$accepted['id'];

// -----------------------------------------------------------------
// ACTION: CHECK IN
// -----------------------------------------------------------------
if ($action === 'checkin') {
    // Check for any open session (checked in but not yet checked out)
    $open_sql = "SELECT id FROM logbook_sessions
                 WHERE siswa_id = $siswa_id AND check_out IS NULL
                 LIMIT 1";
    $open_result = $conn->query($open_sql);
    if ($open_result && $open_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Anda masih memiliki sesi yang belum check-out. Selesaikan sesi sebelumnya terlebih dahulu.']);
        exit;
    }

    $now        = date('Y-m-d H:i:s');
    $day_of_week = (int)date('N'); // 1=Mon … 7=Sun
    $is_overtime = ($day_of_week >= 6) ? 1 : 0; // Saturday or Sunday

    $stmt = $conn->prepare(
        "INSERT INTO logbook_sessions (siswa_id, application_id, check_in, is_overtime) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('iisi', $siswa_id, $application_id, $now, $is_overtime);

    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        echo json_encode([
            'success'     => true,
            'message'     => 'Check-in berhasil!',
            'session_id'  => $session_id,
            'check_in'    => $now,
            'is_overtime' => (bool)$is_overtime
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal check-in: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// -----------------------------------------------------------------
// ACTION: CHECK OUT
// -----------------------------------------------------------------
if ($action === 'checkout') {
    $session_id = (int)($_POST['session_id'] ?? 0);
    $deskripsi  = trim($_POST['deskripsi'] ?? '');

    if ($session_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']);
        exit;
    }
    if (empty($deskripsi)) {
        echo json_encode(['success' => false, 'message' => 'Deskripsi pekerjaan wajib diisi sebelum check-out.']);
        exit;
    }

    // Verify this session belongs to this siswa and is still open
    $check_sql = "SELECT id FROM logbook_sessions
                  WHERE id = $session_id AND siswa_id = $siswa_id AND check_out IS NULL";
    $check_res = $conn->query($check_sql);
    if (!$check_res || $check_res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan atau sudah di-checkout.']);
        exit;
    }

    // Handle optional photo upload
    $foto_filename = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
        $allowed_exts  = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
        $max_size      = 5 * 1024 * 1024; // 5 MB

        $file_tmp  = $_FILES['foto']['tmp_name'];
        $file_name = $_FILES['foto']['name'];
        $file_size = $_FILES['foto']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Detect MIME (HEIC may report as application/octet-stream on some servers)
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $file_mime = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        $mime_ok = in_array($file_mime, $allowed_types) || in_array($file_ext, ['heic', 'heif']);
        $ext_ok  = in_array($file_ext, $allowed_exts);

        if (!$mime_ok || !$ext_ok) {
            echo json_encode(['success' => false, 'message' => 'Format foto tidak didukung. Gunakan JPG, PNG, WebP, atau HEIC.']);
            exit;
        }
        if ($file_size > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Ukuran foto maksimal 5 MB.']);
            exit;
        }

        $upload_dir = __DIR__ . '/uploads/logbook/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $foto_filename = 'logbook_' . $siswa_id . '_' . $session_id . '_' . time() . '.' . $file_ext;
        $destination   = $upload_dir . $foto_filename;

        if (!move_uploaded_file($file_tmp, $destination)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengunggah foto.']);
            exit;
        }
    }

    $now      = date('Y-m-d H:i:s');
    $desc_esc = $conn->real_escape_string($deskripsi);
    $foto_esc = $foto_filename ? "'" . $conn->real_escape_string($foto_filename) . "'" : 'NULL';

    $update_sql = "UPDATE logbook_sessions
                   SET check_out = '$now', deskripsi = '$desc_esc', foto = $foto_esc
                   WHERE id = $session_id AND siswa_id = $siswa_id";

    if ($conn->query($update_sql)) {
        echo json_encode([
            'success'   => true,
            'message'   => 'Check-out berhasil! Sampai jumpa besok 👋',
            'check_out' => $now
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal check-out: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
