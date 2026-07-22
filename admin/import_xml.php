<?php
/**
 * Admin — Import XML
 * Imports registration data from an XML file.
 */
$currentPage = 'import';
$pageTitle   = 'Import XML';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';

$db   = new Database();
$conn = $db->connect();

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    $file = $_FILES['xml_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed.';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'xml') {
        $error = 'Please upload an XML file.';
    } else {
        try {
            $dom = new DOMDocument();
            $dom->load($file['tmp_name']);
            $nodes = $dom->getElementsByTagName('registration');
            $imported = 0;
            $skipped  = 0;

            foreach ($nodes as $node) {
                $fname   = $node->getElementsByTagName('fname')->item(0)->nodeValue ?? '';
                $lname   = $node->getElementsByTagName('lname')->item(0)->nodeValue ?? '';
                $email   = $node->getElementsByTagName('email')->item(0)->nodeValue ?? '';
                $contact = $node->getElementsByTagName('contact')->item(0)->nodeValue ?? '';
                $event   = $node->getElementsByTagName('event')->item(0)->nodeValue ?? '';
                $status  = $node->getElementsByTagName('status')->item(0)->nodeValue ?? 'pending';
                $date    = $node->getElementsByTagName('date')->item(0)->nodeValue ?? date('Y-m-d');
                $time    = $node->getElementsByTagName('time')->item(0)->nodeValue ?? date('H:i:s');

                // Find or create user
                $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $userStmt->execute([$email]);
                $user = $userStmt->fetch();

                if (!$user) {
                    $conn->prepare("INSERT INTO users (fname,lname,email,password,contact_number) VALUES (?,?,?,?,?)")
                         ->execute([$fname, $lname, $email, password_hash('imported123', PASSWORD_DEFAULT), $contact]);
                    $userId = $conn->lastInsertId();
                } else {
                    $userId = $user['user_id'];
                }

                // Find event
                $evStmt = $conn->prepare("SELECT event_id FROM events WHERE event_name = ?");
                $evStmt->execute([$event]);
                $evData = $evStmt->fetch();

                if (!$evData) {
                    $skipped++;
                    continue;
                }

                // Check for duplicate
                $check = $conn->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND event_id = ?");
                $check->execute([$userId, $evData['event_id']]);
                if ($check->fetchColumn() > 0) {
                    $skipped++;
                    continue;
                }

                // Insert registration
                $conn->prepare("INSERT INTO registrations (user_id,event_id,status,registered_date,registered_time) VALUES (?,?,?,?,?)")
                     ->execute([$userId, $evData['event_id'], $status, $date, $time]);
                $imported++;
            }

            $success = "Import complete! $imported registration(s) imported, $skipped skipped.";
        } catch (Exception $e) {
            $error = 'Import error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<h1 class="page-title">Import XML</h1>
<p class="page-subtitle">Upload an XML file to import registration data</p>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="glass-card" style="max-width:500px;">
    <div style="text-align:center;font-size:64px;margin-bottom:16px;">📥</div>
    <h3 style="text-align:center;margin-bottom:16px;">Upload XML File</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Select XML File</label>
            <input type="file" name="xml_file" accept=".xml" class="form-input" required>
        </div>
        <div class="btn-group" style="justify-content:center;">
            <button type="submit" class="btn btn-primary">⬆️ Import</button>
        </div>
    </form>
    <p style="text-align:center;margin-top:16px;font-size:12px;color:var(--text-muted);">
        Expected format: &lt;registrations&gt; → &lt;registration&gt; with fname, lname, email, event, status, etc.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
