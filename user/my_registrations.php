<?php
/**
 * User — My Registrations
 * Shows all approved and rejected (and pending) registrations for the logged-in user.
 */
$currentPage = 'registrations';
$pageTitle   = 'My Registrations';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header_user.php';

$db     = new Database();
$conn   = $db->connect();
$userId = $_SESSION['user_id'];

// Fetch all user registrations
$regs = $conn->prepare("
    SELECT r.*, e.event_name, e.icon, e.event_date, e.venue, e.color, e.description
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    WHERE r.user_id = ?
    ORDER BY r.registered_date DESC, r.registered_time DESC
");
$regs->execute([$userId]);
$registrations = $regs->fetchAll();

// Fetch answers
$allAnswers = [];
if (!empty($registrations)) {
    $regIds = array_column($registrations, 'reg_id');
    $placeholders = implode(',', array_fill(0, count($regIds), '?'));
    $ansStmt = $conn->prepare("
        SELECT ra.reg_id, er.requirement_text, ra.answer_text
        FROM registration_answers ra
        JOIN event_requirements er ON ra.req_id = er.req_id
        WHERE ra.reg_id IN ($placeholders)
        ORDER BY er.sort_order
    ");
    $ansStmt->execute($regIds);
    foreach ($ansStmt->fetchAll() as $a) {
        $allAnswers[$a['reg_id']][] = $a;
    }
}

// Count by status
$statusCounts = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'waitlisted' => 0
];
foreach ($registrations as $r) {
    $statusCounts[$r['status']]++;
}
?>

<h1 class="page-title">My Registrations</h1>
<p class="page-subtitle">Track all your event registrations and their approval status</p>

<!-- Summary Cards -->
<div class="mobile-grid-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px;">
    <div style="background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <div style="font-size: 24px; margin-bottom: 12px; background: rgba(245,166,35,0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #f39c12;"><i class="ph-fill ph-hourglass-medium"></i></div>
        <div style="font-size: 32px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 4px;"><?= $statusCounts['pending'] ?></div>
        <div style="font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase;">Pending</div>
    </div>
    <div style="background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <div style="font-size: 24px; margin-bottom: 12px; background: rgba(16,185,129,0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10b981;"><i class="ph-fill ph-check-circle"></i></div>
        <div style="font-size: 32px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 4px;"><?= $statusCounts['approved'] ?></div>
        <div style="font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase;">Approved</div>
    </div>
    <div style="background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <div style="font-size: 24px; margin-bottom: 12px; background: rgba(239,68,68,0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #ef4444;"><i class="ph-fill ph-x-circle"></i></div>
        <div style="font-size: 32px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 4px;"><?= $statusCounts['rejected'] ?></div>
        <div style="font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase;">Rejected</div>
    </div>
    <div style="background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); border: 1px solid var(--border);">
        <div style="font-size: 24px; margin-bottom: 12px; background: rgba(99,102,241,0.1); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #6366f1;"><i class="ph-fill ph-clock"></i></div>
        <div style="font-size: 32px; font-weight: 800; color: var(--text-dark); line-height: 1; margin-bottom: 4px;"><?= $statusCounts['waitlisted'] ?></div>
        <div style="font-size: 13px; font-weight: 600; color: var(--text-light); text-transform: uppercase;">Waitlisted</div>
    </div>
</div>

<?php if (empty($registrations)): ?>
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <p>You haven't registered for any events yet.</p>
        <a href="events.php" class="btn btn-primary" style="margin-top:16px;">Browse Events →</a>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
    <?php foreach ($registrations as $r): ?>
        <div style="background: white; border-radius: 20px; padding: 24px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.03); border-left: 4px solid <?= htmlspecialchars($r['color'] ?? 'var(--accent)') ?>;">
            <div class="mobile-col" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <h3 style="font-size: 18px; font-weight: 700; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 8px;">
                    <div style="background: rgba(245,166,35,0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                        <?= $r['icon'] ?: '🎟️' ?>
                    </div>
                    <?= htmlspecialchars($r['event_name']) ?>
                </h3>
                <?php 
                    $status = $r['status'];
                    $statusColor = $status === 'pending' ? '#B45309' : ($status === 'approved' ? '#065F46' : '#B91C1C');
                    $statusBg = $status === 'pending' ? '#FEF3C7' : ($status === 'approved' ? '#D1FAE5' : '#FEE2E2');
                    if ($status === 'waitlisted') {
                        $statusColor = '#4B5563'; $statusBg = '#F3F4F6';
                    }
                ?>
                <span style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                    <?= ucfirst($status) ?>
                </span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; background: #F8FAFC; padding: 12px; border-radius: 12px;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-light);">
                    <i class="ph ph-calendar-blank" style="color: var(--accent);"></i> <?= htmlspecialchars($r['event_date'] ?? 'TBD') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-light);">
                    <i class="ph ph-map-pin" style="color: #3B82F6;"></i> <?= htmlspecialchars($r['venue'] ?? 'TBD') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-muted);">
                    <i class="ph ph-clock"></i> Registered: <?= htmlspecialchars($r['registered_date'] . ' ' . $r['registered_time']) ?>
                </div>
            </div>

            <?php if (!empty($allAnswers[$r['reg_id']])): ?>
                <div style="margin-top:8px;padding-top:16px;border-top:1px dashed var(--border);">
                    <p style="font-size:11px;font-weight:700;color:var(--text-light);margin-bottom:8px;text-transform:uppercase;">Your Answers</p>
                    <?php foreach ($allAnswers[$r['reg_id']] as $a): ?>
                        <div style="margin-bottom:8px;font-size:13px; background: #F8FAFC; padding: 8px 12px; border-radius: 8px;">
                            <span style="display:block;color:var(--text-light);font-size:11px;margin-bottom:2px;"><?= htmlspecialchars($a['requirement_text']) ?></span>
                            <span style="color:var(--text-dark);font-weight:500;"><?= htmlspecialchars($a['answer_text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($r['status'] === 'approved'): ?>
                <div class="alert alert-success" style="margin-top:12px;margin-bottom:0;">
                    🎉 Your registration has been approved! See you at the event.
                </div>
            <?php elseif ($r['status'] === 'rejected'): ?>
                <div class="alert alert-error" style="margin-top:12px;margin-bottom:0;">
                    Your registration was not approved. Feel free to register for other events.
                </div>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-top:12px;margin-bottom:0;">
                    ⏳ Awaiting admin review. You'll receive an email notification.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
