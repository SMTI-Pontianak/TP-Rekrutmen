<?php
/**
 * Migration: Add application status tracking
 * Adds siswa_id and status columns to applications table
 */

require_once 'config.php';

echo "Starting migration...\n\n";

// Check if siswa_id column exists
$check_siswa_id = "SHOW COLUMNS FROM applications LIKE 'siswa_id'";
$result = $conn->query($check_siswa_id);
if ($result->num_rows == 0) {
    $add_siswa_id = "ALTER TABLE applications ADD COLUMN siswa_id INT(6) UNSIGNED AFTER job_id";
    if ($conn->query($add_siswa_id) === TRUE) {
        echo "✓ Added siswa_id column\n";
    } else {
        echo "✗ Error adding siswa_id: " . $conn->error . "\n";
    }
} else {
    echo "✓ siswa_id column already exists\n";
}

// Check if status column exists
$check_status = "SHOW COLUMNS FROM applications LIKE 'status'";
$result = $conn->query($check_status);
if ($result->num_rows == 0) {
    $add_status = "ALTER TABLE applications ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER applied_at";
    if ($conn->query($add_status) === TRUE) {
        echo "✓ Added status column\n";
    } else {
        echo "✗ Error adding status: " . $conn->error . "\n";
    }
} else {
    echo "✓ status column already exists\n";
}

// Check if updated_at column exists
$check_updated = "SHOW COLUMNS FROM applications LIKE 'updated_at'";
$result = $conn->query($check_updated);
if ($result->num_rows == 0) {
    $add_updated = "ALTER TABLE applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER status";
    if ($conn->query($add_updated) === TRUE) {
        echo "✓ Added updated_at column\n";
    } else {
        echo "✗ Error adding updated_at: " . $conn->error . "\n";
    }
} else {
    echo "✓ updated_at column already exists\n";
}

echo "\n✓ Migration completed successfully!\n";
echo "\nApplications table structure updated with:\n";
echo "- siswa_id: Track which student applied\n";
echo "- status: Application status (pending, reviewed, accepted, rejected)\n";
echo "- updated_at: Track when status was last changed\n";
?>
