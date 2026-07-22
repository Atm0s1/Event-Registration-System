<?php
/**
 * User Login
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// Already logged in?
if (isset($_SESSION['user_logged_in'])) {
    header('Location: events.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db   = new Database();
            $conn = $db->connect();
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password'])) {
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['user_name']     = $user['fname'] . ' ' . $user['lname'];
                $_SESSION['user_email']    = $user['email'];
                header('Location: events.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
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
    <title>Login — Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <i class="ph-fill ph-user-circle" style="font-size: 32px;"></i>
        </div>
        <h2>Welcome Back</h2>
        <p class="auth-subtitle">Login to your Event Management account</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 24px; text-align: left; border-radius: 12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="auth-links">
            <p style="margin-bottom: 12px;">Don't have an account? <a href="register.php">Create one</a></p>
            <a href="../index.php"><i class="ph ph-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
