<?php
require 'config/database.php';
$db = new Database();
$conn = $db->connect();
$conn->query('SET FOREIGN_KEY_CHECKS = 0');
$conn->query('TRUNCATE TABLE registration_answers');
$conn->query('TRUNCATE TABLE registrations');
$conn->query('SET FOREIGN_KEY_CHECKS = 1');
echo 'Registrations and answers cleared.';
