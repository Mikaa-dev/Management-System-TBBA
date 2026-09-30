<?php
/** Security email sender with authenticated SMTP and native mail support. */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

use PHPMailer\PHPMailer\PHPMailer;

class SecurityMailer
{
    public static function sendPasswordReset(array $user, string $link): bool
    {
        $subject = 'Reset your TBBA Portal password';
        $body = "Hello {$user['name']},\n\nA password reset was requested for your TBBA Portal account.\n\nReset link (valid for 30 minutes):\n{$link}\n\nIf you did not request this, ignore this email and inform the system administrator.\n";
        return self::send((string)$user['email'], $subject, $body);
    }

    public static function sendLoginAlert(array $user, string $ip, string $device): bool
    {
        $subject = 'New TBBA Portal sign-in detected';
        $body = "Hello {$user['name']},\n\nA sign-in from a new device or network was detected.\n\nTime: " . date('d M Y, h:i A') . "\nIP address: {$ip}\nDevice: {$device}\n\nIf this was not you, reset your password and contact the system administrator immediately.\n";
        return self::send((string)$user['email'], $subject, $body);
    }

    private static function send(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $transport = strtolower(trim(tbba_env('MAIL_TRANSPORT', 'mail')));
        if ($transport === 'smtp') {
            return self::sendWithSmtp($to, $subject, $body);
        }
        if ($transport !== 'mail') {
            error_log('[SecurityMailer] Unsupported MAIL_TRANSPORT value.');
            return false;
        }
        return self::sendWithNativeMail($to, $subject, $body);
    }

    private static function sendWithSmtp(string $to, string $subject, string $body): bool
    {
        $host = trim(tbba_env('MAIL_HOST'));
        $username = trim(tbba_env('MAIL_USERNAME'));
        $password = tbba_env('MAIL_PASSWORD');
        $fromAddress = trim(tbba_env('MAIL_FROM_ADDRESS', SECURITY_MAIL_FROM));
        $fromName = trim(tbba_env('MAIL_FROM_NAME', 'TBBA Security')) ?: 'TBBA Security';
        $port = (int)tbba_env('MAIL_PORT', '587');
        $encryption = strtolower(trim(tbba_env('MAIL_ENCRYPTION', 'tls')));

        if ($host === '' || $username === '' || $password === '') {
            error_log('[SecurityMailer] SMTP is selected but host, username, or password is missing.');
            return false;
        }
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            error_log('[SecurityMailer] MAIL_FROM_ADDRESS is not a valid email address.');
            return false;
        }
        if ($port < 1 || $port > 65535) {
            error_log('[SecurityMailer] MAIL_PORT is invalid.');
            return false;
        }

        // Google displays app passwords in four groups; SMTP expects the 16 characters.
        if (strcasecmp($host, 'smtp.gmail.com') === 0) {
            $password = preg_replace('/\s+/', '', $password) ?? $password;
        }

        $secureMode = match ($encryption) {
            'tls', 'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            '', 'none' => '',
            default => null,
        };
        if ($secureMode === null) {
            error_log('[SecurityMailer] MAIL_ENCRYPTION must be tls, ssl, or none.');
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $secureMode;
            $mail->SMTPAutoTLS = $secureMode !== '';
            $mail->Timeout = 20;

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(false);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            error_log('[SecurityMailer] SMTP delivery failed: ' . $mail->ErrorInfo);
            return false;
        }
    }

    private static function sendWithNativeMail(string $to, string $subject, string $body): bool
    {
        $fromAddress = trim(tbba_env('MAIL_FROM_ADDRESS', SECURITY_MAIL_FROM));
        $fromName = trim(tbba_env('MAIL_FROM_NAME', 'TBBA Security')) ?: 'TBBA Security';
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            error_log('[SecurityMailer] Native mail sender address is invalid.');
            return false;
        }
        $safeName = str_replace(["\r", "\n"], '', $fromName);
        $headers = "From: {$safeName} <{$fromAddress}>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        return @mail($to, $subject, $body, $headers);
    }
}
