<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS tp_rekrutmen_db";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

$conn->select_db("tp_rekrutmen_db");

// sql to create tables
$jobs_table = "CREATE TABLE IF NOT EXISTS jobs (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    description TEXT,
    requirements TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($jobs_table) === TRUE) {
    echo "Table jobs created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$applications_table = "CREATE TABLE IF NOT EXISTS applications (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT(6) UNSIGNED,
    nama_lengkap VARCHAR(255) NOT NULL,
    jurusan VARCHAR(255) NOT NULL,
    kelas VARCHAR(100) NOT NULL,
    nomor_wa VARCHAR(50) NOT NULL,
    cv_file VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
)";

if ($conn->query($applications_table) === TRUE) {
    echo "Table applications created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$users_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'teacher',
    company_name VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($users_table) === TRUE) {
    echo "Table users created successfully<br>";
    
    // Insert default admin user if not exists
    $check_user = $conn->query("SELECT * FROM users WHERE username = 'admin'");
    if($check_user->num_rows == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role) VALUES ('admin', '$hashed_password', 'teacher')");
        echo "Default admin user created (admin / admin123)<br>";
    }

    // Insert default recruiter user if not exists
    $check_recruiter = $conn->query("SELECT * FROM users WHERE username = 'recruiter'");
    if($check_recruiter->num_rows == 0) {
        $hashed_password = password_hash('recruiter123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role) VALUES ('recruiter', '$hashed_password', 'recruiter')");
        echo "Default recruiter user created (recruiter / recruiter123)<br>";
    }

    // Insert default siswa user if not exists
    $check_siswa = $conn->query("SELECT * FROM users WHERE username = 'siswa'");
    if($check_siswa->num_rows == 0) {
        $hashed_password = password_hash('siswa123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, password, role) VALUES ('siswa', '$hashed_password', 'siswa')");
        echo "Default siswa user created (siswa / siswa123)<br>";
    }
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
echo "Setup complete. You can delete this file now.";
?>
