<?php
require 'config/database.php';
$db = new Database();
$conn = $db->connect();
$stmt = $conn->query('DESCRIBE registrations');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
