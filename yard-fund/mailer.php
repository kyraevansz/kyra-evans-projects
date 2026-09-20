<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailTemplate(
    string $toEmail,
    string $toName,
    string $subject,
    string $heading,
    string $messageBody,
    string $buttonText = "",
    string $buttonLink = ""
): bool|string {

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $buttonHtml = "";
        if ($buttonText && $buttonLink) {
            $buttonHtml = '
                <div style="margin:30px 0;text-align:center;">
                    <a href="' . htmlspecialchars($buttonLink) . '" style="
                        background:#f98a1e;color:#ffffff;text-decoration:none;
                        padding:14px 24px;border-radius:10px;display:inline-block;font-weight:bold;">
                        ' . htmlspecialchars($buttonText) . '
                    </a>
                </div>';
        }

        $mail->Body = '
        <!DOCTYPE html><html><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f7fc;font-family:Arial,sans-serif;color:#16233f;">
            <div style="max-width:640px;margin:40px auto;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 8px 24px rgba(21,48,103,0.08);">
                <div style="background:linear-gradient(135deg,#1f4aa8 0%,#356dd2 100%);padding:28px;text-align:center;">
                    <h1 style="margin:0;color:#fff;font-size:28px;">' . APP_NAME . '</h1>
                    <p style="margin:10px 0 0;color:rgba(255,255,255,0.9);">Plant a seed, grow a future.</p>
                </div>
                <div style="padding:36px 30px;">
                    <h2 style="margin-top:0;color:#214a9b;">' . htmlspecialchars($heading) . '</h2>
                    <div style="font-size:16px;line-height:1.7;color:#3a4b68;">' . $messageBody . '</div>
                    ' . $buttonHtml . '
                </div>
                <div style="background:#f5f8fe;padding:20px 30px;font-size:14px;color:#64748b;text-align:center;">
                    This message was sent by ' . APP_NAME . '.
                </div>
            </div>
        </body></html>';

        $mail->AltBody = strip_tags($heading . " " . preg_replace('/<br\s*\/?>/i', "\n", $messageBody));
        $mail->send();
        return true;

    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}
