<?php
/**
 * Admin — Registration Scanner
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registration Scanner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #FF9F80;
            --primary-light: #FFDFD4;
            --bg: #FAFAFA;
            --text-main: #1E293B;
            --text-sub: #94A3B8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            background-color: #1A1A1A; /* Top dark notch background */
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }
        .app-container {
            width: 100%;
            max-width: 480px; /* Mobile width constraint for desktop viewing */
            background-color: var(--bg);
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            margin-top: 40px; /* Space for the "notch" */
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 -10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            min-height: calc(100vh - 40px);
        }
        
        /* Simulated Notch / Top handle */
        .notch-handle {
            width: 40px;
            height: 4px;
            background: #E2E8F0;
            border-radius: 4px;
            margin: 16px auto;
        }

        .header {
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-spacer { width: 44px; } /* To center the title if needed */

        .exit-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.2s;
            background: white;
        }
        .exit-btn:hover { background: var(--primary-light); }

        .title-section {
            text-align: center;
            padding: 20px 30px;
        }
        .title-section h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
        }
        .title-section p {
            font-size: 13px;
            color: var(--text-sub);
            line-height: 1.5;
            max-width: 280px;
            margin: 0 auto;
        }

        .scanner-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        /* html5-qrcode container overrides */
        #reader {
            width: 100%;
            max-width: 320px;
            border-radius: 24px;
            overflow: hidden;
            border: none !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            background: white;
        }
        #reader__scan_region {
            background: white;
        }
        #reader video {
            border-radius: 24px;
            object-fit: cover;
        }
        #reader__dashboard_section_csr span { color: var(--text-main) !important; font-weight: 600; }
        #reader__dashboard_section_swaplink { color: var(--primary) !important; text-decoration: none; font-weight: 600;}
        #reader button {
            background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; margin: 8px; width: 80%;
        }

        .scanning-text {
            color: var(--text-sub);
            font-size: 14px;
            font-weight: 600;
            margin-top: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .bottom-actions {
            padding: 30px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            background: white;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.02);
            margin-top: auto;
        }

        .icon-row {
            display: flex;
            gap: 32px;
            color: var(--text-sub);
        }
        .icon-row i { font-size: 24px; cursor: pointer; transition: 0.2s; }
        .icon-row i:hover { color: var(--primary); }

        .main-btn {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(255, 159, 128, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        /* Overlay */
        .overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
        }
        .overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .overlay-card {
            background: white;
            padding: 40px 24px;
            border-radius: 32px;
            width: 85%;
            text-align: center;
            transform: translateY(50px) scale(0.9);
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        .overlay.active .overlay-card {
            transform: translateY(0) scale(1);
        }

        .overlay-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .success .overlay-icon { color: #10B981; }
        .error .overlay-icon { color: #EF4444; }
        
        .overlay-name {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .overlay-msg {
            font-size: 15px;
            color: var(--text-sub);
            line-height: 1.4;
        }

    </style>
</head>
<body>

    <div class="app-container">
        <div class="notch-handle"></div>
        
        <div class="header">
            <div class="header-spacer"></div>
            <a href="dashboard.php" class="exit-btn" title="Exit Scanner">
                <i class="ph-bold ph-sign-out" style="font-size: 24px;"></i>
            </a>
        </div>

        <div class="title-section">
            <h1>Registration Scanner</h1>
            <p>Place qr code inside the frame to scan please avoid shake to get results quickly.</p>
        </div>

        <div class="scanner-wrapper">
            <div id="reader"></div>
            <div class="scanning-text">
                <i class="ph-bold ph-spinner-gap ph-spin" style="color: var(--primary); font-size: 18px;"></i> 
                Scanning Code...
            </div>
        </div>
        
        <div class="bottom-actions">
            <div class="icon-row">
                <i class="ph-bold ph-image"></i>
                <i class="ph-bold ph-barcode"></i>
                <i class="ph-bold ph-lightning"></i>
            </div>
            <button class="main-btn">
                <i class="ph-bold ph-camera"></i> Scanning Active
            </button>
        </div>

        <!-- Result Overlay -->
        <div id="overlay" class="overlay">
            <div class="overlay-card" id="overlay-card">
                <i id="overlay-icon" class="ph-fill ph-check-circle overlay-icon"></i>
                <div id="overlay-name" class="overlay-name">John Doe</div>
                <div id="overlay-msg" class="overlay-msg">Welcome to the Event!</div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    const actx = new AudioContext();

    function playDing() {
        if (actx.state === 'suspended') actx.resume();
        const osc = actx.createOscillator();
        const gain = actx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(1046.50, actx.currentTime);
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
        const overlayCard = document.getElementById('overlay-card');
        const overlayIcon = document.getElementById('overlay-icon');
        const overlayName = document.getElementById('overlay-name');
        const overlayMsg = document.getElementById('overlay-msg');

        function showOverlay(type, name, msg) {
            overlay.classList.add('active');
            overlayCard.className = 'overlay-card ' + type;
            overlayIcon.className = type === 'success' ? 'ph-fill ph-check-circle overlay-icon' : 'ph-fill ph-warning-circle overlay-icon';
            overlayName.textContent = name;
            overlayMsg.textContent = msg;
            
            if (type === 'success') {
                playDing();
            } else {
                playBuzzer();
            }

            setTimeout(() => {
                overlay.classList.remove('active');
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

        html5QrcodeScanner.render(onScanSuccess, (err) => {});
        
        document.body.addEventListener('click', () => {
            if (actx.state === 'suspended') actx.resume();
        }, {once:true});
    });
    </script>
</body>
</html>
