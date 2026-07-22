<?php
/**
 * Admin — History (Flat List)
 */
$currentPage = 'history';
$pageTitle   = 'History';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';

$db   = new Database();
$conn = $db->connect();

// Fetch all registrations across all events, latest first
$regs = $conn->query("
    SELECT r.reg_id, r.registered_date, r.registered_time, r.updated_at, r.status,
           u.fname, u.lname, u.email,
           e.event_name, e.icon, e.is_archived
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    JOIN users u ON r.user_id = u.user_id
    ORDER BY r.reg_id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header_admin.php';
?>

<h1 class="page-title">History</h1>
<p class="page-subtitle">Complete log of all registrations across all events</p>

<?php if (empty($regs)): ?>
    <div class="empty-state">
        <div class="empty-icon">📜</div>
        <p>No registration history yet.</p>
    </div>
<?php else: ?>
    <div class="event-section fade-in-up">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 20%;">Name</th>
                        <th style="width: 25%;">Event</th>
                        <th style="width: 20%;">Registered</th>
                        <th style="width: 15%;">Last Updated</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $totalCount = count($regs);
                foreach ($regs as $i => $r): 
                    $rowNum = $totalCount - $i;
                ?>
                    <tr>
                        <td><?= $rowNum ?></td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($r['email']) ?></div>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['icon']) ?> <?= htmlspecialchars($r['event_name']) ?>
                            <?php if ($r['is_archived']): ?>
                                <span class="badge badge-rejected" style="margin-left: 4px; padding: 2px 6px; font-size: 10px;">Archived</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['registered_date'] . ' ' . $r['registered_time']) ?></td>
                        <td><?= htmlspecialchars($r['updated_at']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'approved'): ?>
                                <span class="badge badge-approved" style="background:rgba(21,128,61,0.1);color:#15803d;border:1px solid #bbf7d0;">Registered</span>
                            <?php else: ?>
                                <span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
