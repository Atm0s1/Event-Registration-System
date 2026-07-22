<?php
/**
 * Admin — Registrations (organized by event)
 */
$currentPage = 'registrations';
$pageTitle   = 'Registrations';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';

$db   = new Database();
$conn = $db->connect();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_register'])) {
    $eId = (int)$_POST['event_id'];
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $age = (int)($_POST['age'] ?? 0);

    if ($eId && $fname && $lname && $email) {
        try {
            $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
            $stmt->execute([$eId]);
            $event = $stmt->fetch();
            if (!$event) throw new Exception("Invalid event.");
            
            if ($event['min_age'] > 0 && $age < $event['min_age']) {
                throw new Exception("This event requires a minimum age of {$event['min_age']}. Attendee is only {$age}.");
            }

            $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $userStmt->execute([$email]);
            $userId = $userStmt->fetchColumn();

            if (!$userId) {
                $dummyPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $insertUser = $conn->prepare("INSERT INTO users (fname, lname, email, password, contact_number) VALUES (?, ?, ?, ?, ?)");
                $insertUser->execute([$fname, $lname, $email, $dummyPass, $contact]);
                $userId = $conn->lastInsertId();
            }

            $checkReg = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND event_id = ?");
            $checkReg->execute([$userId, $eId]);
            if ($checkReg->fetchColumn() > 0) throw new Exception("This user is already registered for this event.");

            if ($event['event_date'] && $event['event_time']) {
                $timeCheck = $conn->prepare("
                    SELECT e.event_name 
                    FROM registrations r
                    JOIN events e ON r.event_id = e.event_id
                    WHERE r.user_id = ? 
                      AND e.event_date = ? 
                      AND e.event_time = ?
                      AND e.event_id != ?
                ");
                $timeCheck->execute([$userId, $event['event_date'], $event['event_time'], $eId]);
                $conflict = $timeCheck->fetchColumn();
                if ($conflict) {
                    throw new Exception("Time conflict: User is already registered for '$conflict' at the exact same date and time.");
                }
            }

            $qrToken = bin2hex(random_bytes(16));
            $regStmt = $conn->prepare("INSERT INTO registrations (user_id,event_id,status,registered_date,registered_time,qr_token) VALUES (?,?,'approved',CURDATE(),CURTIME(),?)");
            $regStmt->execute([$userId, $eId, $qrToken]);
            
            $fullName = $fname . ' ' . $lname;
            $mapUrl = (!empty($event['latitude']) && !empty($event['longitude'])) ? "https://www.google.com/maps/search/?api=1&query={$event['latitude']},{$event['longitude']}" : '';
            
            sendApprovalEmail($email, $fullName, $event['event_name'], $event['event_date'], $event['venue'], $qrToken, $mapUrl);
            
            $success = "Successfully registered $fullName for {$event['event_name']}!";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Get filter
$filterEvent = $_GET['event_id'] ?? '';
$searchName  = trim($_GET['search'] ?? '');

// Build query
$where  = "WHERE 1=1";
$params = [];

if ($filterEvent !== '') {
    $where .= " AND r.event_id = :event_id";
    $params[':event_id'] = (int)$filterEvent;
}
if ($searchName !== '') {
    $where .= " AND (u.fname LIKE :s OR u.lname LIKE :s OR u.email LIKE :s)";
    $params[':s'] = "%$searchName%";
}

$regs = $conn->prepare("
    SELECT r.*, u.fname, u.lname, u.email, u.contact_number,
           e.event_name, e.icon
    FROM registrations r
    JOIN users u  ON r.user_id  = u.user_id
    JOIN events e ON r.event_id = e.event_id
    $where
    ORDER BY e.event_name, r.registered_date DESC
");
$regs->execute($params);
$registrations = $regs->fetchAll();

// Group by event
$grouped = [];
foreach ($registrations as $r) {
    $key = $r['icon'] . ' ' . $r['event_name'];
    $grouped[$key][] = $r;
}

// All events for filter dropdown
$allEvents = $conn->query("SELECT event_id, event_name, icon FROM events ORDER BY event_name")->fetchAll();

// Only active events for manual registration dropdown
$activeEvents = $conn->query("SELECT event_id, event_name, icon FROM events WHERE is_active = 1 AND is_archived = 0 ORDER BY event_name")->fetchAll();

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="margin-bottom:4px;">Registrations</h1>
        <p class="page-subtitle" style="margin-bottom:0;">All registered users organized by event</p>
    </div>
    <button onclick="document.getElementById('manualRegForm').style.display='block'; this.style.display='none';" class="btn btn-primary" id="addRegBtn" style="white-space:nowrap; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"><i class="ph-bold ph-user-plus"></i> Manually Register Attendee</button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Manual Registration Form -->
<div class="card" id="manualRegForm" style="display:none; margin-bottom:28px;">
    <h3 style="margin-bottom:16px;"><i class="ph-bold ph-user-plus"></i> Manually Register Attendee</h3>
    <form method="POST" id="regForm" autocomplete="off">
        <input type="hidden" name="manual_register" value="1">
        
        <div class="form-group">
            <label class="form-label">Select Event *</label>
            <select name="event_id" id="eventId" class="form-select" required>
                <option value="">-- Choose an Event --</option>
                <?php foreach ($activeEvents as $ev): ?>
                    <option value="<?= $ev['event_id'] ?>"><?= $ev['icon'] ?> <?= htmlspecialchars($ev['event_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="fname" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="lname" class="form-input" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" id="emailInput" class="form-input" required>
                <div id="emailWarning" style="display:none; color:var(--warning); font-size:12px; margin-top:4px; align-items:center; gap:4px;">
                    <i class="ph-bold ph-warning-circle"></i> <span>This user is already registered for this event.</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number *</label>
                <input type="text" name="contact_number" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Age *</label>
                <input type="number" name="age" id="ageInput" class="form-input" min="1" required>
                <div id="ageWarning" style="display:none; color:var(--warning); font-size:12px; margin-top:4px; align-items:center; gap:4px;">
                    <i class="ph-bold ph-warning-circle"></i> <span id="ageWarningText">Age does not meet requirement.</span>
                </div>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" id="submitBtn" class="btn btn-primary">Register & Send Ticket</button>
            <button type="button" onclick="document.getElementById('manualRegForm').style.display='none'; document.getElementById('addRegBtn').style.display='inline-flex';" class="btn btn-secondary">Cancel</button>
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

        fetch(`api_check_reg.php?event_id=${ev}&email=${encodeURIComponent(em)}&age=${ag}`)
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
                    ageWarningText.textContent = `Event requires a minimum age of ${data.min_age}.`;
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

    eventId.addEventListener('change', checkValidation);
    emailInput.addEventListener('input', checkValidation);
    ageInput.addEventListener('input', checkValidation);
});
</script>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <select name="event_id" class="form-select" style="min-width:200px;">
            <option value="">All Events</option>
            <?php foreach ($allEvents as $ev): ?>
                <option value="<?= $ev['event_id'] ?>" <?= $filterEvent == $ev['event_id'] ? 'selected' : '' ?>>
                    <?= $ev['icon'] ?> <?= htmlspecialchars($ev['event_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" class="form-input" placeholder="Search name or email..."
               value="<?= htmlspecialchars($searchName) ?>" style="min-width:200px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="ph-bold ph-magnifying-glass"></i> Filter</button>
        <a href="registrations.php" class="btn btn-secondary btn-sm">Clear</a>
    </form>
</div>

<?php if (empty($grouped)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="ph-fill ph-users"></i></div>
        <p>No registrations found.</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $eventLabel => $regs): ?>
        <div class="event-section">
            <div class="event-section-title"><?= $eventLabel ?> <span class="badge badge-approved"><?= count($regs) ?></span></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Name</th>
                            <th style="width: 30%;">Email</th>
                            <th style="width: 15%;">Contact</th>
                            <th style="width: 15%;">Date</th>
                            <th style="width: 15%;">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($regs as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></td>
                            <td><?= htmlspecialchars($r['email']) ?></td>
                            <td><?= htmlspecialchars($r['contact_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($r['registered_date'] . ' ' . $r['registered_time']) ?></td>
                            <td>
                                <?php if (($r['attendance_status'] ?? '') === 'present'): ?>
                                    <span class="badge badge-approved" style="background:rgba(21,128,61,0.1);color:#15803d;border:1px solid #bbf7d0;">✓ Present</span>
                                <?php else: ?>
                                    <span style="color: #94A3B8;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
