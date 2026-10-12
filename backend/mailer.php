<?php
declare(strict_types=1);

/**
 * Play-On Mombasa - Email Notification Dispatcher
 * Uses PHPMailer with SMTP and logs all dispatches into `email_log` table.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Instantiate configured PHPMailer object with SMTP
 */
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    $smtpEnabled = filter_var(env('SMTP_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN);

    if ($smtpEnabled) {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST', 'mail.mombasamall.co.ke');
        $mail->SMTPAuth   = filter_var(env('SMTP_AUTH', 'true'), FILTER_VALIDATE_BOOLEAN);
        $mail->Username   = env('SMTP_USER', 'info@mombasamall.co.ke');
        $mail->Password   = env('SMTP_PASS', '');
        
        $secure = strtolower((string)env('SMTP_SECURE', 'ssl'));
        if ($secure === 'ssl' || $secure === 'smtps') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls' || $secure === 'starttls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->Port = (int)env('SMTP_PORT', 465);
        $mail->Timeout = 15; // 15s timeout
    } else {
        $mail->isMail();
    }

    $fromEmail = env('SMTP_FROM_EMAIL', 'info@mombasamall.co.ke');
    $fromName  = env('SMTP_FROM_NAME', 'Play-On Mombasa');
    $mail->setFrom($fromEmail, $fromName);
    $mail->addReplyTo($fromEmail, $fromName);

    return $mail;
}

/**
 * Log email send attempt to database
 */
function logEmailAttempt(
    PDO $pdo,
    ?int $bookingId,
    string $recipient,
    ?string $recipientName,
    string $emailType,
    string $subject,
    string $status,
    ?string $errorMessage = null
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `email_log` 
            (`booking_id`, `recipient`, `recipient_name`, `email_type`, `subject`, `status`, `error_message`, `sent_at`) 
            VALUES (:b_id, :recipient, :name, :type, :subject, :status, :err, NOW())
        ");
        $stmt->execute([
            ':b_id'      => $bookingId,
            ':recipient' => $recipient,
            ':name'      => $recipientName,
            ':type'      => $emailType,
            ':subject'   => $subject,
            ':status'    => $status,
            ':err'       => $errorMessage
        ]);
    } catch (Throwable $e) {
        error_log('Failed to write to email_log: ' . $e->getMessage());
    }
}

/**
 * Send booking notification emails (Admin and Customer)
 *
 * @param PDO $pdo Active PDO connection
 * @param int $bookingId ID of inserted booking
 * @param array $b Booking details array
 * @return array Status report
 */
function sendBookingNotifications(PDO $pdo, int $bookingId, array $b): array {
    $results = [
        'admin_sent'    => false,
        'customer_sent' => false,
        'errors'        => []
    ];

    $frontendUrl = rtrim((string)env('FRONTEND_URL', 'https://shimix-del.github.io/playon'), '/');
    $adminBase   = (isset($_SERVER['HTTP_HOST']))
        ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME'], 2) . '/admin'
        : 'https://mombasamall.co.ke/playon-backend/admin';

    // ------------------------------------------------------------------------
    // 1. ADMIN NOTIFICATION EMAIL
    // ------------------------------------------------------------------------
    $adminEmailsRaw = env('ADMIN_EMAILS', 'playonkenya@gmail.com,mombasamall01@gmail.com');
    $adminEmails = array_map('trim', explode(',', (string)$adminEmailsRaw));

    $adminSubject = "New Booking Request - {$b['booking_ref']} - {$b['full_name']}";
    $adminBody = renderAdminEmailHtml($b, $adminBase);

    try {
        $mail = createMailer();
        $mail->isHTML(true);
        $mail->Subject = $adminSubject;
        $mail->Body    = $adminBody;
        $mail->AltBody = strip_tags(str_replace(['</tr>', '<br>', '</p>'], "\n", $adminBody));

        foreach ($adminEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email, 'Play-On Management');
            }
        }

        $mail->send();
        $results['admin_sent'] = true;

        foreach ($adminEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                logEmailAttempt($pdo, $bookingId, $email, 'Play-On Admin', 'admin_notification', $adminSubject, 'sent');
            }
        }
    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        $results['errors'][] = "Admin email error: {$errMsg}";
        error_log("Admin booking email failed: {$errMsg}");
        foreach ($adminEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                logEmailAttempt($pdo, $bookingId, $email, 'Play-On Admin', 'admin_notification', $adminSubject, 'failed', $errMsg);
            }
        }
    }

    // ------------------------------------------------------------------------
    // 2. CUSTOMER CONFIRMATION EMAIL
    // ------------------------------------------------------------------------
    if (!empty($b['email']) && filter_var($b['email'], FILTER_VALIDATE_EMAIL)) {
        $customerSubject = "Booking Received - Play-On Mombasa [{$b['booking_ref']}]";
        $customerBody = renderCustomerEmailHtml($b, $frontendUrl);

        try {
            $mail = createMailer();
            $mail->isHTML(true);
            $mail->Subject = $customerSubject;
            $mail->Body    = $customerBody;
            $mail->AltBody = strip_tags(str_replace(['</tr>', '<br>', '</p>'], "\n", $customerBody));
            $mail->addAddress($b['email'], $b['full_name']);

            $mail->send();
            $results['customer_sent'] = true;
            logEmailAttempt($pdo, $bookingId, $b['email'], $b['full_name'], 'customer_confirmation', $customerSubject, 'sent');
        } catch (Throwable $e) {
            $errMsg = $e->getMessage();
            $results['errors'][] = "Customer email error: {$errMsg}";
            error_log("Customer booking email failed: {$errMsg}");
            logEmailAttempt($pdo, $bookingId, $b['email'], $b['full_name'], 'customer_confirmation', $customerSubject, 'failed', $errMsg);
        }
    }

    return $results;
}

/**
 * Render Admin Notification HTML Email
 */
function renderAdminEmailHtml(array $b, string $adminUrl): string {
    $ref   = htmlspecialchars($b['booking_ref']);
    $name  = htmlspecialchars($b['full_name']);
    $phone = htmlspecialchars($b['phone']);
    $email = htmlspecialchars($b['email']);
    $type  = htmlspecialchars($b['booking_type_label'] ?? $b['booking_type']);
    $date  = htmlspecialchars($b['booking_date']);
    $sess  = htmlspecialchars($b['session_time']);
    $kids  = (int)($b['children_count'] ?? 1);
    $adults = (int)($b['adults_count'] ?? 0);
    $total = htmlspecialchars($b['estimated_total']);
    $notes = !empty($b['notes']) ? nl2br(htmlspecialchars($b['notes'])) : '<em>None</em>';
    $timeNow = date('d M Y, h:i A');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Play-On Booking - {$ref}</title>
</head>
<body style="margin:0; padding:0; background-color:#F4FAF6; font-family:'Nunito',-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; color:#0B1E15;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#F4FAF6; padding:30px 10px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#FFFFFF; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06); max-width:600px; width:100%; border:1px solid #E2EFE7;">
          <!-- Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg, #00A651 0%, #064E2D 100%); padding:28px 30px; text-align:center;">
              <h1 style="color:#FFFFFF; margin:0 0 6px 0; font-size:24px; font-weight:800; letter-spacing:0.5px;">🎉 New Booking Received!</h1>
              <p style="color:#FFD400; margin:0; font-size:15px; font-weight:700;">Reference: {$ref}</p>
            </td>
          </tr>

          <!-- Summary Alert -->
          <tr>
            <td style="padding:24px 30px 10px;">
              <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                A new reservation request was just submitted on the <strong>Play-On Mombasa</strong> website on {$timeNow} (EAT).
              </p>
            </td>
          </tr>

          <!-- Details Table -->
          <tr>
            <td style="padding:0 30px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse; background:#F8FCFA; border-radius:10px; overflow:hidden; border:1px solid #E2EFE7; font-size:14px;">
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td width="38%" style="font-weight:700; color:#4A6357; padding:10px 14px;">Booking Reference</td>
                  <td style="font-weight:800; color:#00A651; padding:10px 14px; font-size:15px;">{$ref}</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Parent / Guardian</td>
                  <td style="font-weight:700; color:#0B1E15; padding:10px 14px;">{$name}</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Phone / WhatsApp</td>
                  <td style="padding:10px 14px;"><a href="https://wa.me/" style="color:#00A651; text-decoration:none; font-weight:700;">{$phone}</a></td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Email Address</td>
                  <td style="padding:10px 14px;"><a href="mailto:{$email}" style="color:#00A651; text-decoration:none;">{$email}</a></td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Booking Package / Type</td>
                  <td style="padding:10px 14px; font-weight:700; color:#064E2D;">{$type}</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Preferred Date</td>
                  <td style="padding:10px 14px; font-weight:700; color:#D64E1A;">{$date}</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Session Slot</td>
                  <td style="padding:10px 14px; font-weight:700;">{$sess}</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Guests</td>
                  <td style="padding:10px 14px;"><strong>{$kids}</strong> Children (1-15 yrs), <strong>{$adults}</strong> Adults</td>
                </tr>
                <tr style="border-bottom:1px solid #E2EFE7;">
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px;">Estimated Pass Total</td>
                  <td style="padding:10px 14px; font-size:16px; font-weight:800; color:#00A651;">{$total}</td>
                </tr>
                <tr>
                  <td style="font-weight:700; color:#4A6357; padding:10px 14px; vertical-align:top;">Notes / Requests</td>
                  <td style="padding:10px 14px; color:#222222; line-height:1.4;">{$notes}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Action Button -->
          <tr>
            <td align="center" style="padding:0 30px 30px;">
              <a href="{$adminUrl}/bookings.php?search={$ref}" style="display:inline-block; background:#00A651; color:#FFFFFF; padding:13px 28px; border-radius:50px; text-decoration:none; font-weight:800; font-size:15px; box-shadow:0 4px 12px rgba(0,166,81,0.3);">
                View & Manage in Admin Dashboard &rarr;
              </a>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#F0F7F3; padding:16px 30px; text-align:center; font-size:12px; color:#6B8074; border-top:1px solid #E2EFE7;">
              Play-On Mombasa • Mombasa Mall, 2nd Floor, Mwembe Tayari, Mombasa, Kenya<br>
              Inquiries: +254 780 611 074 • info@mombasamall.co.ke
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Render Customer Confirmation HTML Email
 */
function renderCustomerEmailHtml(array $b, string $siteUrl): string {
    $ref   = htmlspecialchars($b['booking_ref']);
    $name  = htmlspecialchars($b['full_name']);
    $type  = htmlspecialchars($b['booking_type_label'] ?? $b['booking_type']);
    $date  = htmlspecialchars($b['booking_date']);
    $sess  = htmlspecialchars($b['session_time']);
    $kids  = (int)($b['children_count'] ?? 1);
    $adults = (int)($b['adults_count'] ?? 0);
    $total = htmlspecialchars($b['estimated_total']);

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Play-On Booking Reservation - {$ref}</title>
</head>
<body style="margin:0; padding:0; background-color:#FFFDF8; font-family:'Nunito',-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; color:#0B1E15;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#FFFDF8; padding:30px 10px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#FFFFFF; border-radius:18px; overflow:hidden; box-shadow:0 10px 35px rgba(0,0,0,0.06); max-width:600px; width:100%; border:1px solid #EFE8D8;">
          
          <!-- Fun Header Banner -->
          <tr>
            <td style="background:linear-gradient(135deg, #00A651 0%, #064E2D 100%); padding:32px 30px; text-align:center;">
              <h1 style="color:#FFFFFF; margin:0 0 6px 0; font-size:26px; font-weight:800;">🎈 Karibu Play-On Mombasa!</h1>
              <p style="color:#FFD400; margin:0; font-size:16px; font-weight:700;">We've received your booking reservation!</p>
            </td>
          </tr>

          <!-- Greeting Body -->
          <tr>
            <td style="padding:28px 30px 12px;">
              <h2 style="font-size:18px; margin:0 0 10px; color:#064E2D;">Habari, {$name}! 👋</h2>
              <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#2C3E35;">
                Asante sana for choosing <strong>Play-On Mombasa</strong>! Your visit request is registered under booking reference 
                <span style="display:inline-block; background:#FFE033; color:#0B1E15; padding:2px 8px; border-radius:6px; font-weight:800;">{$ref}</span>.
              </p>
            </td>
          </tr>

          <!-- Booking Summary Box -->
          <tr>
            <td style="padding:0 30px 20px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="9" style="border-collapse:collapse; background:#F9FAF6; border-radius:12px; border:1px solid #E2EFE7; font-size:14px;">
                <tr style="border-bottom:1px solid #EAEFEA;">
                  <td width="40%" style="font-weight:700; color:#6B8074;">Booking Reference:</td>
                  <td style="font-weight:800; color:#00A651; font-size:15px;">{$ref}</td>
                </tr>
                <tr style="border-bottom:1px solid #EAEFEA;">
                  <td style="font-weight:700; color:#6B8074;">Activity / Package:</td>
                  <td style="font-weight:700; color:#0B1E15;">{$type}</td>
                </tr>
                <tr style="border-bottom:1px solid #EAEFEA;">
                  <td style="font-weight:700; color:#6B8074;">Visit Date:</td>
                  <td style="font-weight:800; color:#D64E1A;">{$date}</td>
                </tr>
                <tr style="border-bottom:1px solid #EAEFEA;">
                  <td style="font-weight:700; color:#6B8074;">Session Time:</td>
                  <td style="font-weight:700; color:#0B1E15;">{$sess}</td>
                </tr>
                <tr style="border-bottom:1px solid #EAEFEA;">
                  <td style="font-weight:700; color:#6B8074;">Guests:</td>
                  <td>{$kids} Child(ren), {$adults} Adult(s)</td>
                </tr>
                <tr>
                  <td style="font-weight:700; color:#6B8074;">Estimated Total:</td>
                  <td style="font-weight:800; color:#00A651; font-size:16px;">{$total}</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- What Happens Next & Important Visitor Tips -->
          <tr>
            <td style="padding:0 30px 24px;">
              <div style="background:#EBF7EE; border-left:4px solid #00A651; border-radius:8px; padding:14px 18px; margin-bottom:14px; font-size:14px; line-height:1.5; color:#0B4A23;">
                <strong style="font-size:15px; display:block; margin-bottom:4px;">⏱️ What Happens Next:</strong>
                Our team will review your booking and confirm your slot shortly via WhatsApp / Email. Keep your reference <strong>{$ref}</strong> handy when arriving.
              </div>

              <div style="background:#FFF9D6; border-left:4px solid #FFD400; border-radius:8px; padding:16px 18px; font-size:13px; line-height:1.5; color:#5A4300;">
                <strong style="font-size:14px; display:block; margin-bottom:6px;">🌟 Important Visit Information:</strong>
                • 📍 <strong>Location:</strong> Mombasa Mall, 2nd Floor, Nyali, Mombasa (Mwembe Tayari road access, ample mall parking available).<br>
                • 🧦 <strong>Free Grip Socks:</strong> Provided upon entry for all registered children.<br>
                • 🕒 <strong>Arrival:</strong> Please arrive 10-15 minutes prior to your session for quick wristband check-in.<br>
                • 💳 <strong>Payment:</strong> Admission pass is settled at our reception desk via M-Pesa or Cash.
              </div>
            </td>
          </tr>

          <!-- Quick WhatsApp & Contact CTAs -->
          <tr>
            <td align="center" style="padding:0 30px 30px;">
              <p style="margin:0 0 12px; font-size:14px; color:#4A6357;">Need urgent assistance or special arrangements?</p>
              <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
                <a href="https://wa.me/254780611074?text=Hi%20Play-On%20Team!%20My%20booking%20reference%20is%20{$ref}." style="display:inline-block; background:#25D366; color:#FFFFFF; padding:12px 24px; border-radius:50px; text-decoration:none; font-weight:800; font-size:14px; box-shadow:0 4px 12px rgba(37,211,102,0.25); margin:4px;">
                  💬 WhatsApp: 0780 611 074
                </a>
                <a href="mailto:playonkenya@gmail.com?subject=Inquiry%20regarding%20booking%20{$ref}" style="display:inline-block; background:#00A651; color:#FFFFFF; padding:12px 24px; border-radius:50px; text-decoration:none; font-weight:800; font-size:14px; box-shadow:0 4px 12px rgba(0,166,81,0.25); margin:4px;">
                  ✉️ Email: playonkenya@gmail.com
                </a>
              </div>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#F4FAF6; padding:18px 30px; text-align:center; font-size:12px; color:#6B8074; border-top:1px solid #E2EFE7;">
              <strong>Play-On Mombasa Kids Indoor Playground</strong><br>
              Mombasa Mall, 2nd Floor, Nyali, Mombasa, Kenya<br>
              WhatsApp: 0780 611 074 • Email: playonkenya@gmail.com • info@mombasamall.co.ke
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
