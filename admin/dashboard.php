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
    SELECT event_name, event_date, venue, icon 
    FROM events 
    WHERE is_active = 1 AND event_date >= CURDATE() 
    ORDER BY event_date ASC LIMIT 4
")->fetchAll();

// Chart Data: Top Events by Participants
$chartDataQuery = $conn->query("
    SELECT e.event_name, COUNT(r.reg_id) as participant_count
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id
    WHERE e.is_active = 1
    GROUP BY e.event_id
    ORDER BY participant_count DESC
    LIMIT 5
")->fetchAll();

$chartLabels = [];
$chartValues = [];
foreach ($chartDataQuery as $row) {
    $name = (strlen($row['event_name']) > 18) ? substr($row['event_name'], 0, 18) . '...' : $row['event_name'];
    $chartLabels[] = $name;
    $chartValues[] = (int)$row['participant_count'];
}

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
                        <p><?= htmlspecialchars($ev['event_date'] ?? 'TBD') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════ RIGHT COLUMN ════ -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="stat-card" style="background: white; border: 1px solid var(--border); padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.03);">
            <h3 style="font-size: 16px; font-weight: 700; color: var(--text-dark); margin-bottom: 20px;"><i class="ph-bold ph-chart-bar" style="color: var(--primary);"></i> Top Events by Registration</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="participantsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('participantsChart').getContext('2d');
    
    // Gradient for bars
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, '#FF9F80');
    gradient.addColorStop(1, '#FF754D');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Participants',
                data: <?= json_encode($chartValues) ?>,
                backgroundColor: gradient,
                borderRadius: 8,
                borderSkipped: false,
                barThickness: 32
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1E293B',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' Participants';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#94A3B8',
                        font: { family: "'Inter', sans-serif", size: 12 }
                    },
                    border: { display: false },
                    grid: {
                        color: '#F1F5F9',
                        drawTicks: false,
                    }
                },
                x: {
                    ticks: {
                        color: '#64748B',
                        font: { family: "'Inter', sans-serif", size: 12, weight: '500' }
                    },
                    border: { display: false },
                    grid: {
                        display: false,
                        drawTicks: false
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
