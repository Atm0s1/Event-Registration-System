<?php
/**
 * Gmail SMTP Mail Configuration
 * Uses PHPMailer to send approval / rejection emails.
 */

// ── CHANGE THESE TWO LINES ──────────────────────────────────
define('MAIL_USERNAME', 'jeremysibonga99@gmail.com');
define('MAIL_PASSWORD', 'udkkqoomquhjbowh');        // 16-char App Password
// ─────────────────────────────────────────────────────────────

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_FROM_NAME', 'Event Management System');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an approval notification email with QR code ticket.
 */
function sendApprovalEmail(string $toEmail, string $toName, string $eventName, string $eventDate, string $eventVenue, string $qrToken, string $mapUrl = ''): bool {
    return _sendStatusEmail($toEmail, $toName, $eventName, 'approved', $eventDate, $eventVenue, $qrToken, $mapUrl);
}

/**
 * Send a rejection notification email.
 */
function sendRejectionEmail(string $toEmail, string $toName, string $eventName): bool {
    return _sendStatusEmail($toEmail, $toName, $eventName, 'rejected');
}

/**
 * Internal helper — builds and sends the status email.
 */
function _sendStatusEmail(string $toEmail, string $toName, string $eventName, string $status, string $eventDate = '', string $eventVenue = '', string $qrToken = '', string $mapUrl = ''): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("PHPMailer not installed.");
        return false;
    }
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        if ($status === 'approved') {
            $mail->Subject = "Your Ticket: Registration Approved — $eventName";
            
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $scanUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/scan.php?token=" . urlencode($qrToken);
            $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($scanUrl) . "&margin=10";
            
            // Fetch and embed the image directly (CID attachment) to prevent email clients from blocking external images
            $context = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false], "http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]]);
            $qrImageData = @file_get_contents($qrApiUrl, false, $context);
            if ($qrImageData) {
                $mail->addStringEmbeddedImage($qrImageData, 'qr_ticket_cid', 'ticket.png', 'base64', 'image/png');
            }
            
            $mail->Body = _buildApprovedHtml($toName, $eventName, $eventDate, $eventVenue, $qrToken, $scanUrl, $mapUrl);
        } else {
            $mail->Subject = "Registration Rejected — $eventName";
            $mail->Body = _buildRejectedHtml($toName, $eventName);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
}

/* ── HTML email templates ─────────────────────────────────── */

function _buildApprovedHtml(string $name, string $event, string $date, string $venue, string $qrToken, string $scanUrl, string $mapUrl = ''): string {
    $finalMapUrl = !empty($mapUrl) ? $mapUrl : "https://www.google.com/maps/search/?api=1&query=" . urlencode($venue);

    // Generate Add to Google Calendar Link
    $startDate = str_replace('-', '', $date);
    $endDate = date('Ymd', strtotime($date . ' +1 day'));
    $calendarUrl = "https://calendar.google.com/calendar/render?action=TEMPLATE&text=" . urlencode($event) . "&dates={$startDate}/{$endDate}&details=" . urlencode("Your registration is confirmed! Have this email ready.") . "&location=" . urlencode($venue);

    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #ffffff; color: #1f2937; margin: 0; padding: 30px 20px; line-height: 1.6; }
            .container { max-width: 520px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; }
            .btn { display: inline-block; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 8px; margin-top: 8px; }
            .btn-map { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
            .btn-cal { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="font-size: 22px; font-weight: 800; color: #111827; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 16px;">
                Hi {$name}, You Are Registered!
            </h1>
            
            <p style="font-size: 15px; color: #374151; margin-bottom: 24px;">
                Your registration for <strong>{$event}</strong> is confirmed. Here are your event details:
            </p>

            <div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 28px; font-size: 14px;">
                <p style="margin: 0 0 10px 0;"><strong style="color:#4b5563; width: 60px; display:inline-block;">Event:</strong> <span style="font-weight:700; color:#111827;">{$event}</span></p>
                <p style="margin: 0 0 10px 0;"><strong style="color:#4b5563; width: 60px; display:inline-block;">Date:</strong> {$date}</p>
                <p style="margin: 0 0 16px 0;"><strong style="color:#4b5563; width: 60px; display:inline-block;">Venue:</strong> {$venue}</p>
                
                <div>
                    <a href="{$finalMapUrl}" class="btn btn-map" target="_blank">📍 Open in Google Maps</a>
                    <a href="{$calendarUrl}" class="btn btn-cal" target="_blank">📅 Add to Calendar</a>
                </div>
            </div>

            <div style="text-align: center; border-top: 1px solid #e5e7eb; padding-top: 28px;">
                <p style="font-size: 15px; font-weight: 700; color: #111827; margin-top: 0; margin-bottom: 12px;">
                    Please present this QR code when entering the event:
                </p>
                <a href="{$scanUrl}" target="_blank">
                    <img src="cid:qr_ticket_cid" alt="QR Ticket" style="width: 200px; height: 200px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; background: #fff;">
                </a>
                <p style="font-size: 12px; color: #6b7280; margin-top: 10px;">
                    Ticket ID: <strong>{$qrToken}</strong>
                </p>
            </div>
            
            <div style="margin-top: 32px; font-size: 12px; color: #9ca3af; text-align: center; border-top: 1px solid #f3f4f6; padding-top: 16px;">
                Event Management System &copy; 2026
            </div>
        </div>
    </body>
    </html>
    HTML;
}

function _buildRejectedHtml(string $name, string $event): string {
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #ffffff; color: #1f2937; margin: 0; padding: 30px 20px; line-height: 1.6; }
            .container { max-width: 520px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 style="font-size: 22px; font-weight: 800; color: #dc2626; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 16px;">
                Registration Status Update
            </h1>
            <p style="font-size: 15px; color: #374151;">Hi <strong>{$name}</strong>,</p>
            <p style="color: #4b5563; font-size: 15px;">Unfortunately, your registration for <strong>{$event}</strong> has been <strong>not approved</strong> at this time due to event capacity or eligibility criteria.</p>
            <div style="margin-top: 32px; font-size: 12px; color: #9ca3af; text-align: center; border-top: 1px solid #f3f4f6; padding-top: 16px;">
                Event Management System &copy; 2026
            </div>
        </div>
    </body>
    </html>
    HTML;
}
