<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['email']) || !isset($_GET['event_id'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$email = trim($_GET['email']);
$eventId = (int)$_GET['event_id'];
$age = (int)($_GET['age'] ?? 0);

$db = new Database();
$conn = $db->connect();

$response = [
    'duplicate' => false,
    'age_error' => false,
    'min_age' => 0
];

// Check Event min age
$stmt = $conn->prepare("SELECT min_age FROM events WHERE event_id = ?");
$stmt->execute([$eventId]);
$minAge = $stmt->fetchColumn();

if ($minAge > 0 && $age > 0 && $age < $minAge) {
    $response['age_error'] = true;
    $response['min_age'] = $minAge;
}

// Check duplication
if (!empty($email)) {
    $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $userId = $userStmt->fetchColumn();

    if ($userId) {
        $regStmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND event_id = ?");
        $regStmt->execute([$userId, $eventId]);
        if ($regStmt->fetchColumn() > 0) {
            $response['duplicate'] = true;
        }
    }
}

echo json_encode($response);
