<?php
/**
 * User — Browse Available Events
 */
$currentPage = 'events';
$pageTitle   = 'Available Events';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header_user.php';

$db   = new Database();
$conn = $db->connect();
$userId = $_SESSION['user_id'];

// Get active events
$events = $conn->query("SELECT * FROM events WHERE is_active = 1 AND is_archived = 0 ORDER BY event_date ASC")->fetchAll();

// Get user's existing registrations to check duplicates
$userRegs = [];
$stmt = $conn->prepare("SELECT event_id, status FROM registrations WHERE user_id = ?");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $r) {
    $userRegs[$r['event_id']] = $r['status'];
}

// Fetch requirements per event
$allReqs = [];
$reqRows = $conn->query("SELECT * FROM event_requirements ORDER BY event_id, sort_order")->fetchAll();
foreach ($reqRows as $r) {
    $allReqs[$r['event_id']][] = $r;
}
?>

<h1 class="page-title">Available Events</h1>
<p class="page-subtitle">Browse and register for exciting events, <?= htmlspecialchars($_SESSION['user_name']) ?> 🎉</p>

<?php if (empty($events)): ?>
    <div class="empty-state">
        <div class="empty-icon">🎪</div>
        <p style="color: var(--text-light);">No events available at the moment. Check back later!</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
    <?php foreach ($events as $ev): ?>
        <div style="background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.03); display: flex; flex-direction: column; position: relative; overflow: hidden;">
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: <?= htmlspecialchars($ev['color'] ?? 'var(--accent)') ?>;"></div>
            
            <div style="width: 48px; height: 48px; background: rgba(245,166,35,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
                <?= $ev['icon'] ?: '🎟️' ?>
            </div>
            
            <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px; color: var(--text-dark);"><?= htmlspecialchars($ev['event_name']) ?></h3>
            <p style="color: var(--text-light); font-size: 14px; margin-bottom: 24px; line-height: 1.5; flex: 1;"><?= htmlspecialchars($ev['description']) ?></p>

            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; background: #F8FAFC; padding: 16px; border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-light);">
                    <i class="ph ph-calendar-blank" style="font-size: 16px; color: var(--accent);"></i> <?= htmlspecialchars($ev['event_date'] ?? 'To be announced') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-light);">
                    <i class="ph ph-map-pin" style="font-size: 16px; color: #3B82F6;"></i> <?= htmlspecialchars($ev['venue'] ?? 'To be announced') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-light);">
                    <i class="ph ph-user" style="font-size: 16px; color: #10B981;"></i> Min Age: <?= $ev['min_age'] ?>+
                </div>
            </div>

            <?php if (isset($userRegs[$ev['event_id']])): ?>
                <?php 
                    $status = $userRegs[$ev['event_id']];
                    $statusColor = $status === 'pending' ? '#B45309' : ($status === 'approved' ? '#065F46' : '#B91C1C');
                    $statusBg = $status === 'pending' ? '#FEF3C7' : ($status === 'approved' ? '#D1FAE5' : '#FEE2E2');
                    $statusIcon = $status === 'pending' ? 'ph-hourglass' : ($status === 'approved' ? 'ph-check-circle' : 'ph-x-circle');
                    $statusText = $status === 'pending' ? 'Pending Approval' : ($status === 'approved' ? 'Approved' : 'Rejected');
                    if ($status === 'waitlisted') {
                        $statusColor = '#4B5563'; $statusBg = '#F3F4F6'; $statusIcon = 'ph-clock'; $statusText = 'Waitlisted';
                    }
                ?>
                <div style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 12px; border-radius: 10px; text-align: center; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="ph <?= $statusIcon ?>"></i> <?= $statusText ?>
                </div>
            <?php else: ?>
                <a href="register_event.php?event_id=<?= $ev['event_id'] ?>" class="btn btn-primary" style="width: 100%; justify-content: center; background: var(--text-dark); color: white;">Register Now <i class="ph ph-arrow-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
