<?php
/**
 * Admin — Event Statistics
 * Charts and analytics for event participation
 */
$currentPage = 'statistics';
$pageTitle   = 'Event Statistics';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';
$db   = new Database();
$conn = $db->connect();

// 1. Top chart: All events ranked by total participants
$topEvents = $conn->query("
    SELECT e.event_id, e.event_name, e.icon, e.color, COUNT(r.reg_id) as total_participants
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id
    GROUP BY e.event_id
    ORDER BY total_participants DESC
")->fetchAll();

// 2. Per-event data: group by event_name to combine recurring events
$eventGroups = [];
foreach ($topEvents as $ev) {
    $name = $ev['event_name'];
    if (!isset($eventGroups[$name])) {
        $eventGroups[$name] = [
            'icon'  => $ev['icon'],
            'color' => $ev['color'],
            'ids'   => [],
            'total' => 0
        ];
    }
    $eventGroups[$name]['ids'][] = $ev['event_id'];
    $eventGroups[$name]['total'] += $ev['total_participants'];
}

// 3. For each event group, get per-occurrence data (each event_id = one occurrence)
$eventDetails = [];
foreach ($eventGroups as $name => $group) {
    $placeholders = implode(',', array_fill(0, count($group['ids']), '?'));
    
    // Get each occurrence with date and participant count
    $stmt = $conn->prepare("
        SELECT e.event_id, e.event_date, e.event_time, COUNT(r.reg_id) as participants,
               SUM(CASE WHEN r.attendance_status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM events e
        LEFT JOIN registrations r ON e.event_id = r.event_id
        WHERE e.event_id IN ($placeholders)
        GROUP BY e.event_id
        ORDER BY e.event_date ASC
    ");
    $stmt->execute($group['ids']);
    $occurrences = $stmt->fetchAll();
    
    // Get attendees for each occurrence
    $attendees = [];
    foreach ($group['ids'] as $eid) {
        $aStmt = $conn->prepare("
            SELECT u.fname, u.lname, u.email, r.attendance_status, e.event_date
            FROM registrations r
            JOIN users u ON r.user_id = u.user_id
            JOIN events e ON r.event_id = e.event_id
            WHERE r.event_id = ?
            ORDER BY u.lname ASC
        ");
        $aStmt->execute([$eid]);
        $attendees[$eid] = $aStmt->fetchAll();
    }
    
    $eventDetails[$name] = [
        'icon'        => $group['icon'],
        'color'       => $group['color'],
        'total'       => $group['total'],
        'occurrences' => $occurrences,
        'attendees'   => $attendees
    ];
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<h1 class="page-title" style="margin-bottom:4px;">Event Statistics</h1>
<p class="page-subtitle" style="margin-bottom:28px;">Visual analytics and attendance breakdown for all events</p>

<!-- ══ TOP CHART: Events by Total Participants ══ -->
<div style="background: white; border-radius: 20px; padding: 28px; border: 1px solid var(--border); margin-bottom: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
        <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(99,102,241,0.1); display:flex; align-items:center; justify-content:center;">
            <i class="ph-bold ph-chart-bar-horizontal" style="font-size: 20px; color: #6366F1;"></i>
        </div>
        <div>
            <h3 style="font-size: 17px; font-weight: 700; color: var(--text-dark); margin: 0;">Events by Total Participants</h3>
            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Ranked from most to least registered</p>
        </div>
    </div>
    <div style="position: relative; height: <?= max(count($topEvents) * 50, 150) ?>px; width: 100%;">
        <canvas id="topEventsChart"></canvas>
    </div>
</div>

<!-- ══ PER-EVENT BREAKDOWN ══ -->
<h2 style="font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 16px;"><i class="ph ph-stack" style="color: var(--accent);"></i> Event Breakdown</h2>
<p style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px;">Click any event to expand its statistics, occurrence chart, and attendee list.</p>

<?php $idx = 0; foreach ($eventDetails as $eventName => $detail): ?>
<div style="background: white; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.02); transition: box-shadow 0.3s;" 
     onmouseover="this.style.boxShadow='0 8px 30px rgba(0,0,0,0.06)'" onmouseout="this.style.boxShadow='0 2px 12px rgba(0,0,0,0.02)'">
    
    <!-- Clickable Header -->
    <div onclick="toggleEvent(<?= $idx ?>)" style="padding: 20px 24px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-left: 4px solid <?= htmlspecialchars($detail['color']) ?>;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: <?= htmlspecialchars($detail['color']) ?>15; color: <?= htmlspecialchars($detail['color']) ?>; display:flex; align-items:center; justify-content:center; font-size: 20px;">
                <i class="ph-fill ph-ticket"></i>
            </div>
            <div>
                <h3 style="font-size: 16px; font-weight: 700; color: var(--text-dark); margin: 0;"><?= htmlspecialchars($eventName) ?></h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 4px 0 0 0;">
                    <?= count($detail['occurrences']) ?> occurrence<?= count($detail['occurrences']) !== 1 ? 's' : '' ?> · <?= $detail['total'] ?> total participants
                </p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="background: <?= htmlspecialchars($detail['color']) ?>15; color: <?= htmlspecialchars($detail['color']) ?>; font-weight: 700; font-size: 18px; padding: 6px 16px; border-radius: 10px;"><?= $detail['total'] ?></span>
            <i id="chevron-<?= $idx ?>" class="ph-bold ph-caret-down" style="font-size: 18px; color: var(--text-muted); transition: transform 0.3s;"></i>
        </div>
    </div>
    
    <!-- Expandable Content -->
    <div id="event-detail-<?= $idx ?>" style="display: none; border-top: 1px solid var(--border);">
        
        <!-- Occurrence Chart -->
        <?php if (count($detail['occurrences']) > 0): ?>
        <div style="padding: 24px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-dark); margin-bottom: 16px;">
                <i class="ph ph-chart-bar" style="color: <?= htmlspecialchars($detail['color']) ?>;"></i> Participants per Occurrence
            </h4>
            <div style="position: relative; height: <?= max(count($detail['occurrences']) * 55, 100) ?>px; width: 100%;">
                <canvas id="eventChart-<?= $idx ?>"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Occurrence Details + Attendees -->
        <?php foreach ($detail['occurrences'] as $occ): ?>
        <div style="border-top: 1px solid #F1F5F9; padding: 20px 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="ph ph-calendar-blank" style="font-size: 18px; color: var(--accent);"></i>
                    <span style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($occ['event_date'] ?? 'TBD') ?></span>
                    <?php if (!empty($occ['event_time'])): ?>
                        <span style="color: var(--text-muted);">·</span>
                        <span style="color: var(--text-muted);"><?= date('g:i A', strtotime($occ['event_time'])) ?></span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; gap: 8px;">
                    <span style="background: #EEF6FF; color: #3B82F6; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 8px;">
                        <?= $occ['participants'] ?> Registered
                    </span>
                    <span style="background: #D1FAE5; color: #15803d; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 8px;">
                        <?= $occ['present_count'] ?> Present
                    </span>
                </div>
            </div>
            
            <?php 
            $attList = $detail['attendees'][$occ['event_id']] ?? [];
            if (!empty($attList)): 
            ?>
            <div class="table-wrapper" style="border-radius: 12px; overflow: hidden;">
                <table class="data-table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 35%;">Name</th>
                            <th style="width: 35%;">Email</th>
                            <th style="width: 25%;">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attList as $ai => $att): ?>
                        <tr>
                            <td><?= $ai + 1 ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($att['fname'] . ' ' . $att['lname']) ?></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($att['email']) ?></td>
                            <td>
                                <?php if ($att['attendance_status'] === 'present'): ?>
                                    <span style="background: rgba(21,128,61,0.1); color: #15803d; border: 1px solid #bbf7d0; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 8px;">✓ Present</span>
                                <?php else: ?>
                                    <span style="background: #FEF2F2; color: #EF4444; font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 8px;">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 13px; font-style: italic;">No participants registered for this occurrence.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $idx++; endforeach; ?>

<?php if (empty($eventDetails)): ?>
<div class="empty-state">
    <div class="empty-icon"><i class="ph-fill ph-chart-bar"></i></div>
    <p>No events with data yet. Create events and register participants to see statistics.</p>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Toggle expand/collapse
function toggleEvent(idx) {
    const detail = document.getElementById('event-detail-' + idx);
    const chevron = document.getElementById('chevron-' + idx);
    if (detail.style.display === 'none') {
        detail.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
        // Lazy-init chart
        initEventChart(idx);
    } else {
        detail.style.display = 'none';
        chevron.style.transform = 'rotate(0deg)';
    }
}

// ── TOP CHART ──
document.addEventListener('DOMContentLoaded', function() {
    const topCtx = document.getElementById('topEventsChart');
    if (!topCtx) return;
    
    const topLabels = <?= json_encode(array_map(function($e) { 
        return (strlen($e['event_name']) > 25) ? substr($e['event_name'], 0, 25) . '...' : $e['event_name']; 
    }, $topEvents)) ?>;
    const topData = <?= json_encode(array_map(function($e) { return (int)$e['total_participants']; }, $topEvents)) ?>;
    const topColors = <?= json_encode(array_map(function($e) { return $e['color'] ?: '#6366F1'; }, $topEvents)) ?>;
    
    new Chart(topCtx, {
        type: 'bar',
        data: {
            labels: topLabels,
            datasets: [{
                label: 'Participants',
                data: topData,
                backgroundColor: topColors.map(c => c + '90'),
                borderColor: topColors,
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 28
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1E293B',
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(ctx) { return ctx.parsed.x + ' Participants'; }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: '#94A3B8', font: { family: "'Inter', sans-serif", size: 12 } },
                    border: { display: false },
                    grid: { color: '#F1F5F9', drawTicks: false }
                },
                y: {
                    ticks: { color: '#1E293B', font: { family: "'Inter', sans-serif", size: 13, weight: '600' } },
                    border: { display: false },
                    grid: { display: false }
                }
            }
        }
    });
});

// ── PER-EVENT CHARTS (lazy loaded) ──
const chartInited = {};
const eventChartData = <?= json_encode(array_values(array_map(function($name, $detail) {
    $labels = [];
    $registered = [];
    $present = [];
    $color = $detail['color'] ?: '#6366F1';
    foreach ($detail['occurrences'] as $occ) {
        $dateLabel = $occ['event_date'] ?? 'TBD';
        if (!empty($occ['event_time'])) {
            $dateLabel .= ' ' . date('g:i A', strtotime($occ['event_time']));
        }
        $labels[] = $dateLabel;
        $registered[] = (int)$occ['participants'];
        $present[] = (int)$occ['present_count'];
    }
    return ['labels' => $labels, 'registered' => $registered, 'present' => $present, 'color' => $color];
}, array_keys($eventDetails), array_values($eventDetails)))) ?>;

function initEventChart(idx) {
    if (chartInited[idx]) return;
    chartInited[idx] = true;
    
    const canvas = document.getElementById('eventChart-' + idx);
    if (!canvas) return;
    const data = eventChartData[idx];
    if (!data) return;
    
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Registered',
                    data: data.registered,
                    backgroundColor: data.color + '60',
                    borderColor: data.color,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    barThickness: 28
                },
                {
                    label: 'Present',
                    data: data.present,
                    backgroundColor: '#10B98160',
                    borderColor: '#10B981',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                    barThickness: 28
                }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 12, boxHeight: 12, borderRadius: 3,
                        useBorderRadius: true,
                        font: { family: "'Inter', sans-serif", size: 12, weight: '500' },
                        color: '#64748B',
                        padding: 16
                    }
                },
                tooltip: {
                    backgroundColor: '#1E293B',
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, color: '#94A3B8', font: { family: "'Inter', sans-serif", size: 12 } },
                    border: { display: false },
                    grid: { color: '#F1F5F9', drawTicks: false }
                },
                y: {
                    ticks: { color: '#1E293B', font: { family: "'Inter', sans-serif", size: 12, weight: '600' } },
                    border: { display: false },
                    grid: { display: false }
                }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
