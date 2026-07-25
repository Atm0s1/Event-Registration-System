<?php
/**
 * Admin Dashboard — VidConf UI with real data
 */
$currentPage = 'dashboard';
$pageTitle   = 'Dashboard';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';
$db   = new Database();
$conn = $db->connect();

// Stats
$totalEvents   = $conn->query("SELECT COUNT(*) FROM events WHERE is_active = 1")->fetchColumn();
$totalUsers    = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$approvedCount = $conn->query("SELECT COUNT(*) FROM registrations WHERE status = 'approved'")->fetchColumn();

// Upcoming
$upcomingEvents = $conn->query("
    SELECT event_name, event_date, event_time, venue, icon 
    FROM events 
    WHERE is_active = 1 AND event_date >= CURDATE() 
    ORDER BY event_date ASC LIMIT 4
")->fetchAll();

require_once __DIR__ . '/../includes/header_admin.php';
?>
<div class="dash-layout">

    <!-- ════ LEFT COLUMN ════ -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 20px; font-weight: 700; color: var(--text-dark);">Overview</h2>
        </div>
        
        <!-- 4 Stat Cards (2×2) -->
        <div class="stat-grid">
            <div class="stat-card blue">
                <div class="stat-label">Events Created</div>
                <div class="stat-value"><?= number_format($totalEvents) ?></div>
            </div>
            <div class="stat-card orange">
                <div class="stat-label">Total Participants</div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="stat-card red">
                <div class="stat-label">Approved Participants</div>
                <div class="stat-value"><?= number_format($approvedCount) ?></div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="upcoming-card">
            <div class="card-header">
                <h3>Your Upcoming Event</h3>
                <span class="filter-btn">filter by Date : All ▾</span>
            </div>

            <?php if (empty($upcomingEvents)): ?>
                <p style="color: var(--text-light); font-size: 14px;">No upcoming events.</p>
            <?php else: ?>
                <?php 
                    $dots = ['coral','blue','green','purple'];
                    foreach ($upcomingEvents as $i => $ev): 
                ?>
                <div class="event-item">
                    <div class="event-dot <?= $dots[$i % 4] ?>"></div>
                    <div class="event-info">
                        <h4><?= htmlspecialchars($ev['event_name']) ?></h4>
                        <p><?= htmlspecialchars($ev['event_date'] ?? 'TBD') ?> <?= !empty($ev['event_time']) ? '• ' . date('g:i A', strtotime($ev['event_time'])) : '' ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
