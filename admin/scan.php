<?php
/**
 * Online Receipt (QR Code Target)
 */
$currentPage = 'scan';
$pageTitle   = 'Online Receipt';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';
$db   = new Database();
$conn = $db->connect();

$error = '';
$reg = null;

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    try {
        $stmt = $conn->prepare("
            SELECT r.*, u.fname, u.lname, u.email, u.contact_number, e.event_name, e.event_date, e.venue
            FROM registrations r
            JOIN users u ON r.user_id = u.user_id
            JOIN events e ON r.event_id = e.event_id
            WHERE r.qr_token = ?
        ");
        $stmt->execute([$token]);
        $reg = $stmt->fetch();

        if (!$reg) {
            $error = "Invalid or unrecognized ticket receipt.";
        }
    } catch (Exception $e) {
        $error = "System error loading receipt.";
    }
} else {
    $error = "No ticket token provided.";
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div id="receiptCard" style="max-width:600px; margin: 40px auto; background: white; padding: 40px; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid var(--border);">
    <div style="text-align: center; margin-bottom: 32px;">
        <i class="ph-fill ph-check-circle" style="font-size: 64px; color: #10B981; margin-bottom: 16px;"></i>
        <h1 style="font-size: 28px; font-weight: 800; color: var(--text-dark); margin-bottom: 8px;">Official Ticket Receipt</h1>
        <p style="color: var(--text-light);">This digital receipt is valid and confirmed.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="font-size: 18px; padding: 20px; text-align: center;">
            <i class="ph-bold ph-warning-circle"></i> <?= $error ?>
        </div>
    <?php elseif ($reg): ?>
        <div style="background: #F8FAFC; border-radius: 16px; padding: 24px; border: 1px solid #E2E8F0; margin-bottom: 24px;">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px;">Attendee Name</div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--text-dark);"><i class="ph ph-user"></i> <?= htmlspecialchars($reg['fname'] . ' ' . $reg['lname']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px;">Contact Info</div>
                    <div style="font-size: 16px; color: var(--text-dark);"><i class="ph ph-envelope-simple"></i> <?= htmlspecialchars($reg['email']) ?></div>
                    <div style="font-size: 16px; color: var(--text-dark); margin-top: 4px;"><i class="ph ph-phone"></i> <?= htmlspecialchars($reg['contact_number'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>

        <div style="background: white; border-radius: 16px; padding: 24px; border: 1px solid #E2E8F0;">
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px;">Event Title</div>
                    <div style="font-size: 18px; font-weight: 700; color: var(--text-dark);"><i class="ph-fill ph-ticket"></i> <?= htmlspecialchars($reg['event_name']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px;">Date</div>
                    <div style="font-size: 16px; color: var(--text-dark);"><i class="ph ph-calendar-blank"></i> <?= htmlspecialchars($reg['event_date']) ?></div>
                </div>
                <div>
                    <div style="font-size: 12px; font-weight: 700; color: #94A3B8; text-transform: uppercase; margin-bottom: 4px;">Location</div>
                    <div style="font-size: 16px; color: var(--text-dark);"><i class="ph ph-map-pin"></i> <?= htmlspecialchars($reg['venue']) ?></div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 32px;" data-html2canvas-ignore="true">
            <button onclick="downloadReceipt()" class="btn btn-primary" style="margin-right: 8px;"><i class="ph ph-download-simple"></i> Download PDF</button>
            <a href="dashboard.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadReceipt() {
        var element = document.getElementById('receiptCard');
        var opt = {
            margin:       0.5,
            filename:     'Ticket_Receipt_<?= htmlspecialchars($reg['fname'] ?? 'User') ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }

    <?php if (isset($_GET['download']) && $_GET['download'] == 1 && $reg): ?>
    window.addEventListener('load', function() {
        setTimeout(downloadReceipt, 500); // Wait for fonts to load
    });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
