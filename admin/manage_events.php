<?php
/**
 * Admin — Manage Events
 * Add, edit, delete events with type, min age, and 3 requirements.
 */
$currentPage = 'events';
$pageTitle   = 'Manage Events';
$GLOBALS['admin_layout'] = true;

require_once __DIR__ . '/../config/database.php';

$db   = new Database();
$conn = $db->connect();

$success = '';
$error   = '';

// ── Handle POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO events (event_name,description,event_date,event_time,venue,latitude,longitude,icon,color,min_age,max_capacity) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($_POST['event_name']),
                trim($_POST['description']),
                $_POST['event_date'] ?: null,
                $_POST['event_time'] ?: null,
                trim($_POST['venue']),
                $_POST['latitude'] ? (float)$_POST['latitude'] : null,
                $_POST['longitude'] ? (float)$_POST['longitude'] : null,
                trim($_POST['icon']) ?: '📋',
                trim($_POST['color']) ?: '#667eea',
                (int)$_POST['min_age'],
                $_POST['max_capacity'] !== '' ? (int)$_POST['max_capacity'] : null
            ]);
            
            $success = 'Event added successfully!';

        } elseif ($action === 'edit') {
            $eid = (int)$_POST['event_id'];
            $stmt = $conn->prepare("UPDATE events SET event_name=?,description=?,event_date=?,event_time=?,venue=?,latitude=?,longitude=?,icon=?,color=?,min_age=?,max_capacity=? WHERE event_id=?");
            $stmt->execute([
                trim($_POST['event_name']),
                trim($_POST['description']),
                $_POST['event_date'] ?: null,
                $_POST['event_time'] ?: null,
                trim($_POST['venue']),
                $_POST['latitude'] ? (float)$_POST['latitude'] : null,
                $_POST['longitude'] ? (float)$_POST['longitude'] : null,
                trim($_POST['icon']) ?: '📋',
                trim($_POST['color']) ?: '#667eea',
                (int)$_POST['min_age'],
                $_POST['max_capacity'] !== '' ? (int)$_POST['max_capacity'] : null,
                $eid
            ]);

            $success = 'Event updated successfully!';

        } elseif ($action === 'delete') {
            $eid = (int)$_POST['event_id'];
            $conn->prepare("UPDATE events SET is_active = 0 WHERE event_id = ?")->execute([$eid]);
            $success = 'Event deactivated.';

        } elseif ($action === 'archive') {
            $eid = (int)$_POST['event_id'];
            $conn->prepare("UPDATE events SET is_archived = 1, is_active = 0 WHERE event_id = ?")->execute([$eid]);
            $success = 'Event archived successfully. You can view it in the History tab.';

        } elseif ($action === 'restore') {
            $eid = (int)$_POST['event_id'];
            $conn->prepare("UPDATE events SET is_active = 1 WHERE event_id = ?")->execute([$eid]);
            $success = 'Event restored.';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch events
$events = $conn->query("SELECT * FROM events WHERE is_archived = 0 ORDER BY is_active DESC, created_at DESC")->fetchAll();

// Editing?
$editEvent = null;
$editReqs  = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($events as $ev) {
        if ($ev['event_id'] == $editId) { $editEvent = $ev; break; }
    }
}

require_once __DIR__ . '/../includes/header_admin.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="margin-bottom:4px;">Manage Events</h1>
        <p class="page-subtitle" style="margin-bottom:0;">Add, edit, and manage event types with requirements</p>
    </div>
    <?php if (!$editEvent): ?>
    <button onclick="document.getElementById('eventFormContainer').style.display='block'; this.style.display='none'; setTimeout(function(){ if(typeof map !== 'undefined') map.invalidateSize(); }, 100);" class="btn btn-primary" id="addEventBtn" style="white-space:nowrap; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"><i class="ph-bold ph-plus"></i> Add New Event</button>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div id='eventFormContainer' <?php if (!$editEvent): ?>style='display:none;'<?php endif; ?>>
<!-- Add / Edit Form -->
<div class="card" style="margin-bottom:28px;">
    <h3 style="margin-bottom:16px;"><?= $editEvent ? '<i class="ph-bold ph-pencil-simple"></i> Edit Event' : '<i class="ph-bold ph-plus"></i> Add New Event' ?></h3>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editEvent ? 'edit' : 'add' ?>">
        <?php if ($editEvent): ?>
            <input type="hidden" name="event_id" value="<?= $editEvent['event_id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Event Name *</label>
                <input type="text" name="event_name" class="form-input" required
                    value="<?= htmlspecialchars($editEvent['event_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Venue / Location Name</label>
                <input type="text" name="venue" class="form-input"
                    value="<?= htmlspecialchars($editEvent['venue'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><i class="ph-bold ph-map-pin"></i> Pin Event Location (Search or click to drop pin)</label>
            <div style="position: relative; margin-bottom: 8px;">
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <input type="text" id="mapSearch" class="form-input" placeholder="Search for a place, address, or landmark..." style="flex: 1; border-radius: 10px;">
                    <button type="button" id="mapSearchBtn" class="btn btn-primary" style="white-space: nowrap;" onclick="searchLocation()"><i class="ph-bold ph-magnifying-glass"></i> Search</button>
                </div>
                <div id="searchResults" style="display:none; position:absolute; top:52px; left:0; right:0; z-index:1000; background:white; border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,0.15); border:1px solid #E2E8F0; max-height:200px; overflow-y:auto;"></div>
            </div>
            <div id="map" style="width: 100%; height: 350px; border-radius: 12px; border: 2px solid #E2E8F0; z-index: 1;"></div>
            <input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars($editEvent['latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars($editEvent['longitude'] ?? '') ?>">
            <div id="coordDisplay" style="margin-top: 8px; padding: 8px 14px; background: #F1F5F9; border-radius: 8px; font-size: 12px; color: #64748B; font-family: monospace;">
                <i class="ph-bold ph-push-pin"></i> Coordinates: <span id="coordText">Click the map or search to set location</span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea"><?= htmlspecialchars($editEvent['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Event Date</label>
                <input type="date" name="event_date" class="form-input" onkeydown="return false"
                    value="<?= htmlspecialchars($editEvent['event_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Event Time</label>
                <input type="time" name="event_time" class="form-input"
                    value="<?= htmlspecialchars($editEvent['event_time'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Minimum Age</label>
                <input type="number" name="min_age" class="form-input" min="0"
                    value="<?= htmlspecialchars($editEvent['min_age'] ?? '0') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Max Capacity (Leave blank for unlimited)</label>
                <input type="number" name="max_capacity" class="form-input" min="1"
                    value="<?= htmlspecialchars($editEvent['max_capacity'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Icon</label>
                <input type="text" name="icon" class="form-input"
                    value="<?= htmlspecialchars($editEvent['icon'] ?? '📋') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Color</label>
                <input type="color" name="color" class="form-input" style="height:44px;padding:4px;"
                    value="<?= htmlspecialchars($editEvent['color'] ?? '#667eea') ?>">
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary"><?= $editEvent ? 'Update Event' : 'Add Event' ?></button>
            <?php if ($editEvent): ?>
                <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>


</div>

<!-- Events List -->
<h2 style="font-size:20px;font-weight:700;margin-bottom:16px;">All Events (<?= count($events) ?>)</h2>

<?php if (empty($events)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="ph-fill ph-ticket"></i></div>
        <p>No events yet. Add one above!</p>
    </div>
<?php else: ?>
    <div class="admin-event-grid">
    <?php foreach ($events as $ev): ?>
        <div class="admin-event-card" style="<?= !$ev['is_active'] ? 'opacity:0.6;' : '' ?> border-top: 4px solid <?= htmlspecialchars($ev['color']) ?>; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <h3 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 17px; color: var(--text-dark);">
                    <span style="display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; background: <?= htmlspecialchars($ev['color']) ?>20; color: <?= htmlspecialchars($ev['color']) ?>;">
                        <i class="ph-fill ph-ticket" style="font-size: 20px;"></i>
                    </span>
                    <?= htmlspecialchars($ev['event_name']) ?>
                </h3>
                <?php if (!$ev['is_active']): ?>
                    <span class="badge badge-rejected" style="font-size:10px;">INACTIVE</span>
                <?php endif; ?>
            </div>

            <p class="event-detail" style="margin-bottom: 16px; color: var(--text-light); line-height: 1.5; flex-grow: 1;">
                <?= htmlspecialchars($ev['description'] ?? 'No description provided.') ?>
            </p>

            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; font-size: 13px; color: var(--text-muted);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-calendar-blank" style="font-size: 16px; color: var(--accent);"></i> <?= htmlspecialchars($ev['event_date'] ?? 'TBD') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-map-pin" style="font-size: 16px; color: #EF4444;"></i> <?= htmlspecialchars($ev['venue'] ?? 'TBD') ?>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-user" style="font-size: 16px; color: #F5A623;"></i> Min Age: <strong style="color: var(--text-dark); margin-left: 4px;"><?= $ev['min_age'] ?>+</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-users-three" style="font-size: 16px; color: #8B5CF6;"></i> Capacity: <strong style="color: var(--text-dark); margin-left: 4px;"><?= !empty($ev['max_capacity']) ? $ev['max_capacity'] : 'Unlimited' ?></strong>
                </div>
            </div>

            <!-- Requirements removed -->

            <div class="btn-group" style="margin-top: auto; border-top: 1px solid #F1F5F9; padding-top: 16px; display: flex; gap: 8px;">
                <a href="manage_events.php?edit=<?= $ev['event_id'] ?>" class="btn btn-sm" style="background: #E0E7FF; color: #4F46E5; box-shadow: none;"><i class="ph ph-pencil-simple" style="font-size: 16px;"></i> Edit</a>
                <?php if ($ev['is_active']): ?>
                <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Deactivate this event?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background: #FEE2E2; color: #EF4444; box-shadow: none;"><i class="ph ph-pause" style="font-size: 16px;"></i> Deactivate</button>
                </form>
                <?php else: ?>
                <form method="POST" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background: #D1FAE5; color: #10B981; box-shadow: none;"><i class="ph ph-arrows-clockwise" style="font-size: 16px;"></i> Restore</button>
                </form>
                <?php endif; ?>
                
                <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Archive this event? It will be moved to the History tab.')">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="event_id" value="<?= $ev['event_id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background: #FFFBEB; color: #F59E0B; box-shadow: none;"><i class="ph ph-archive" style="font-size: 16px;"></i> Archive</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
var map, marker, latInput, lngInput;

document.addEventListener('DOMContentLoaded', function() {
    latInput = document.getElementById('latitude');
    lngInput = document.getElementById('longitude');
    var coordText = document.getElementById('coordText');
    var venueInput = document.querySelector('input[name="venue"]');

    var initialLat = latInput.value ? parseFloat(latInput.value) : 14.5995;
    var initialLng = lngInput.value ? parseFloat(lngInput.value) : 120.9842;
    var zoomLevel = latInput.value ? 15 : 12;

    map = L.map('map').setView([initialLat, initialLng], zoomLevel);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    var customIcon = L.divIcon({
        html: '<div style="background:#6366F1;width:28px;height:28px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-size:14px;">📍</div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        className: ''
    });

    marker = L.marker([initialLat, initialLng], {draggable: true, icon: customIcon}).addTo(map);

    function updateInputs(lat, lng) {
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
        coordText.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
    }

    if (!latInput.value) {
        map.removeLayer(marker);
    } else {
        updateInputs(initialLat, initialLng);
    }

    map.on('click', function(e) {
        if (!map.hasLayer(marker)) marker.addTo(map);
        marker.setLatLng([e.latlng.lat, e.latlng.lng]);
        updateInputs(e.latlng.lat, e.latlng.lng);
    });

    marker.on('dragend', function(e) {
        var pos = marker.getLatLng();
        updateInputs(pos.lat, pos.lng);
    });

    // Search on Enter key
    document.getElementById('mapSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
    });
});

function searchLocation() {
    var query = document.getElementById('mapSearch').value.trim();
    if (!query) return;
    
    var resultsDiv = document.getElementById('searchResults');
    resultsDiv.innerHTML = '<div style="padding:14px;color:#64748B;font-size:13px;">🔄 Searching...</div>';
    resultsDiv.style.display = 'block';

    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&limit=5&addressdetails=1')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.length) {
                resultsDiv.innerHTML = '<div style="padding:14px;color:#EF4444;font-size:13px;"><i class="ph-bold ph-x-circle"></i> No results found. Try a different search.</div>';
                return;
            }
            resultsDiv.innerHTML = '';
            data.forEach(function(item) {
                var div = document.createElement('div');
                div.style.cssText = 'padding:12px 16px;cursor:pointer;border-bottom:1px solid #F1F5F9;font-size:13px;color:#1E293B;transition:background 0.15s;';
                div.innerHTML = '<strong style="color:#6366F1;">' + (item.display_name.split(',')[0]) + '</strong><br><span style="color:#94A3AF;font-size:11px;">' + item.display_name + '</span>';
                div.onmouseover = function() { this.style.background = '#F8FAFC'; };
                div.onmouseout = function() { this.style.background = 'white'; };
                div.onclick = function() {
                    var lat = parseFloat(item.lat);
                    var lng = parseFloat(item.lon);
                    map.flyTo([lat, lng], 16, {duration: 1.5});
                    if (!map.hasLayer(marker)) marker.addTo(map);
                    marker.setLatLng([lat, lng]);
                    latInput.value = lat.toFixed(8);
                    lngInput.value = lng.toFixed(8);
                    document.getElementById('coordText').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
                    
                    // Auto-fill venue name
                    var venueInput = document.querySelector('input[name="venue"]');
                    if (venueInput && !venueInput.value) {
                        venueInput.value = item.display_name.split(',').slice(0, 3).join(', ');
                    }
                    
                    resultsDiv.style.display = 'none';
                    document.getElementById('mapSearch').value = item.display_name.split(',').slice(0, 2).join(', ');
                };
                resultsDiv.appendChild(div);
            });
        })
        .catch(function() {
            resultsDiv.innerHTML = '<div style="padding:14px;color:#EF4444;font-size:13px;"><i class="ph-bold ph-warning-circle"></i> Search failed. Please try again.</div>';
        });
}

// Close search results when clicking elsewhere
document.addEventListener('click', function(e) {
    var resultsDiv = document.getElementById('searchResults');
    if (resultsDiv && !e.target.closest('#searchResults') && e.target.id !== 'mapSearch' && e.target.id !== 'mapSearchBtn') {
        resultsDiv.style.display = 'none';
    }
});
});
</script>

<style>
/* Custom Flatpickr Theme matching Figma Mockup */
.flatpickr-calendar {
    box-shadow: 0 20px 50px rgba(0,0,0,0.15) !important;
    border: none !important;
    border-radius: 24px !important;
    font-family: 'Inter', sans-serif !important;
    padding-bottom: 10px !important;
}
.flatpickr-day {
    border-radius: 50% !important;
    font-weight: 500;
}
.flatpickr-months {
    margin-bottom: 10px;
}
.flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.startRange.inRange, .flatpickr-day.endRange.inRange, .flatpickr-day.selected:focus, .flatpickr-day.startRange:focus, .flatpickr-day.endRange:focus, .flatpickr-day.selected:hover, .flatpickr-day.startRange:hover, .flatpickr-day.endRange:hover, .flatpickr-day.selected.prevMonthDay, .flatpickr-day.startRange.prevMonthDay, .flatpickr-day.endRange.prevMonthDay, .flatpickr-day.selected.nextMonthDay, .flatpickr-day.startRange.nextMonthDay, .flatpickr-day.endRange.nextMonthDay {
    background: #5F949A !important;
    border-color: #5F949A !important;
    box-shadow: 0 4px 10px rgba(95, 148, 154, 0.4);
}

.flatpickr-time {
    border-top: none !important;
}
.flatpickr-time input:hover, .flatpickr-time .flatpickr-am-pm:hover, .flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus {
    background: #F1F5F9 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize custom date picker
    flatpickr('input[name="event_date"]', {
        dateFormat: "Y-m-d",
        minDate: "today", // Disallow past dates naturally
        altInput: true,
        altFormat: "F j, Y",
        disableMobile: true // Force the custom UI on mobile instead of native picker
    });
    
    // Initialize custom time picker
    flatpickr('input[name="event_time"]', {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: false,
        altInput: true,
        altFormat: "h:i K",
        disableMobile: true
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
