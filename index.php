<?php
session_start();

// If admin is logged in, go to dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/dashboard.php');
    exit;
}

// Otherwise, force admin login
header('Location: admin/login.php');
exit;
?>

// Already logged in as admin?
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin/dashboard.php');
    exit;
}
// Fetch upcoming active events for the showcase
require_once __DIR__ . '/config/database.php';
$events = [];
try {
    $db = new Database();
    $conn = $db->connect();
    // Only get events that are active and not archived and not in the past
    $stmt = $conn->query("SELECT * FROM events WHERE is_active = 1 AND is_archived = 0 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 6");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    // silently fail for landing page showcase
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management — Premium Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --primary: #F5A623;
            --primary-hover: #E49312;
            --secondary: #2563EB;
            --text-dark: #1E293B;
            --text-light: #64748B;
            --bg: #F8FAFC;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg); color: var(--text-dark); overflow-x: hidden; }
        
        /* Navbar */
        nav { display: flex; justify-content: space-between; align-items: center; padding: 24px 5%; position: relative; z-index: 10; }
        .logo { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 22px; color: var(--text-dark); text-decoration: none; }
        .nav-links { display: flex; gap: 16px; align-items: center; }
        .btn { padding: 12px 24px; border-radius: 999px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline { color: var(--text-dark); background: transparent; }
        .btn-outline:hover { background: rgba(0,0,0,0.05); }
        .btn-primary { background: var(--text-dark); color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .btn-accent { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(245,166,35,0.3); }
        .btn-accent:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(245,166,35,0.4); }

        /* Hero Section */
        .hero { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 100px); padding: 40px 5%; position: relative; text-align: center; }
        .hero-content { flex: 1; max-width: 800px; animation: slideUp 0.8s ease-out forwards; display: flex; flex-direction: column; align-items: center; }
        
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(37,99,235,0.1); color: var(--secondary); font-size: 13px; font-weight: 700; border-radius: 999px; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .hero h1 { font-size: 64px; font-weight: 800; line-height: 1.1; margin-bottom: 24px; color: var(--text-dark); letter-spacing: -1.5px; }
        .hero h1 span { color: var(--primary); }
        
        .hero p { font-size: 18px; color: var(--text-light); line-height: 1.6; margin-bottom: 40px; font-weight: 400; }
        
        .cta-group { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; }

        /* Hero Image */
        .hero-image { flex: 1; display: flex; justify-content: center; align-items: center; position: relative; animation: fadeIn 1s ease-out 0.3s forwards; opacity: 0; }
        .hero-image img { max-width: 120%; height: auto; z-index: 2; animation: float 6s ease-in-out infinite; border-radius: 40px; mix-blend-mode: multiply; }
        
        /* Background Blobs */
        .blob-1 { position: absolute; width: 600px; height: 600px; background: rgba(245,166,35,0.15); border-radius: 50%; filter: blur(80px); top: -200px; right: -100px; z-index: 0; }
        .blob-2 { position: absolute; width: 500px; height: 500px; background: rgba(37,99,235,0.1); border-radius: 50%; filter: blur(80px); bottom: -100px; left: -200px; z-index: 0; }

        /* Animations */
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-20px); } 100% { transform: translateY(0px); } }

        /* Showcase Section */
        .showcase { padding: 80px 5%; background: white; position: relative; z-index: 5; }
        .showcase h2 { text-align: center; font-size: 36px; font-weight: 800; color: var(--text-dark); margin-bottom: 16px; }
        .showcase p.sub { text-align: center; color: var(--text-light); font-size: 16px; margin-bottom: 48px; max-width: 600px; margin-inline: auto; }
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px; max-width: 1200px; margin: 0 auto; }
        .event-card { background: white; border-radius: 24px; padding: 32px; border: 1px solid #F1F5F9; box-shadow: 0 10px 40px rgba(0,0,0,0.03); transition: all 0.3s; display: flex; flex-direction: column; }
        .event-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .ec-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .ec-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .ec-title { font-size: 20px; font-weight: 700; color: var(--text-dark); line-height: 1.2; }
        .ec-desc { color: var(--text-light); font-size: 15px; line-height: 1.5; margin-bottom: 24px; flex-grow: 1; }
        .ec-meta { display: flex; flex-direction: column; gap: 8px; font-size: 14px; color: var(--text-muted); margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #F1F5F9; }
        .ec-meta-item { display: flex; align-items: center; gap: 8px; }

        /* Responsive */
        @media (max-width: 992px) {
            .hero { flex-direction: column; text-align: center; padding-top: 60px; }
            .hero-content { max-width: 100%; margin-bottom: 60px; display: flex; flex-direction: column; align-items: center; }
            .hero h1 { font-size: 48px; }
            .hero-image img { max-width: 100%; }
            .blob-1, .blob-2 { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="blob-1"></div>
    <div class="blob-2"></div>

    <nav>
        <a href="#" class="logo">
            <i class="ph-fill ph-ticket" style="color: var(--primary); font-size: 32px;"></i> Event Management
        </a>
        <div class="nav-links">
            <a href="admin/login.php" class="btn btn-primary"><i class="ph ph-lock-key"></i> Admin Portal</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <div class="badge"><i class="ph-fill ph-lightning"></i> Next-Generation Platform</div>
            <h1>Elevate your events with <span>Event Management</span></h1>
            <p>The all-in-one platform to create, manage, and discover incredible experiences. Seamlessly handle registrations, ticketing, and analytics in one beautiful workspace.</p>
            <div class="cta-group">
                <a href="admin/login.php" class="btn btn-accent"><i class="ph ph-rocket-launch"></i> Get Started as Admin</a>
                <a href="user/events.php" class="btn btn-outline" style="background: white; border: 1px solid #E2E8F0;"><i class="ph ph-magnifying-glass"></i> Browse Events</a>
            </div>
        </div>
    </section>

    <section class="showcase" id="events">
        <h2>Upcoming Events</h2>
        <p class="sub">Discover and register for amazing events happening soon. Spots are limited!</p>
        
        <?php if (empty($events)): ?>
            <div style="text-align: center; color: var(--text-light); padding: 40px;">
                <i class="ph ph-calendar-blank" style="font-size: 48px; color: #CBD5E1; margin-bottom: 16px;"></i>
                <p>Check back later for exciting new events!</p>
            </div>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($events as $ev): ?>
                    <div class="event-card" style="border-top: 4px solid <?= htmlspecialchars($ev['color'] ?? '#667eea') ?>;">
                        <div class="ec-header">
                            <div class="ec-icon" style="background: <?= htmlspecialchars($ev['color'] ?? '#667eea') ?>20; color: <?= htmlspecialchars($ev['color'] ?? '#667eea') ?>;">
                                <i class="ph-fill ph-ticket"></i>
                            </div>
                            <h3 class="ec-title"><?= htmlspecialchars($ev['event_name']) ?></h3>
                        </div>
                        <p class="ec-desc"><?= htmlspecialchars($ev['description'] ?? 'No description provided.') ?></p>
                        <div class="ec-meta">
                            <div class="ec-meta-item">
                                <i class="ph-fill ph-calendar-blank" style="color: var(--primary);"></i> 
                                <strong><?= date('M j, Y', strtotime($ev['event_date'])) ?></strong>
                            </div>
                            <div class="ec-meta-item">
                                <i class="ph-fill ph-map-pin" style="color: #EF4444;"></i> 
                                <?= htmlspecialchars($ev['venue'] ?? 'TBA') ?>
                            </div>
                        </div>
                        <a href="user/register.php" class="btn btn-outline" style="width: 100%; justify-content: center; background: #F8FAFC; border: 1px solid #E2E8F0;">
                            Register Now <i class="ph ph-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>