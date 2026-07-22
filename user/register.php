<?php
/**
 * User Registration (Account Creation)
 */
session_start();
require_once __DIR__ . '/../config/database.php';

// Already logged in?
if (isset($_SESSION['user_logged_in'])) {
    header('Location: events.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname   = trim($_POST['fname'] ?? '');
    $lname   = trim($_POST['lname'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $contact = trim($_POST['contact_number'] ?? '');

    if (empty($fname) || empty($lname) || empty($email) || empty($pass)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $db   = new Database();
            $conn = $db->connect();

            // Check if email already exists
            $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetchColumn() > 0) {
                $error = 'An account with this email already exists.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (fname,lname,email,password,contact_number) VALUES (?,?,?,?,?)");
                $stmt->execute([$fname, $lname, $email, $hash, $contact ?: null]);
                $success = 'Account created successfully! You can now log in.';
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
    <title>Register — Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="max-width: 500px;">
        <div class="auth-logo">
            <i class="ph-fill ph-user-plus" style="font-size: 32px;"></i>
        </div>
        <h2>Create Account</h2>
        <p class="auth-subtitle">Join Event Management to register for events</p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 24px; text-align: left; border-radius: 12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 24px; text-align: left; border-radius: 12px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="fname" class="form-input" required autofocus>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lname" class="form-input" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-input">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm</label>
                    <input type="password" name="confirm_password" class="form-input" required>
                </div>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>
        </form>

        <div class="auth-links">
            <p style="margin-bottom: 12px;">Already have an account? <a href="login.php">Log In</a></p>
            <a href="../index.php"><i class="ph ph-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
