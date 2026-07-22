<?php
/**
 * Admin — Export XML
 * Exports all registrations with user + event + answers to XML.
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// If download requested
if (isset($_GET['download'])) {
    $db   = new Database();
    $conn = $db->connect();

    $regs = $conn->query("
        SELECT r.reg_id, r.status, r.registered_date, r.registered_time,
               u.fname, u.lname, u.email, u.contact_number,
               e.event_name
        FROM registrations r
        JOIN users u  ON r.user_id  = u.user_id
        JOIN events e ON r.event_id = e.event_id
        ORDER BY e.event_name, r.registered_date
    ")->fetchAll();

    // Fetch answers
    $answers = [];
    $ansRows = $conn->query("
        SELECT ra.reg_id, er.requirement_text, ra.answer_text
        FROM registration_answers ra
        JOIN event_requirements er ON ra.req_id = er.req_id
        ORDER BY er.sort_order
    ")->fetchAll();
    foreach ($ansRows as $a) {
        $answers[$a['reg_id']][] = $a;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $root = $dom->createElement('registrations');

    foreach ($regs as $r) {
        $node = $dom->createElement('registration');
        $node->appendChild($dom->createElement('reg_id',     $r['reg_id']));
        $node->appendChild($dom->createElement('fname',      $r['fname']));
        $node->appendChild($dom->createElement('lname',      $r['lname']));
        $node->appendChild($dom->createElement('email',      $r['email']));
        $node->appendChild($dom->createElement('contact',    $r['contact_number'] ?? ''));
        $node->appendChild($dom->createElement('event',      $r['event_name']));
        $node->appendChild($dom->createElement('status',     $r['status']));
        $node->appendChild($dom->createElement('date',       $r['registered_date']));
        $node->appendChild($dom->createElement('time',       $r['registered_time']));

        // Add answers
        if (!empty($answers[$r['reg_id']])) {
            $ansNode = $dom->createElement('answers');
            foreach ($answers[$r['reg_id']] as $a) {
                $aNode = $dom->createElement('answer');
                $aNode->appendChild($dom->createElement('question', $a['requirement_text']));
                $aNode->appendChild($dom->createElement('response', $a['answer_text']));
                $ansNode->appendChild($aNode);
            }
            $node->appendChild($ansNode);
        }

        $root->appendChild($node);
    }
    $dom->appendChild($root);

    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename=registrations_export_' . date('Y-m-d') . '.xml');
    echo $dom->saveXML();
    exit;
}

// Show export page
$currentPage = 'export';
$pageTitle   = 'Export XML';
$GLOBALS['admin_layout'] = true;

$db   = new Database();
$conn = $db->connect();
$totalRegs = $conn->query("SELECT COUNT(*) FROM registrations")->fetchColumn();

require_once __DIR__ . '/../includes/header_admin.php';
?>

<h1 class="page-title">Export XML</h1>
<p class="page-subtitle">Download all registration data as an XML file</p>

<div class="glass-card" style="max-width:500px;text-align:center;">
    <div style="font-size:64px;margin-bottom:16px;">📤</div>
    <h3 style="margin-bottom:8px;">Export Registrations</h3>
    <p style="color:var(--text-secondary);margin-bottom:20px;">
        <?= $totalRegs ?> registration(s) will be exported with user details, event info, status, and answers.
    </p>
    <a href="export_xml.php?download=1" class="btn btn-primary">⬇️ Download XML</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
