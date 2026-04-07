<?php
// includes/Email.php  –  SendGrid email helper for the COG Management System
require_once __DIR__ . '/../config/env.php';

class Email {

    private static function apiKey(): string {
        return env('SENDGRID_API_KEY', '');
    }

    private static function fromEmail(): string {
        return env('MAIL_FROM_ADDRESS', 'noreply@olshco.edu');
    }

    private static function fromName(): string {
        return env('MAIL_FROM_NAME', 'OLSHCO COG System');
    }

    private static function baseUrl(): string {
        return env('APP_URL', 'http://localhost:8000');
    }

    /**
     * Send an email via SendGrid Web API v3.
     * Returns true on success, false on failure.
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $plainBody = ''
    ): bool {
        $apiKey = self::apiKey();

        if (empty($apiKey) || $apiKey === 'your_sendgrid_api_key_here') {
            error_log("[Email] SendGrid API key not configured. Skipping email to {$toEmail}.");
            return false;
        }

        if (empty($plainBody)) {
            $plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
            $plainBody = html_entity_decode(preg_replace('/\s+/', ' ', $plainBody));
        }

        $payload = json_encode([
            'personalizations' => [[
                'to' => [['email' => $toEmail, 'name'  => $toName]],
            ]],
            'from'    => ['email' => self::fromEmail(), 'name' => self::fromName()],
            'subject' => $subject,
            'content' => [
                ['type' => 'text/plain', 'value' => $plainBody],
                ['type' => 'text/html',  'value' => $htmlBody],
            ],
        ]);

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[Email] cURL error sending to {$toEmail}: {$curlError}");
            return false;
        }

        // SendGrid returns 202 Accepted on success
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log("[Email] SendGrid error ({$httpCode}) sending to {$toEmail}: {$response}");
        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Templated email methods
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Welcome email sent after student registration.
     */
    public static function sendWelcome(string $email, string $fullName, string $studentId): bool {
        $subject = '🎓 Welcome to OLSHCO COG Management System';
        $html    = self::wrap($fullName, '
            <p>Welcome to the <strong>OLSHCO Certificate of Grades (COG) Management System</strong>!</p>
            <p>Your student account has been created successfully.</p>

            <div class="info-box">
                <div class="info-row"><span class="info-label">Name</span><span class="info-value">' . htmlspecialchars($fullName) . '</span></div>
                <div class="info-row"><span class="info-label">Student ID</span><span class="info-value">' . htmlspecialchars($studentId) . '</span></div>
                <div class="info-row"><span class="info-label">Email</span><span class="info-value">' . htmlspecialchars($email) . '</span></div>
            </div>

            <p>You can now log in and request your Certificate of Grades online.</p>
            <div style="text-align:center;margin:28px 0;">
                <a href="' . self::baseUrl() . '/index.php" class="btn">Login to Your Account</a>
            </div>
            <p style="color:#888;font-size:13px;">Processing time is <strong>2–3 working days</strong>. Fee is <strong>₱50.00 per copy</strong>.</p>
        ');
        return self::send($email, $fullName, $subject, $html);
    }

    /**
     * Confirmation email when a COG request is submitted.
     */
    public static function sendRequestConfirmation(
        string $email,
        string $fullName,
        string $requestNumber,
        string $purpose,
        int    $copies,
        float  $amount
    ): bool {
        $subject = "📄 COG Request Submitted – {$requestNumber}";
        $html    = self::wrap($fullName, '
            <p>Your Certificate of Grades request has been <strong>successfully submitted</strong>.</p>

            <div class="info-box">
                <div class="info-row"><span class="info-label">Reference No.</span><span class="info-value">' . htmlspecialchars($requestNumber) . '</span></div>
                <div class="info-row"><span class="info-label">Purpose</span><span class="info-value">' . htmlspecialchars($purpose) . '</span></div>
                <div class="info-row"><span class="info-label">No. of Copies</span><span class="info-value">' . $copies . '</span></div>
                <div class="info-row"><span class="info-label">Total Amount</span><span class="info-value">₱' . number_format($amount, 2) . '</span></div>
                <div class="info-row"><span class="info-label">Processing Time</span><span class="info-value">2–3 working days</span></div>
            </div>

            <p><strong>Next steps:</strong></p>
            <ul style="color:#555;padding-left:20px;line-height:1.8;">
                <li>Pay online via GCash or proceed to the Registrar\'s Office.</li>
                <li>Bring a valid ID and your school ID when claiming.</li>
                <li>You will receive an email when your COG is ready for pickup.</li>
            </ul>
            <div style="text-align:center;margin:28px 0;">
                <a href="' . self::baseUrl() . '/student/my_requests.php" class="btn">View My Requests</a>
            </div>
        ');
        return self::send($email, $fullName, $subject, $html);
    }

    /**
     * Status update email when admin changes request status.
     */
    public static function sendStatusUpdate(
        string $email,
        string $fullName,
        string $requestNumber,
        string $newStatus,
        string $adminNotes = ''
    ): bool {
        $labels = [
            'processing' => ['🔄 Request Being Processed',     'Your COG request is <strong>now being processed</strong> by the Registrar\'s Office.'],
            'ready'      => ['✅ COG Ready for Pickup!',        'Great news! Your Certificate of Grades is <strong>ready for pickup</strong> at the Registrar\'s Office.'],
            'released'   => ['🎉 COG Successfully Released',   'Your Certificate of Grades has been <strong>successfully released</strong>. Thank you!'],
            'pending'    => ['⏳ Request Moved to Pending',     'Your COG request has been moved back to <strong>pending</strong> status.'],
        ];

        [$subject, $statusText] = $labels[$newStatus] ?? ["📋 Request Status Updated – {$requestNumber}", "Your request status has been updated to <strong>{$newStatus}</strong>."];
        $subject .= " – {$requestNumber}";

        $notesHtml = '';
        if (!empty($adminNotes)) {
            $clean = trim(preg_replace('/\[xendit_\w+:[^\]]*\]/', '', $adminNotes));
            if ($clean) {
                $notesHtml = '<div class="info-box" style="background:#fff9e6;border-left-color:#f0ad4e;">
                    <p style="margin:0;font-size:13px;"><strong>📝 Note from Registrar:</strong><br>' . nl2br(htmlspecialchars($clean)) . '</p>
                </div>';
            }
        }

        $pickupNote = $newStatus === 'ready' ? '
            <div class="info-box" style="background:#e6f9ef;border-left-color:#28a745;">
                <p style="margin:0;font-size:13px;"><strong>📍 Pickup Instructions:</strong><br>
                Please proceed to the <strong>Registrar\'s Office</strong> during office hours.<br>
                Bring your <strong>valid ID</strong> and <strong>school ID</strong>.</p>
            </div>' : '';

        $html = self::wrap($fullName, '
            <p>' . $statusText . '</p>
            <div class="info-box">
                <div class="info-row"><span class="info-label">Reference No.</span><span class="info-value">' . htmlspecialchars($requestNumber) . '</span></div>
                <div class="info-row"><span class="info-label">New Status</span><span class="info-value"><strong>' . ucfirst($newStatus) . '</strong></span></div>
                <div class="info-row"><span class="info-label">Updated At</span><span class="info-value">' . date('F d, Y h:i A') . '</span></div>
            </div>
            ' . $pickupNote . $notesHtml . '
            <div style="text-align:center;margin:28px 0;">
                <a href="' . self::baseUrl() . '/student/my_requests.php" class="btn">View My Requests</a>
            </div>
        ');
        return self::send($email, $fullName, $subject, $html);
    }

    /**
     * Payment confirmation email.
     */
    public static function sendPaymentConfirmation(
        string $email,
        string $fullName,
        string $requestNumber,
        float  $amount,
        string $method = 'GCash'
    ): bool {
        $subject = "💳 Payment Confirmed – {$requestNumber}";
        $html    = self::wrap($fullName, '
            <p>Your payment has been <strong>successfully confirmed</strong>. Your COG is now being processed.</p>

            <div class="info-box">
                <div class="info-row"><span class="info-label">Reference No.</span><span class="info-value">' . htmlspecialchars($requestNumber) . '</span></div>
                <div class="info-row"><span class="info-label">Amount Paid</span><span class="info-value">₱' . number_format($amount, 2) . '</span></div>
                <div class="info-row"><span class="info-label">Payment Method</span><span class="info-value">' . htmlspecialchars($method) . '</span></div>
                <div class="info-row"><span class="info-label">Payment Date</span><span class="info-value">' . date('F d, Y h:i A') . '</span></div>
                <div class="info-row"><span class="info-label">Status</span><span class="info-value"><strong style="color:#28a745;">Paid ✓</strong></span></div>
            </div>

            <p>We will notify you by email when your COG is <strong>ready for pickup</strong>.</p>
            <div style="text-align:center;margin:28px 0;">
                <a href="' . self::baseUrl() . '/student/my_requests.php" class="btn">View My Requests</a>
            </div>
        ');
        return self::send($email, $fullName, $subject, $html);
    }

    /**
     * Password changed security alert email.
     */
    public static function sendPasswordChanged(string $email, string $fullName): bool {
        $subject = '🔐 Password Changed – OLSHCO COG System';
        $html    = self::wrap($fullName, '
            <p>Your account password was <strong>recently changed</strong>.</p>
            <p>If you made this change, you can safely ignore this email.</p>
            <div class="info-box" style="background:#fff0f0;border-left-color:#dc3545;">
                <p style="margin:0;color:#721c24;font-size:13px;">
                    ⚠️ <strong>If you did NOT change your password</strong>, please contact the Registrar\'s Office immediately or reset your password.
                </p>
            </div>
            <div class="info-box">
                <div class="info-row"><span class="info-label">Account</span><span class="info-value">' . htmlspecialchars($email) . '</span></div>
                <div class="info-row"><span class="info-label">Changed At</span><span class="info-value">' . date('F d, Y h:i A') . '</span></div>
            </div>
        ');
        return self::send($email, $fullName, $subject, $html);
    }

    /**
     * Admin notification when a new COG request is submitted.
     */
    public static function sendAdminNewRequest(
        string $adminEmail,
        string $adminName,
        string $studentName,
        string $studentId,
        string $requestNumber,
        string $purpose,
        int    $copies,
        float  $amount
    ): bool {
        $subject = "🔔 New COG Request – {$requestNumber}";
        $html    = self::wrap($adminName, '
            <p>A new Certificate of Grades request has been submitted and requires your attention.</p>

            <div class="info-box">
                <div class="info-row"><span class="info-label">Request No.</span><span class="info-value">' . htmlspecialchars($requestNumber) . '</span></div>
                <div class="info-row"><span class="info-label">Student</span><span class="info-value">' . htmlspecialchars($studentName) . '</span></div>
                <div class="info-row"><span class="info-label">Student ID</span><span class="info-value">' . htmlspecialchars($studentId) . '</span></div>
                <div class="info-row"><span class="info-label">Purpose</span><span class="info-value">' . htmlspecialchars($purpose) . '</span></div>
                <div class="info-row"><span class="info-label">Copies</span><span class="info-value">' . $copies . '</span></div>
                <div class="info-row"><span class="info-label">Amount</span><span class="info-value">₱' . number_format($amount, 2) . '</span></div>
                <div class="info-row"><span class="info-label">Submitted At</span><span class="info-value">' . date('F d, Y h:i A') . '</span></div>
            </div>

            <div style="text-align:center;margin:28px 0;">
                <a href="' . self::baseUrl() . '/admin/requests.php" class="btn">Manage Requests</a>
            </div>
        ');
        return self::send($adminEmail, $adminName, $subject, $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTML email wrapper template
    // ──────────────────────────────────────────────────────────────────────────

    private static function wrap(string $recipientName, string $bodyContent): string {
        $schoolName = env('APP_NAME', 'OLSHCO COG System');
        $year       = date('Y');
        $firstName  = explode(' ', $recipientName)[0];

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0; padding:0; background:#f4f4f4; font-family: "Segoe UI", Arial, sans-serif; font-size:15px; color:#333; }
  .wrapper { max-width:600px; margin:0 auto; padding:30px 16px; }
  .card { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
  .header { background:linear-gradient(135deg,#800000,#660000); padding:32px 36px; text-align:center; }
  .header h1 { color:#fff; margin:0 0 4px; font-size:22px; font-weight:700; letter-spacing:0.5px; }
  .header p { color:rgba(255,255,255,0.82); margin:0; font-size:13px; }
  .body { padding:32px 36px; }
  .greeting { font-size:18px; font-weight:600; margin-bottom:16px; color:#222; }
  .info-box { background:#f8f9fa; border-left:4px solid #800000; border-radius:8px; padding:16px 20px; margin:20px 0; }
  .info-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #eee; font-size:14px; }
  .info-row:last-child { border-bottom:none; }
  .info-label { color:#888; flex-basis:40%; }
  .info-value { color:#333; font-weight:500; text-align:right; flex-basis:58%; }
  .btn { display:inline-block; background:linear-gradient(135deg,#800000,#660000); color:#fff !important; text-decoration:none; padding:12px 32px; border-radius:30px; font-weight:700; font-size:15px; }
  ul { color:#555; padding-left:20px; line-height:1.8; }
  .footer { background:#f8f9fa; padding:20px 36px; text-align:center; font-size:12px; color:#999; border-top:1px solid #eee; }
  .footer a { color:#800000; text-decoration:none; }
  p { line-height:1.7; color:#555; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <h1>🎓 ' . htmlspecialchars($schoolName) . '</h1>
      <p>Certificate of Grades Management System</p>
    </div>
    <div class="body">
      <div class="greeting">Hi, ' . htmlspecialchars($firstName) . '!</div>
      ' . $bodyContent . '
    </div>
    <div class="footer">
      <p>This is an automated message from the <strong>' . htmlspecialchars($schoolName) . '</strong>.<br>
      Please do not reply to this email.<br>
      For assistance, contact the Registrar\'s Office.</p>
      <p>&copy; ' . $year . ' OLSHCO. All rights reserved.</p>
    </div>
  </div>
</div>
</body>
</html>';
    }
}