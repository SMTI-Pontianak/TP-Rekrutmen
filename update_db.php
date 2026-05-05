<?php
require_once 'config.php';

$sql = "ALTER TABLE jobs ADD COLUMN user_id INT(6) UNSIGNED DEFAULT 1 AFTER id";
if ($conn->query($sql) === TRUE) {
    echo "Column user_id added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}
?>
