<?php
$conn = new mysqli('localhost', 'root', '');
$conn->query('DROP DATABASE IF EXISTS tp_rekrutmen');
echo "Dropped";
?>
