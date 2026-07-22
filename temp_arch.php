<?php
require 'config/database.php';
$db = new Database();
$conn = $db->connect();
$conn->query('ALTER TABLE events ADD COLUMN is_archived TINYINT(1) DEFAULT 0 AFTER is_active');
echo 'Added is_archived column';
