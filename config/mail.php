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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: 'Inter', Helvetica, sans-serif; background: #f4f7f6; margin: 0; padding: 20px 10px; }
            .container { max-width: 600px; width: 100%; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
            .map-btn { display: inline-block; background-color: #F8FAFC; border: 1px solid #E2E8F0; color: #334155; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 13px; font-weight: bold; margin-top: 6px; }
            .waze-btn { display: inline-block; background-color: #33CCFF; color: #111; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-size: 13px; font-weight: bold; margin-top: 6px; }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #00b894, #00cec9); padding: 40px 20px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 28px; letter-spacing: 1px;">YOU ARE IN!</h1>
                <p style="margin: 10px 0 0; opacity: 0.9;">Registration <strong style="color:#fff;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;">APPROVED</strong></p>
            </div>
            
            <!-- Body -->
            <div style="padding: 30px 20px;">
                <p style="font-size: 16px; color: #333; margin-top: 0;">Hi <strong>{$name}</strong>,</p>
                <p style="color: #666; line-height: 1.6; font-size: 14px;">Your registration for <strong>{$event}</strong> has been confirmed. Below is your official entrance ticket. Please present this QR code at the door.</p>
                
                <!-- QR Code Box -->
                <div style="text-align: center; margin: 25px 0; padding: 25px 15px; background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px;">
                    <a href="{$scanUrl}" target="_blank">
                        <img src="cid:qr_ticket_cid" alt="QR Ticket" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    </a>
                    <p style="color: #64748b; font-size: 12px; margin-top: 15px; letter-spacing: 1px; text-transform: uppercase;">Unique Ticket ID<br><strong style="color:#334155;font-size:14px;">{$qrToken}</strong></p>
                    <a href="{$scanUrl}&download=1" target="_blank" style="display: inline-block; background-color: #10B981; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: bold; margin-top: 10px;">View & Download PDF Receipt</a>
                </div>

                <!-- Event Details -->
                <table style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px;">
                    <tr>
                        <td style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; width: 80px; vertical-align: top; font-weight: bold; color: #94a3b8;">DATE</td>
                        <td style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: top;">
                            <strong>{$date}</strong><br>
                            <a href="{$calendarUrl}" class="waze-btn" style="background-color: #4285F4; color: #fff;" target="_blank">Pin to Google Calendar</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; font-weight: bold; color: #94a3b8;">VENUE</td>
                        <td style="padding: 15px 0; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: top;">
                            <strong>{$venue}</strong><br>
                            <a href="{$finalMapUrl}" class="map-btn" target="_blank">Navigate with Google Maps</a>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Footer -->
            <div style="background: #1e293b; color: #94a3b8; text-align: center; padding: 20px; font-size: 12px;">
                <p style="margin: 0;">Event Management System &copy; 2026</p>
                <p style="margin: 5px 0 0;">Please have this email ready on your phone when arriving.</p>
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
    <head><style>body { font-family: 'Inter', Helvetica, sans-serif; background: #f4f7f6; margin: 0; padding: 40px 20px; }</style></head>
    <body>
        <div style="max-width:600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
            <div style="background: linear-gradient(135deg, #e17055, #ff7675); padding: 40px 20px; text-align: center; color: white;">
                <h1 style="margin: 0; font-size: 24px;">Registration Update</h1>
            </div>
            <div style="padding: 40px;">
                <p style="font-size: 18px; color: #333; margin-top: 0;">Hi <strong>{$name}</strong>,</p>
                <p style="color: #666; line-height: 1.6;">Unfortunately, your registration for <strong>{$event}</strong> has been <span style="color:#e17055;font-weight:bold;">REJECTED</span>.</p>
                <p style="color: #666; line-height: 1.6;">This may be due to age restrictions or event capacity. Please check out our other available events!</p>
            </div>
            <div style="background: #1e293b; color: #94a3b8; text-align: center; padding: 20px; font-size: 12px;">
                <p style="margin: 0;">Event Management System &copy; 2026</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}
