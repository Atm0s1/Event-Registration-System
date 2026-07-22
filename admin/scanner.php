<?php
/**
 * Admin — QR Code Scanner
 */
$currentPage = 'dashboard';
$pageTitle   = 'Ticket Scanner';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';

// Handle AJAX scan request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_token'])) {
    header('Content-Type: application/json');
    $token = trim($_POST['qr_token']);
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("
            SELECT r.reg_id, r.status, r.attendance_status, u.fname, u.lname, e.event_name 
            FROM registrations r 
            JOIN users u ON r.user_id = u.user_id 
            JOIN events e ON r.event_id = e.event_id 
            WHERE r.qr_token = ?
        ");
        $stmt->execute([$token]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Invalid QR Code. No ticket found.']);
            exit;
        }
        
        if ($ticket['status'] !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'This ticket is not approved. Status: ' . ucfirst($ticket['status'])]);
            exit;
        }
        
        if ($ticket['attendance_status'] === 'present') {
            echo json_encode(['success' => false, 'message' => 'Ticket already scanned! Attendee is already marked Present.']);
            exit;
        }
        
        // Mark as present
        $conn->prepare("UPDATE registrations SET attendance_status = 'present' WHERE reg_id = ?")->execute([$ticket['reg_id']]);
        
        $name = htmlspecialchars($ticket['fname'] . ' ' . $ticket['lname']);
        $event = htmlspecialchars($ticket['event_name']);
        
        echo json_encode(['success' => true, 'message' => "Successfully checked in {$name} for {$event}!"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <h1 class="page-title">Check-In Scanner</h1>
    <p class="page-subtitle">Scan attendee QR tickets using your device camera.</p>

    <div style="background: white; padding: 24px; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.03); border: 1px solid #F1F5F9; text-align: center;">
        
        <div id="reader" style="width: 100%; border-radius: 16px; overflow: hidden; border: 2px dashed #E2E8F0; margin-bottom: 24px; min-height: 300px; background: #F8FAFC;"></div>
        
        <div id="scan-result" style="display: none; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;"></div>
        
        <button id="restart-btn" class="btn btn-outline" style="display: none; margin: 0 auto;">
            <i class="ph ph-arrows-clockwise"></i> Scan Another Ticket
        </button>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        { fps: 10, qrbox: {width: 250, height: 250} },
        /* verbose= */ false);

    const resultDiv = document.getElementById('scan-result');
    const restartBtn = document.getElementById('restart-btn');
    let isScanning = true;

    function onScanSuccess(decodedText, decodedResult) {
        if (!isScanning) return;
        isScanning = false;
        
        // Pause scanner UI
        html5QrcodeScanner.pause();
        
        resultDiv.style.display = 'block';
        resultDiv.style.background = '#FEF3C7';
        resultDiv.style.color = '#D97706';
        resultDiv.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Verifying ticket...';
        
        // Send AJAX request
        const formData = new FormData();
        formData.append('qr_token', decodedText);
        
        fetch('scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.style.background = '#ECFDF5';
                resultDiv.style.color = '#059669';
                resultDiv.innerHTML = `<i class="ph-fill ph-check-circle" style="font-size:24px; vertical-align:middle; margin-right:8px;"></i> ${data.message}`;
            } else {
                resultDiv.style.background = '#FEF2F2';
                resultDiv.style.color = '#DC2626';
                resultDiv.innerHTML = `<i class="ph-fill ph-warning-circle" style="font-size:24px; vertical-align:middle; margin-right:8px;"></i> ${data.message}`;
            }
            restartBtn.style.display = 'inline-flex';
        })
        .catch(err => {
            resultDiv.style.background = '#FEF2F2';
            resultDiv.style.color = '#DC2626';
            resultDiv.innerHTML = `<i class="ph-fill ph-warning-circle"></i> Network error occurred.`;
            restartBtn.style.display = 'inline-flex';
        });
    }

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning
    }

    html5QrcodeScanner.render(onScanSuccess, onScanFailure);

    restartBtn.addEventListener('click', () => {
        resultDiv.style.display = 'none';
        restartBtn.style.display = 'none';
        isScanning = true;
        html5QrcodeScanner.resume();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
