<?php
/**
 * SMTP test — open this URL once, then DELETE the file.
 * http://localhost/Software-Engineering-Final/MainFolder/campus_safety_system/test_mail.php
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/plain; charset=UTF-8');

echo "CampusSafe SMTP Test\n";
echo str_repeat('=', 40) . "\n";
echo "Host : " . SMTP_HOST . "\n";
echo "Port : " . SMTP_PORT . "\n";
echo "User : " . SMTP_USER . "\n";
echo "Pass : " . str_repeat('*', max(0, strlen(SMTP_PASS) - 4)) . substr(SMTP_PASS, -4) . " (" . strlen(SMTP_PASS) . " chars)\n\n";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug   = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = 'echo';
    $mail->isSMTP();
    $mail->Host        = SMTP_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = SMTP_USER;
    $mail->Password    = SMTP_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = SMTP_PORT;
    $mail->CharSet     = 'UTF-8';
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    $mail->addAddress(SMTP_USER); // send test email to yourself
    $mail->isHTML(false);
    $mail->Subject = 'CampusSafe SMTP Test';
    $mail->Body    = 'SMTP is working. This confirms verification and password-reset emails will be delivered.';

    $mail->send();
    echo "\n" . str_repeat('=', 40) . "\n";
    echo "RESULT: SUCCESS\n";
    echo "Check the inbox of " . SMTP_USER . " for the test email.\n";
    echo "You can now delete this file.\n";
} catch (Exception $e) {
    echo "\n" . str_repeat('=', 40) . "\n";
    echo "RESULT: FAILED\n";
    echo "Error : " . $mail->ErrorInfo . "\n\n";
    echo "--- Troubleshooting ---\n";
    echo "535 / authentication failed  → App Password is wrong. Regenerate at:\n";
    echo "  https://myaccount.google.com/apppasswords\n";
    echo "  (requires 2-Step Verification enabled on the Google account)\n\n";
    echo "Connection refused / timeout → Port 587 is blocked by firewall/antivirus.\n";
    echo "  Try disabling antivirus temporarily, or use port 465.\n\n";
    echo "Current SMTP_PASS length is " . strlen(SMTP_PASS) . " chars (must be 16).\n";
}
