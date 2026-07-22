<?php
/**
 * User — Register for an Event
 * Shows form with 3 requirement questions, validates age, saves as pending.
 */
$currentPage = 'events';
$pageTitle   = 'Register for Event';

require_once __DIR__ . '/../config/database.php';

// Guest Registration Flow
session_start();
$db      = new Database();
$conn    = $db->connect();
$eventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);

if (!$eventId) {
    header('Location: events.php');
    exit;
}

// Fetch event
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? AND is_active = 1 AND is_archived = 0");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: events.php');
    exit;
}

// Removed initial user checks since this is a guest form

// Fetch requirements
$reqStmt = $conn->prepare("SELECT * FROM event_requirements WHERE event_id = ? ORDER BY sort_order");
$reqStmt->execute([$eventId]);
$requirements = $reqStmt->fetchAll();

$error   = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get User Details
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $age = (int)($_POST['age'] ?? 0);

    if (empty($fname) || empty($lname) || empty($email)) {
        $error = "Name and Email are required.";
    } elseif ($event['min_age'] > 0 && $age < $event['min_age']) {
        $error = "You must be at least {$event['min_age']} years old to register for this event.";
    } else {
        try {
            // Check if user exists by email, if not create them
            $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $userStmt->execute([$email]);
            $userId = $userStmt->fetchColumn();

            if (!$userId) {
                // Auto-create guest user
                $dummyPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $insertUser = $conn->prepare("INSERT INTO users (fname, lname, email, password, contact_number) VALUES (?, ?, ?, ?, ?)");
                $insertUser->execute([$fname, $lname, $email, $dummyPass, $contact]);
                $userId = $conn->lastInsertId();
            }

            // Check if already registered
            $checkReg = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND event_id = ?");
            $checkReg->execute([$userId, $eventId]);
            if ($checkReg->fetchColumn() > 0) {
                throw new Exception("You have already registered for this event with this email.");
            }

            $initialStatus = 'pending';
            if (!empty($event['max_capacity']) && $event['max_capacity'] > 0) {
                $capStmt = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status = 'approved'");
                $capStmt->execute([$eventId]);
                if ($capStmt->fetchColumn() >= $event['max_capacity']) {
                    $initialStatus = 'waitlisted';
                }
            }

            // Insert registration
            $regStmt = $conn->prepare("INSERT INTO registrations (user_id,event_id,status,registered_date,registered_time) VALUES (?,?,?,CURDATE(),CURTIME())");
            $regStmt->execute([$userId, $eventId, $initialStatus]);
            $regId = $conn->lastInsertId();

            // Insert answers
            $ansStmt = $conn->prepare("INSERT INTO registration_answers (reg_id,req_id,answer_text) VALUES (?,?,?)");
            foreach ($requirements as $req) {
                $answer = trim($_POST['answer_' . $req['req_id']] ?? '');
                $ansStmt->execute([$regId, $req['req_id'], $answer]);
            }

            $success = true;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Don't include header_user for success page (shows its own layout)
if (!$success) {
    require_once __DIR__ . '/../includes/header_user.php';
}
?>

<?php if ($success): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Submitted — Event Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card" style="text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🎉</div>
        <h2 style="background:linear-gradient(135deg,#00b894,#00cec9);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Registration Submitted!</h2>
        <?php if ($initialStatus === 'waitlisted'): ?>
            <p style="color:var(--text-light);margin:12px 0 24px;">
                Your registration for <strong style="color:var(--text-dark);"><?= htmlspecialchars($event['event_name']) ?></strong>
                has been submitted, but the event is currently <span class="badge" style="background:#FFFBEB;color:#D97706;">At Capacity</span>. 
                You have been placed on the <span class="badge" style="background:#F3F4F6;color:#4B5563;">Waitlist</span>.
            </p>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:24px;">
                If a spot opens up, you will be automatically approved and notified via email!
            </p>
        <?php else: ?>
            <p style="color:var(--text-light);margin:12px 0 24px;">
                Your registration for <strong style="color:var(--text-dark);"><?= htmlspecialchars($event['event_name']) ?></strong>
                has been submitted and is <span class="badge" style="background:#FEF3C7;color:#D97706;">Pending</span> admin approval.
            </p>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:24px;">
                You'll receive an email notification once your registration is approved.
            </p>
        <?php endif; ?>
        <div class="btn-group" style="justify-content:center;">
            <a href="events.php" class="btn btn-primary">Browse Events</a>
            <a href="my_registrations.php" class="btn btn-secondary">My Registrations</a>
        </div>
    </div>
</div>
</body>
</html>

<?php else: ?>

<h1 class="page-title"><?= $event['icon'] ?> Register for <?= htmlspecialchars($event['event_name']) ?></h1>
<p class="page-subtitle">Fill in the form below to register. Your application will be reviewed by an admin.</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="max-width:700px;">
    <!-- Event Info Card -->
    <div class="glass-card" style="margin-bottom:24px;">
        <h3 style="margin-bottom:12px;">📋 Event Details</h3>
        <p style="color:var(--text-secondary);margin:4px 0;">📅 Date: <strong style="color:var(--text-primary);"><?= htmlspecialchars($event['event_date'] ?? 'TBD') ?></strong></p>
        <p style="color:var(--text-secondary);margin:4px 0;">📍 Venue: <strong style="color:var(--text-primary);"><?= htmlspecialchars($event['venue'] ?? 'TBD') ?></strong></p>
        <p style="color:var(--text-secondary);margin:4px 0;">🎂 Minimum Age: <strong style="color:var(--text-primary);"><?= $event['min_age'] ?></strong></p>
        <p style="color:var(--text-secondary);margin:8px 0 0;"><?= htmlspecialchars($event['description']) ?></p>
    </div>

    <!-- Registration Form -->
    <form method="POST" id="regForm" autocomplete="off">
        <input type="hidden" name="event_id" id="eventId" value="<?= $eventId ?>">

        <div class="glass-card" style="margin-bottom:24px;">
            <h3 style="margin-bottom:16px;">👤 Your Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="fname" class="form-input" required value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="lname" class="form-input" required value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" id="emailInput" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <div id="emailWarning" style="display:none; color:var(--warning); font-size:12px; margin-top:4px; align-items:center; gap:4px;">
                        <i class="ph-bold ph-warning-circle"></i> <span>You are already registered for this event.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Number *</label>
                    <input type="text" name="contact_number" class="form-input" required value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Your Age *</label>
                <input type="number" name="age" id="ageInput" class="form-input" min="1" max="120" required
                       value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"
                       placeholder="Enter your current age">
                
                <div id="ageWarning" style="display:none; color:var(--warning); font-size:12px; margin-top:4px; align-items:center; gap:4px;">
                    <i class="ph-bold ph-warning-circle"></i> <span id="ageWarningText">Age does not meet requirement.</span>
                </div>
                
                <?php if ($event['min_age'] > 0): ?>
                    <small style="color:var(--text-light);font-size:12px;margin-top:4px;display:block;"><i class="ph-bold ph-info"></i> Minimum age for this event: <?= $event['min_age'] ?></small>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($requirements)): ?>
        <div class="glass-card" style="margin-bottom:24px;">
            <h3 style="margin-bottom:16px;">📝 Requirements</h3>
            <?php foreach ($requirements as $i => $req): ?>
                <div class="form-group">
                    <label class="form-label"><?= ($i + 1) ?>. <?= htmlspecialchars($req['requirement_text']) ?></label>
                    <textarea name="answer_<?= $req['req_id'] ?>" class="form-textarea" rows="2" required
                              placeholder="Type your answer..."><?= htmlspecialchars($_POST['answer_' . $req['req_id']] ?? '') ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="btn-group">
            <button type="submit" id="submitBtn" class="btn btn-primary"><i class="ph-bold ph-check-circle"></i> Submit Registration</button>
            <a href="events.php" class="btn btn-secondary">← Back to Events</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventId = document.getElementById('eventId');
    const emailInput = document.getElementById('emailInput');
    const ageInput = document.getElementById('ageInput');
    const emailWarning = document.getElementById('emailWarning');
    const ageWarning = document.getElementById('ageWarning');
    const ageWarningText = document.getElementById('ageWarningText');
    const submitBtn = document.getElementById('submitBtn');

    function checkValidation() {
        const ev = eventId.value;
        const em = emailInput.value.trim();
        const ag = ageInput.value.trim();

        if (!ev) return;

        fetch(`../admin/api_check_reg.php?event_id=${ev}&email=${encodeURIComponent(em)}&age=${ag}`)
            .then(res => res.json())
            .then(data => {
                let hasError = false;

                if (data.duplicate) {
                    emailWarning.style.display = 'flex';
                    hasError = true;
                } else {
                    emailWarning.style.display = 'none';
                }

                if (data.age_error) {
                    ageWarningText.textContent = `You must be at least ${data.min_age} to register.`;
                    ageWarning.style.display = 'flex';
                    hasError = true;
                } else {
                    ageWarning.style.display = 'none';
                }

                submitBtn.disabled = hasError;
                if (hasError) {
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                } else {
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            })
            .catch(err => console.error(err));
    }

    emailInput.addEventListener('input', checkValidation);
    ageInput.addEventListener('input', checkValidation);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
