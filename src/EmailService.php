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
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Payment success email not sent - no email address',
                    ['payment_id' => $payment['id'] ?? null],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getSuccessSubject($payment, $result);
        $body = MailTemplates::getSuccessBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'payment_success', [
            'payment_id' => $payment['id'] ?? null,
            'customer_email' => $to,
            'amount' => $payment['amount'] ?? null,
        ]);

        return $sent;
    }

    /**
     * Send payment failure notification
     */
    public static function sendPaymentFailure(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Payment failure email not sent - no email address',
                    ['payment_id' => $payment['id'] ?? null],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getFailureSubject($payment, $result);
        $body = MailTemplates::getFailureBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'payment_failure', [
            'payment_id' => $payment['id'] ?? null,
            'customer_email' => $to,
            'amount' => $payment['amount'] ?? null,
            'errors' => $result['errors'] ?? [],
        ]);

        return $sent;
    }

    /**
     * Send recurring payment schedule confirmation
     */
    public static function sendScheduleConfirmation(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to || !($result['scheduleId'] ?? null)) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Schedule confirmation email not sent - missing email or schedule ID',
                    [
                        'payment_id' => $payment['id'] ?? null,
                        'has_email' => !empty($to),
                        'has_schedule_id' => !empty($result['scheduleId'] ?? null),
                    ],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getScheduleSubject($payment, $result);
        $body = MailTemplates::getScheduleBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'schedule_confirmation', [
            'payment_id' => $payment['id'] ?? null,
            'customer_email' => $to,
            'schedule_id' => $result['scheduleId'] ?? null,
            'amount' => $payment['amount'] ?? null,
        ]);

        return $sent;
    }

    /**
     * Send callback notification (for admin/internal use)
     */
    public static function sendCallbackNotification(array $callbackData)
    {
        $adminEmail = Config::get('PAYMENT_ADMIN_EMAIL');
        if (!$adminEmail) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Callback notification email not sent - no admin email configured',
                    ['configured_admin_email' => false],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getCallbackSubject($callbackData);
        $body = MailTemplates::getCallbackBody($callbackData);
        
        $sent = self::send($adminEmail, $subject, $body, 'callback_notification', [
            'admin_email' => $adminEmail,
            'transaction_id' => $callbackData['merchantTransactionId'] ?? null,
            'payment_status' => $callbackData['result'] ?? null,
        ]);

        return $sent;
    }

    /**
     * Send email using PHP mail function or configured SMTP
     */
    private static function send($to, $subject, $body, $emailType = 'generic', array $context = [])
    {
        // Validate email address
        if (!self::validateEmail($to)) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    "Invalid email address: {$to}",
                    array_merge($context, [
                        'email_type' => $emailType,
                        'invalid_email' => $to,
                    ]),
                    'warning'
                );
            }
            return false;
        }

        $smtpHost = Config::get('MAIL_SMTP_HOST');
        
        try {
            if ($smtpHost) {
                $result = self::sendViaSMTP($to, $subject, $body);
            } else {
                $result = self::sendViaPhpMail($to, $subject, $body);
            }

            // Log successful send
            if ($result && Config::bool('ENABLE_LOGGING')) {
                Logger::logTransaction(array_merge([
                    'type' => 'email_sent',
                    'email_type' => $emailType,
                    'recipient' => $to,
                    'subject' => $subject,
                    'success' => true,
                    'method' => $smtpHost ? 'smtp' : 'php_mail',
                ], $context));
            }

            // Log failed send
            if (!$result && Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    "Failed to send {$emailType} email to {$to}",
                    array_merge($context, [
                        'email_type' => $emailType,
                        'recipient' => $to,
                        'subject' => $subject,
                        'method' => $smtpHost ? 'smtp' : 'php_mail',
                    ]),
                    'error'
                );
            }

            return $result;

        } catch (Throwable $exception) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    "Email sending exception ({$emailType}): " . $exception->getMessage(),
                    array_merge($context, [
                        'email_type' => $emailType,
                        'recipient' => $to,
                        'subject' => $subject,
                        'exception' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                    ]),
                    'critical'
                );
            }
            return false;
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
        
        return @mail($to, $subject, $body, $headers);
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

    /**
     * Validate email address format
     */
    private static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
