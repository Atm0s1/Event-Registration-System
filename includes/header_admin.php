<?php
/**
 * Admin header — VidConf sidebar + top header
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — Event Management</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        window.addEventListener("pageshow", function (event) {
            if (event.persisted || (typeof window.performance != "undefined" && window.performance.navigation.type === 2)) {
                window.location.reload();
            }
        });
    </script>
</head>
<body>
<div class="admin-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-logo" style="gap: 8px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="ph-fill ph-ticket" style="font-size: 28px; color: var(--accent);"></i>
                <span style="font-size: 16px;">Event Management</span>
            </div>
            <button id="closeSidebarBtn" class="mobile-only-btn" style="background: none; border: none; color: white; cursor: pointer; display: none;"><i class="ph ph-x" style="font-size: 24px;"></i></button>
        </div>

        <div style="margin: 0 16px 16px 16px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 14px; font-weight: 700; color: white;">Admin</span>
                <span style="font-size: 12px; color: var(--accent);">Administrator</span>
            </div>
            <a href="logout.php" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; background: rgba(239, 68, 68, 0.15); color: #EF4444; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.25)'" onmouseout="this.style.background='rgba(239, 68, 68, 0.15)'" title="Logout">
                <i class="ph-bold ph-sign-out" style="font-size: 20px;"></i>
            </a>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php"       class="sidebar-link <?= ($currentPage ?? '') === 'dashboard'     ? 'active' : '' ?>"><i class="ph ph-squares-four" style="font-size: 20px;"></i> Dashboard</a>
            <a href="manage_events.php"   class="sidebar-link <?= ($currentPage ?? '') === 'events'        ? 'active' : '' ?>"><i class="ph ph-calendar-blank" style="font-size: 20px;"></i> Manage Events</a>
            <a href="registrations.php"   class="sidebar-link <?= ($currentPage ?? '') === 'registrations' ? 'active' : '' ?>"><i class="ph ph-users" style="font-size: 20px;"></i> Registrations</a>
            <a href="scanner.php"         class="sidebar-link" style="color: #38BDF8; font-weight: 600;"><i class="ph-bold ph-scan" style="font-size: 20px;"></i> Launch Scanner</a>
            <div class="sidebar-divider"></div>
            <a href="history.php"         class="sidebar-link <?= ($currentPage ?? '') === 'history'       ? 'active' : '' ?>"><i class="ph ph-scroll" style="font-size: 20px;"></i> History</a>
            <a href="export_xml.php"      class="sidebar-link <?= ($currentPage ?? '') === 'export'        ? 'active' : '' ?>"><i class="ph ph-upload-simple" style="font-size: 20px;"></i> Export XML</a>
            <a href="import_xml.php"      class="sidebar-link <?= ($currentPage ?? '') === 'import'        ? 'active' : '' ?>"><i class="ph ph-download-simple" style="font-size: 20px;"></i> Import XML</a>
        </nav>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main-content">
        <header class="top-header" style="margin-bottom: 0;">
            <div class="header-left">
                <button id="openSidebarBtn" class="mobile-only-btn" style="background: none; border: none; color: var(--text-dark); cursor: pointer; display: none; margin-right: 16px;"><i class="ph ph-list" style="font-size: 28px;"></i></button>
            </div>
        </header>

        <script>
            // Mobile sidebar toggle
            const sidebar = document.getElementById('adminSidebar');
            const openBtn = document.getElementById('openSidebarBtn');
            const closeBtn = document.getElementById('closeSidebarBtn');
            
            if (openBtn && closeBtn && sidebar) {
                openBtn.addEventListener('click', () => { sidebar.classList.add('active'); });
                closeBtn.addEventListener('click', () => { sidebar.classList.remove('active'); });
            }
        </script>
