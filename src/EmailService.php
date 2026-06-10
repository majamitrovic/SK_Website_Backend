<?php

namespace App;

final class EmailService
{
    /**
     * Send payment success notification
     */
    public static function sendPaymentSuccess(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to) {
            return false;
        }

        $subject = MailTemplates::getSuccessSubject($payment, $result);
        $body = MailTemplates::getSuccessBody($payment, $result);
        
        return self::send($to, $subject, $body);
    }

    /**
     * Send payment failure notification
     */
    public static function sendPaymentFailure(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to) {
            return false;
        }

        $subject = MailTemplates::getFailureSubject($payment, $result);
        $body = MailTemplates::getFailureBody($payment, $result);
        
        return self::send($to, $subject, $body);
    }

    /**
     * Send recurring payment schedule confirmation
     */
    public static function sendScheduleConfirmation(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to || !$result['scheduleId']) {
            return false;
        }

        $subject = MailTemplates::getScheduleSubject($payment, $result);
        $body = MailTemplates::getScheduleBody($payment, $result);
        
        return self::send($to, $subject, $body);
    }

    /**
     * Send callback notification (for admin/internal use)
     */
    public static function sendCallbackNotification(array $callbackData)
    {
        $adminEmail = Config::get('PAYMENT_ADMIN_EMAIL');
        if (!$adminEmail) {
            return false;
        }

        $subject = MailTemplates::getCallbackSubject($callbackData);
        $body = MailTemplates::getCallbackBody($callbackData);
        
        return self::send($adminEmail, $subject, $body);
    }

    /**
     * Send email using PHP mail function or configured SMTP
     */
    private static function send($to, $subject, $body)
    {
        $smtpHost = Config::get('MAIL_SMTP_HOST');
        
        if ($smtpHost) {
            return self::sendViaSMTP($to, $subject, $body);
        } else {
            return self::sendViaPhpMail($to, $subject, $body);
        }
    }

    /**
     * Send email via PHP mail function
     */
    private static function sendViaPhpMail($to, $subject, $body)
    {
        $from = Config::get('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = Config::get('MAIL_FROM_NAME', 'Payment System');
        
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: SK Website Payment System\r\n";
        
        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send email via SMTP
     */
    private static function sendViaSMTP($to, $subject, $body)
    {
        // Requires PHP with SMTP/IMAP extensions or PHPMailer library
        // This is a placeholder - implement based on your SMTP library preference
        
        $host = Config::get('MAIL_SMTP_HOST');
        $port = Config::get('MAIL_SMTP_PORT', 587);
        $username = Config::get('MAIL_SMTP_USERNAME');
        $password = Config::get('MAIL_SMTP_PASSWORD');
        $encryption = Config::get('MAIL_SMTP_ENCRYPTION', 'tls');
        
        // TODO: Implement SMTP sending using PHPMailer or similar
        // For now, fall back to PHP mail
        return self::sendViaPhpMail($to, $subject, $body);
    }
}
