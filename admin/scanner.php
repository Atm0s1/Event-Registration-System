<?php
/**
 * Admin — QR Code Kiosk Scanner
 */
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Handle AJAX scan request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_token'])) {
    header('Content-Type: application/json');
    $token = trim($_POST['qr_token']);
    
    // The QR code contains a full URL (e.g. http://.../scan.php?token=XYZ)
    // We need to extract just the token parameter.
    if (filter_var($token, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($token);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            if (isset($query['token'])) {
                $token = $query['token'];
            }
        }
    }
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
            echo json_encode(['success' => false, 'message' => 'Ticket is not approved.']);
            exit;
        }
        
        if ($ticket['attendance_status'] === 'present') {
            echo json_encode(['success' => false, 'message' => 'Already Scanned!']);
            exit;
        }
        
        // Mark as present
        $conn->prepare("UPDATE registrations SET attendance_status = 'present' WHERE reg_id = ?")->execute([$ticket['reg_id']]);
        
        $name = htmlspecialchars($ticket['fname'] . ' ' . $ticket['lname']);
        $event = htmlspecialchars($ticket['event_name']);
        
        echo json_encode(['success' => true, 'name' => $name, 'message' => "Welcome to {$event}!"]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System error.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Scanner — Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            background-color: #0F172A; /* Deep dark blue */
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
        .header {
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #F8FAFC;
        }
        .logo i { color: #38BDF8; font-size: 32px; }
        .exit-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .exit-btn:hover { background: rgba(239, 68, 68, 0.2); border-color: #EF4444; color: #FCA5A5; }

        .scanner-container {
            width: 100%;
            max-width: 500px;
            background: #1E293B;
            border-radius: 32px;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            position: relative;
            z-index: 10;
        }
        .scanner-title {
            text-align: center;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 600;
            color: #94A3B8;
        }
        
        #reader {
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
            border: 2px solid #334155;
        }
        #reader video {
            border-radius: 20px;
        }
        
        /* The massive overlay */
        .overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: scale(0.9);
        }
        .overlay.active {
            opacity: 1;
            transform: scale(1);
        }
        .overlay.success {
            background: rgba(16, 185, 129, 0.95);
            backdrop-filter: blur(10px);
        }
        .overlay.error {
            background: rgba(239, 68, 68, 0.95);
            backdrop-filter: blur(10px);
        }
        .overlay-icon {
            font-size: 120px;
            margin-bottom: 24px;
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .overlay-name {
            font-size: 64px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 16px;
            line-height: 1.1;
        }
        .overlay-msg {
            font-size: 24px;
            font-weight: 400;
            opacity: 0.9;
            text-align: center;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); }
        }

        /* html5-qrcode overrides to make it look clean */
        #reader__dashboard_section_csr span { color: white !important; }
        #reader__dashboard_section_swaplink { color: #38BDF8 !important; text-decoration: none; }
        #reader button {
            background: #38BDF8; color: #0F172A; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; margin: 4px;
        }
        
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">
            <i class="ph-fill ph-scan"></i>
            Kiosk Mode
        </div>
        <a href="dashboard.php" class="exit-btn">
            <i class="ph-bold ph-x"></i> Exit Kiosk
        </a>
    </div>

    <div class="scanner-container">
        <div class="scanner-title">Please show your QR ticket to the camera</div>
        <div id="reader"></div>
    </div>

    <!-- Success/Error Overlay -->
    <div id="overlay" class="overlay">
        <i id="overlay-icon" class="ph-fill ph-check-circle overlay-icon"></i>
        <div id="overlay-name" class="overlay-name">John Doe</div>
        <div id="overlay-msg" class="overlay-msg">Welcome to the Event!</div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
    // Audio Synthesizers for Instant Zero-Latency Sound
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    const actx = new AudioContext();

    function playDing() {
        if (actx.state === 'suspended') actx.resume();
        const osc = actx.createOscillator();
        const gain = actx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1046.50, actx.currentTime); // C6
        osc.frequency.exponentialRampToValueAtTime(523.25, actx.currentTime + 0.6);
        gain.gain.setValueAtTime(0.5, actx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, actx.currentTime + 0.6);
        osc.connect(gain);
        gain.connect(actx.destination);
        osc.start();
        osc.stop(actx.currentTime + 0.6);
    }

    function playBuzzer() {
        if (actx.state === 'suspended') actx.resume();
        const osc = actx.createOscillator();
        const gain = actx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(150, actx.currentTime);
        osc.frequency.setValueAtTime(100, actx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.3, actx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, actx.currentTime + 0.4);
        osc.connect(gain);
        gain.connect(actx.destination);
        osc.start();
        osc.stop(actx.currentTime + 0.4);
    }

    document.addEventListener('DOMContentLoaded', function() {
        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            { fps: 15, qrbox: {width: 250, height: 250} },
            /* verbose= */ false);

        let isScanning = true;
        const overlay = document.getElementById('overlay');
        const overlayIcon = document.getElementById('overlay-icon');
        const overlayName = document.getElementById('overlay-name');
        const overlayMsg = document.getElementById('overlay-msg');

        function showOverlay(type, name, msg) {
            overlay.className = 'overlay active ' + type;
            overlayIcon.className = type === 'success' ? 'ph-fill ph-check-circle overlay-icon' : 'ph-fill ph-warning-circle overlay-icon';
            overlayName.textContent = name;
            overlayMsg.textContent = msg;
            
            if (type === 'success') {
                playDing();
            } else {
                playBuzzer();
            }

            // Auto resume after 3 seconds
            setTimeout(() => {
                overlay.className = 'overlay';
                isScanning = true;
            }, 3000);
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (!isScanning) return;
            isScanning = false;
            
            const formData = new FormData();
            formData.append('qr_token', decodedText);
            
            fetch('scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOverlay('success', data.name, data.message);
                } else {
                    showOverlay('error', 'Check-In Failed', data.message);
                }
            })
            .catch(err => {
                showOverlay('error', 'Network Error', 'Could not connect to server.');
            });
        }

        // Initialize scanner
        html5QrcodeScanner.render(onScanSuccess, (err) => {});
        
        // Start Audio Context on first click (browser policy)
        document.body.addEventListener('click', () => {
            if (actx.state === 'suspended') actx.resume();
        }, {once:true});
    });
    </script>
</body>
</html>
