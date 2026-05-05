<?php
require_once 'config.php';

$sql = "ALTER TABLE users ADD COLUMN company_name VARCHAR(255) NULL AFTER role";
if ($conn->query($sql) === TRUE) {
    echo "Column company_name added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}
?>
