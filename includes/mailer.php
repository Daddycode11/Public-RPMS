<?php
/**
 * Reusable PHPMailer helper for RPMS
 * Usage: sendRpmsMail($toEmail, $toName, $subject, $htmlBody, $plainBody)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendRpmsMail(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): array
{
    if (getenv('RPMS_MAIL_ENABLED') === '0') return ['success'=>false,'message'=>'Email delivery is disabled.'];
    $localMail = is_file(__DIR__ . '/../config/smtp.local.php') ? require __DIR__ . '/../config/smtp.local.php' : [];
    $mail = new PHPMailer(true);
    $mail->Timeout = 10;
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('RPMS_SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('RPMS_SMTP_USER') ?: ($localMail['Username'] ?? '');
        $mail->Password   = getenv('RPMS_SMTP_PASSWORD') ?: ($localMail['Password'] ?? '');
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('eutech253@gmail.com', 'RPMS - San Jose Public Market');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

/**
 * Build a styled RPMS email template
 */
function rpmsEmailTemplate(string $heading, string $bodyContent, string $footerNote = ''): string
{
    $footer = $footerNote ?: 'This is an automated message from RPMS. Please do not reply directly to this email.';

    return '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f0f4f1;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f1;padding:32px 0;">
            <tr><td align="center">
                <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid rgba(14,158,82,0.12);">

                    <!-- Header -->
                    <tr>
                        <td style="background:#0d1f14;padding:24px 32px;text-align:center;">
                            <h1 style="margin:0;font-size:22px;color:#ffffff;font-weight:700;">RPMS</h1>
                            <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,0.5);letter-spacing:0.05em;">San Jose Public Market</p>
                        </td>
                    </tr>

                    <!-- Heading -->
                    <tr>
                        <td style="padding:28px 32px 0;">
                            <h2 style="margin:0;font-size:18px;color:#0d1f14;font-weight:700;">' . $heading . '</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:16px 32px 28px;font-size:14px;line-height:1.7;color:#3a5042;">
                            ' . $bodyContent . '
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 32px;background:#f7faf8;border-top:1px solid #edf2ee;font-size:12px;color:#6b8878;text-align:center;">
                            ' . htmlspecialchars($footer) . '
                        </td>
                    </tr>

                </table>
            </td></tr>
        </table>
    </body>
    </html>';
}
