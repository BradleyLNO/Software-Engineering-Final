<?php
/**
 * Email helper — wraps PHPMailer for verification and password-reset emails.
 *
 * SETUP: run `composer install` once in this directory to install PHPMailer.
 * Then set SMTP_HOST / SMTP_PORT / SMTP_USER / SMTP_PASS in config.php.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    error_log('PHPMailer not installed. Run `composer install` in ' . __DIR__);
    if (!function_exists('sendEmail')) {
        function sendEmail(string $to, string $name, string $subject, string $html): bool { return false; }
    }
    if (!function_exists('sendVerificationEmail')) {
        function sendVerificationEmail(string $to, string $name, string $token): bool { return false; }
    }
    if (!function_exists('sendPasswordResetEmail')) {
        function sendPasswordResetEmail(string $to, string $name, string $token): bool { return false; }
    }
    return;
}
require_once $autoload;

if (function_exists('sendEmail')) { return; }

function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = wordwrap(
            strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h2>'], "\n", $htmlBody)),
            72, "\n", true
        );

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer Error [' . $toEmail . ']: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
{
    $url     = BASE_URL . '/verify_email.php?token=' . rawurlencode($token);
    $name    = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $appName = APP_NAME;

    $html = _buildEmailHtml(
        'Verify Your Email Address',
        "Hi {$name},<br><br>
        Thank you for registering with <strong>{$appName}</strong>.
        Click the button below to verify your email address and activate your account.<br><br>
        <strong>This link expires in 24 hours.</strong>",
        $url,
        'Verify My Email',
        "If you did not create an account on {$appName}, you can safely ignore this email."
    );

    return sendEmail($toEmail, $toName, "{$appName} — Please Verify Your Email", $html);
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool
{
    $url     = BASE_URL . '/reset_password.php?token=' . rawurlencode($token);
    $name    = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $appName = APP_NAME;

    $html = _buildEmailHtml(
        'Reset Your Password',
        "Hi {$name},<br><br>
        We received a request to reset your <strong>{$appName}</strong> password.
        Click the button below to choose a new password.<br><br>
        <strong>This link expires in 1 hour.</strong>",
        $url,
        'Reset My Password',
        "If you did not request a password reset, please ignore this email — your password will not change."
    );

    return sendEmail($toEmail, $toName, "{$appName} — Password Reset Request", $html);
}

function _buildEmailHtml(
    string $title,
    string $bodyHtml,
    string $btnUrl,
    string $btnText,
    string $footerNote
): string {
    $appName  = APP_NAME;
    $year     = date('Y');
    $urlEsc   = htmlspecialchars($btnUrl,  ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title,   ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$titleEsc}</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Segoe UI,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:40px 16px;">
  <tr><td align="center">
    <table width="580" cellpadding="0" cellspacing="0"
           style="background:#ffffff;border-radius:10px;overflow:hidden;
                  box-shadow:0 4px 16px rgba(0,0,0,0.10);max-width:100%;">
      <tr>
        <td style="background:linear-gradient(135deg,#C0392B,#96281B);
                   padding:28px 40px;text-align:center;">
          <div style="font-size:32px;margin-bottom:6px;">&#x1F6E1;&#xFE0F;</div>
          <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;">{$appName}</h1>
          <p style="color:rgba(255,255,255,0.75);margin:4px 0 0;font-size:12px;">
            Campus Emergency Response System
          </p>
        </td>
      </tr>
      <tr>
        <td style="padding:40px 40px 24px;">
          <h2 style="color:#1a1a2e;font-size:20px;font-weight:700;margin:0 0 16px;">
            {$titleEsc}
          </h2>
          <p style="color:#444444;font-size:15px;line-height:1.75;margin:0 0 28px;">
            {$bodyHtml}
          </p>
          <div style="text-align:center;margin:28px 0;">
            <a href="{$urlEsc}"
               style="display:inline-block;background:#C0392B;color:#ffffff;
                      text-decoration:none;font-size:15px;font-weight:600;
                      padding:14px 42px;border-radius:6px;letter-spacing:0.3px;">
              {$btnText}
            </a>
          </div>
          <p style="color:#999999;font-size:12px;margin:20px 0 0;line-height:1.6;">
            If the button does not work, copy and paste this link into your browser:<br>
            <a href="{$urlEsc}" style="color:#C0392B;word-break:break-all;">{$urlEsc}</a>
          </p>
        </td>
      </tr>
      <tr>
        <td style="background:#f8f9fa;border-top:1px solid #eeeeee;
                   padding:18px 40px;text-align:center;">
          <p style="color:#aaaaaa;font-size:12px;margin:0;line-height:1.6;">
            {$footerNote}<br>
            &copy; {$year} {$appName}. All rights reserved.
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
