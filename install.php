<?php
/**
 * ======================================================
 *  Event Management System — One-Click Installer
 * ======================================================
 *  Open this file in your browser ONCE to set up the
 *  database, tables, admin account, and sample events.
 *
 *  ⚠️  DELETE THIS FILE after successful installation!
 * ======================================================
 */

$host   = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'dbevent';

$msgs    = [];
$ok      = true;

try {
    /* ── Connect ────────────────────────────────────────── */
    $pdo = new PDO("mysql:host=$host", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* ── Create database ────────────────────────────────── */
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
    $msgs[] = '✅ Database created.';

    /* ── Drop old tables ────────────────────────────────── */
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach (['registration_answers','registrations','event_requirements','events','users','admins'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS `$t`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $msgs[] = '✅ Old tables dropped.';

    /* ── Create tables ──────────────────────────────────── */
    $pdo->exec("
        CREATE TABLE admins (
            admin_id   INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(50)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE users (
            user_id        INT AUTO_INCREMENT PRIMARY KEY,
            fname          VARCHAR(100) NOT NULL,
            lname          VARCHAR(100) NOT NULL,
            email          VARCHAR(255) NOT NULL UNIQUE,
            password       VARCHAR(255) NOT NULL,
            contact_number VARCHAR(20)  DEFAULT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE events (
            event_id    INT AUTO_INCREMENT PRIMARY KEY,
            event_name  VARCHAR(255) NOT NULL,
            description TEXT,
            event_date  DATE         DEFAULT NULL,
            event_time  TIME         DEFAULT NULL,
            venue       VARCHAR(255) DEFAULT NULL,
            icon        VARCHAR(50)  DEFAULT '📋',
            color       VARCHAR(20)  DEFAULT '#667eea',
            min_age     INT          DEFAULT 0,
            is_active   TINYINT(1)   DEFAULT 1,
            is_archived TINYINT(1)   DEFAULT 0,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE event_requirements (
            req_id           INT AUTO_INCREMENT PRIMARY KEY,
            event_id         INT          NOT NULL,
            requirement_text VARCHAR(500) NOT NULL,
            sort_order       INT          DEFAULT 1,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE registrations (
            reg_id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NOT NULL,
            event_id        INT NOT NULL,
            status          ENUM('pending','approved','rejected') DEFAULT 'pending',
            registered_date DATE    DEFAULT NULL,
            registered_time TIME    DEFAULT NULL,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
            UNIQUE KEY unique_reg (user_id, event_id)
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE registration_answers (
            answer_id   INT AUTO_INCREMENT PRIMARY KEY,
            reg_id      INT  NOT NULL,
            req_id      INT  NOT NULL,
            answer_text TEXT,
            FOREIGN KEY (reg_id) REFERENCES registrations(reg_id)      ON DELETE CASCADE,
            FOREIGN KEY (req_id) REFERENCES event_requirements(req_id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    $msgs[] = '✅ All 6 tables created.';

    /* ── Seed admin ─────────────────────────────────────── */
    $hash = password_hash('12345', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (username,password) VALUES (?,?)")
        ->execute(['admin', $hash]);
    $msgs[] = '✅ Admin account created — <b>admin / 12345</b>';

    /* ── Seed events + requirements ─────────────────────── */
    $eventsData = [
        ['Basketball',          '5v5 basketball competition for all students!',          '2026-07-20', 'School Gymnasium',  '🏀', '#ff8fb1', 16,
            ['Why do you want to attend this event?', 'Are you affiliated with any sports team?', 'Do you have experience playing basketball?']],
        ['Programming Seminar', 'Learn basic to advanced coding concepts.',               '2026-07-25', 'Computer Lab 1',    '💻', '#b084f5', 15,
            ['Why do you want to attend this event?', 'Which programming languages do you want to learn?', 'What do you hope to accomplish?']],
        ['Art Exhibition',      'Unleash your talent in this exciting art exhibit!',       '2026-07-28', 'Multi-Purpose Hall','🎨', '#f6c453', 12,
            ['Why do you want to attend this event?', 'What type of art interests you most?', 'Do you have a background in visual arts?']],
        ['Concert',             'Watch out for an amazingly fun concert!',                 '2026-08-03', 'Auditorium',        '🎤', '#ff77aa', 18,
            ['What is your preferred music genre?', 'Which artist are you most excited to see?', 'Do you play any musical instrument?']],
        ['Food Festival',       'Join us and indulge your cravings!',                      '2026-08-08', 'Open Field',        '🍱', '#6fa8ff', 10,
            ['Why do you want to attend this event?', 'Do you own a food-related business?', 'What cuisine interests you the most?']],
    ];

    $insertEvent = $pdo->prepare("INSERT INTO events (event_name,description,event_date,venue,icon,color,min_age) VALUES (?,?,?,?,?,?,?)");
    $insertReq   = $pdo->prepare("INSERT INTO event_requirements (event_id,requirement_text,sort_order) VALUES (?,?,?)");

    foreach ($eventsData as $ev) {
        $reqs = array_pop($ev);
        $insertEvent->execute($ev);
        $eid = $pdo->lastInsertId();
        foreach ($reqs as $i => $q) {
            $insertReq->execute([$eid, $q, $i + 1]);
        }
    }
    $msgs[] = '✅ 5 default events with 3 requirements each created.';

} catch (Exception $e) {
    $ok = false;
    $msgs[] = '❌ Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install — Event Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#000;color:#fff;padding:20px}
        .card{background:#000;border:4px solid #fff;padding:40px;max-width:540px;width:100%;box-shadow: 12px 12px 0px #fff;}
        h1{font-size:32px;margin-bottom:30px;text-transform:uppercase;letter-spacing:-1px;border-bottom:4px solid #fff;padding-bottom:10px}
        .msg{padding:14px 18px;margin:10px 0;font-size:15px;font-weight:600;text-transform:uppercase;letter-spacing:1px;background:#000;border:2px solid #fff}
        .ok{color:#fff;border-left:8px solid #fff}
        .err{color:#000;background:#fff;border-left:8px solid #000}
        .next{margin-top:30px;display:flex;gap:15px;flex-wrap:wrap}
        a.btn{padding:14px 24px;text-decoration:none;font-weight:800;font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#000;background:#fff;border:2px solid #fff;transition:all .2s ease}
        a.btn:hover{background:#000;color:#fff;box-shadow: 6px 6px 0px #fff;transform:translate(-2px,-2px)}
        .warn{margin-top:30px;padding:15px;font-size:13px;font-weight:700;text-transform:uppercase;background:#000;border:2px dashed #fff;color:#fff;text-align:center}
        code{background:#fff;color:#000;padding:2px 6px;font-weight:bold}
    </style>
</head>
<body>
<div class="card">
    <h1>SYS // INSTALLER</h1>
    <?php foreach ($msgs as $m): ?>
        <?php 
            $text = str_replace(['✅ ', '❌ '], '', $m);
            $icon = $ok ? '[OK]' : '[ERR]';
        ?>
        <div class="msg <?= $ok ? 'ok' : 'err' ?>"><?= $icon ?> <?= $text ?></div>
    <?php endforeach; ?>

    <?php if ($ok): ?>
        <div class="next">
            <a class="btn" href="admin/login.php">ADMIN PANEL &rarr;</a>
            <a class="btn" href="user/login.php">USER PORTAL &rarr;</a>
        </div>
        <div class="warn">WARNING: DELETE <code>install.php</code> IMMEDIATELY AFTER USE.</div>
    <?php else: ?>
        <div class="warn">CRITICAL FAILURE: FIX ERROR AND REBOOT.</div>
    <?php endif; ?>
</div>
</body>
</html>
