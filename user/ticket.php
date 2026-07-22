<?php
/**
 * User — Digital E-Ticket
 */
$currentPage = 'events';
$pageTitle   = 'My Ticket';
require_once __DIR__ . '/../includes/header_user.php';

$regId = (int)($_GET['reg_id'] ?? 0);
if (!$regId) {
    echo "<div class='alert alert-error'>Invalid ticket request.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT r.*, e.event_name, e.event_date, e.venue, e.color, e.icon, u.fname, u.lname 
    FROM registrations r 
    JOIN events e ON r.event_id = e.event_id 
    JOIN users u ON r.user_id = u.user_id 
    WHERE r.reg_id = ? AND r.user_id = ?
");
$stmt->execute([$regId, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo "<div class='alert alert-error'>Ticket not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($ticket['status'] !== 'approved') {
    echo "<div class='alert alert-error'>Your registration is currently " . htmlspecialchars(ucfirst($ticket['status'])) . ". Ticket is only available after approval.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Ensure token exists (fallback if old row didn't get one generated)
$qrToken = $ticket['qr_token'];
if (empty($qrToken)) {
    $qrToken = bin2hex(random_bytes(16));
    $conn->prepare("UPDATE registrations SET qr_token = ? WHERE reg_id = ?")->execute([$qrToken, $regId]);
}

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrToken);
$color = htmlspecialchars($ticket['color'] ?? '#667eea');
?>

<div style="max-width: 400px; margin: 40px auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: var(--text-dark);">Your Ticket</h1>
        <button onclick="window.print()" class="btn btn-outline" style="padding: 8px 16px; background: white; border-color: #E2E8F0;"><i class="ph ph-printer"></i> Print</button>
    </div>

    <!-- Ticket Card -->
    <div style="background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.08); border: 1px solid #F1F5F9; position: relative;">
        <!-- Top color bar -->
        <div style="height: 120px; background: <?= $color ?>; position: relative; padding: 24px; display: flex; align-items: flex-end;">
            <!-- Subtle pattern overlay could go here -->
            <div style="width: 64px; height: 64px; background: white; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 32px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); position: absolute; bottom: -32px; left: 32px;">
                <i class="ph-fill ph-ticket"></i>
            </div>
        </div>

        <!-- Ticket Body -->
        <div style="padding: 48px 32px 32px 32px; text-align: center;">
            <h2 style="font-size: 24px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; line-height: 1.2;">
                <?= htmlspecialchars($ticket['event_name']) ?>
            </h2>
            <p style="color: var(--text-light); font-size: 15px; margin-bottom: 32px;">
                Admit One &bull; <?= htmlspecialchars($ticket['fname'] . ' ' . $ticket['lname']) ?>
            </p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; text-align: left; margin-bottom: 32px; background: #F8FAFC; padding: 16px; border-radius: 16px;">
                <div>
                    <span style="display: block; font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Date</span>
                    <strong style="color: var(--text-dark); font-size: 14px;">
                        <?= $ticket['event_date'] ? date('M j, Y', strtotime($ticket['event_date'])) : 'TBD' ?>
                    </strong>
                </div>
                <div>
                    <span style="display: block; font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">Venue</span>
                    <strong style="color: var(--text-dark); font-size: 14px;">
                        <?= htmlspecialchars($ticket['venue'] ?? 'TBD') ?>
                    </strong>
                </div>
            </div>

            <!-- Perforated Line -->
            <div style="position: relative; border-top: 2px dashed #E2E8F0; margin: 32px -32px;">
                <div style="width: 24px; height: 24px; background: #F8FAFC; border-radius: 50%; position: absolute; top: -12px; left: -12px; box-shadow: inset -2px 0 5px rgba(0,0,0,0.02);"></div>
                <div style="width: 24px; height: 24px; background: #F8FAFC; border-radius: 50%; position: absolute; top: -12px; right: -12px; box-shadow: inset 2px 0 5px rgba(0,0,0,0.02);"></div>
            </div>

            <!-- QR Code -->
            <div style="margin: 0 auto; padding: 16px; background: white; border-radius: 16px; display: inline-block; border: 1px solid #F1F5F9; box-shadow: 0 10px 20px rgba(0,0,0,0.03);">
                <img src="<?= $qrUrl ?>" alt="QR Code" style="width: 200px; height: 200px; display: block;">
            </div>
            <p style="margin-top: 16px; font-family: monospace; color: var(--text-muted); letter-spacing: 2px; font-size: 12px;">
                <?= substr($qrToken, 0, 12) ?>...
            </p>
            
            <?php if ($ticket['attendance_status'] === 'present'): ?>
                <div style="margin-top: 16px; display: inline-flex; align-items: center; gap: 8px; background: #ECFDF5; color: #059669; padding: 8px 16px; border-radius: 999px; font-weight: 600; font-size: 14px;">
                    <i class="ph-fill ph-check-circle"></i> Ticket Scanned
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    @media print {
        body * { visibility: hidden; }
        .header, .sidebar { display: none; }
        .main-content { padding: 0 !important; margin: 0 !important; }
        .alert, .btn { display: none !important; }
        div[style*="max-width: 400px"] * { visibility: visible; }
        div[style*="max-width: 400px"] { position: absolute; left: 50%; top: 50px; transform: translateX(-50%); width: 100%; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
