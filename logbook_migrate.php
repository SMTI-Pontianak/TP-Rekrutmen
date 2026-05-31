<?php
session_start();
require_once 'config.php';

// Only allow teacher to run this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Akses ditolak. Hanya teacher yang dapat menjalankan migrasi ini.");
}

echo "<pre>";

// Create uploads/logbook directory
$logbook_dir = __DIR__ . '/uploads/logbook';
if (!is_dir($logbook_dir)) {
    if (mkdir($logbook_dir, 0755, true)) {
        echo "✅ Direktori uploads/logbook berhasil dibuat\n";
    } else {
        echo "❌ Gagal membuat direktori uploads/logbook\n";
    }
} else {
    echo "ℹ️  Direktori uploads/logbook sudah ada\n";
}

// Create logbook_sessions table
$sql = "CREATE TABLE IF NOT EXISTS logbook_sessions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    siswa_id       INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL,
    check_in       DATETIME NOT NULL,
    check_out      DATETIME DEFAULT NULL,
    deskripsi      TEXT DEFAULT NULL,
    foto           VARCHAR(255) DEFAULT NULL,
    is_overtime    TINYINT(1) DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($sql) === TRUE) {
    echo "✅ Tabel logbook_sessions berhasil dibuat (atau sudah ada)\n";
} else {
    echo "❌ Error membuat tabel logbook_sessions: " . $conn->error . "\n";
}

echo "\n✅ Migrasi selesai! Anda dapat menghapus file ini setelah migrasi berhasil.\n";
echo "</pre>";
?>
