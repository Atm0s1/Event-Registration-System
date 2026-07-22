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

require_once __DIR__ . '/../includes/header_admin.php';
?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            <div class="stat-card gray">
                <div class="stat-label">Average Rating</div>
                <div class="stat-value">4.5</div>
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

        <!-- Booking vs Revenue Chart -->
        <div class="chart-card">
            <h3>Registrations Trend</h3>
            <p class="chart-subtitle">Past 7 Days</p>
            <div style="position: relative; height: 220px;">
                <canvas id="areaChart"></canvas>
            </div>
        </div>




    </div>
</div>

<!-- ════ CHARTS JS ════ -->
<script>

// Area Chart
const actx = document.getElementById('areaChart').getContext('2d');
const grad = actx.createLinearGradient(0,0,0,220);
grad.addColorStop(0,'rgba(245,166,35,0.35)');
grad.addColorStop(1,'rgba(245,166,35,0)');
new Chart(actx, {
    type: 'line',
    data: {
        labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        datasets: [{
            label: 'New Registrations', data: [1,2.5,1.5,3,2.5,3.5,5], borderColor: '#F5A623', backgroundColor: grad, borderWidth: 3, fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderColor: '#3B82F6', pointBorderWidth: 2
        },{
            label: 'Active Users', data: [0.5,1,1.5,1,2,1.5,2.5], borderColor: '#A78BFA', borderWidth: 2, borderDash: [5,5], fill: false, tension: 0.4, pointRadius: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } } },
        scales: { y: { beginAtZero: true, max: 6, grid: { color: '#F3F4F6', drawBorder: false }, border: { display: false }, ticks: { font: { size: 11 }, color: '#9CA3AF' } }, x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }, color: '#9CA3AF' } } }
    }
});


</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
