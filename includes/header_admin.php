<?php
/**
 * Admin header — VidConf sidebar + top header
 */
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
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
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

        <nav class="sidebar-nav">
            <a href="dashboard.php"       class="sidebar-link <?= ($currentPage ?? '') === 'dashboard'     ? 'active' : '' ?>"><i class="ph ph-squares-four" style="font-size: 20px;"></i> Dashboard</a>
            <a href="manage_events.php"   class="sidebar-link <?= ($currentPage ?? '') === 'events'        ? 'active' : '' ?>"><i class="ph ph-calendar-blank" style="font-size: 20px;"></i> Manage Events</a>
            <a href="registrations.php"   class="sidebar-link <?= ($currentPage ?? '') === 'registrations' ? 'active' : '' ?>"><i class="ph ph-users" style="font-size: 20px;"></i> Registrations</a>
            <div class="sidebar-divider"></div>
            <a href="history.php"         class="sidebar-link <?= ($currentPage ?? '') === 'history'       ? 'active' : '' ?>"><i class="ph ph-scroll" style="font-size: 20px;"></i> History</a>
            <a href="export_xml.php"      class="sidebar-link <?= ($currentPage ?? '') === 'export'        ? 'active' : '' ?>"><i class="ph ph-upload-simple" style="font-size: 20px;"></i> Export XML</a>
            <a href="import_xml.php"      class="sidebar-link <?= ($currentPage ?? '') === 'import'        ? 'active' : '' ?>"><i class="ph ph-download-simple" style="font-size: 20px;"></i> Import XML</a>
        </nav>

        <a href="logout.php" class="sidebar-logout"><i class="ph ph-sign-out" style="font-size: 20px;"></i> Sign Out</a>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button id="openSidebarBtn" class="mobile-only-btn" style="background: none; border: none; color: var(--text-dark); cursor: pointer; display: none; margin-right: 16px;"><i class="ph ph-list" style="font-size: 28px;"></i></button>
            </div>
            <div class="header-right">
                <div class="header-user-info" style="text-align: right;">
                    <span class="header-user-name">Admin</span>
                    <span class="header-user-role">Administrator</span>
                </div>
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
