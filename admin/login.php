<?php
/**
 * Admin Login Page
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// Already logged in?
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db   = new Database();
            $conn = $db->connect();
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']       = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'System error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <i class="ph-fill ph-shield-check" style="font-size: 32px;"></i>
        </div>
        <h2>Admin Portal</h2>
        <p class="auth-subtitle">Event Management System</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 24px; text-align: left; border-radius: 12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Secure Login</button>
        </form>

        <div class="auth-links">
            <a href="../index.php"><i class="ph ph-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
