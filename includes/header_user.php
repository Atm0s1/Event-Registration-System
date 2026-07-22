<?php
/**
 * User header partial — top navigation bar
 * Usage: set $currentPage before including, e.g. $currentPage = 'events';
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$basePath = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Event Manager') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body style="background: var(--bg); display: flex; flex-direction: column; min-height: 100vh;">
    <!-- User Navbar -->
    <header class="mobile-header" style="background: white; padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
        <a href="events.php" style="font-size: 20px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px; text-decoration: none;">
            <div style="background: var(--accent); color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <i class="ph-fill ph-ticket"></i>
            </div>
            Event Management
        </a>
        <nav class="mobile-nav" style="display: flex; gap: 32px; align-items: center;">
            <a href="events.php" style="font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 8px; text-decoration: none; color: <?= ($currentPage ?? '') === 'events' ? 'var(--accent)' : '#1F2937' ?>;">
                <i class="ph-fill ph-calendar-star" style="font-size: 20px;"></i> Browse Events
            </a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="mobile-padding" style="flex: 1; padding: 40px; max-width: 1200px; margin: 0 auto; width: 100%;">
