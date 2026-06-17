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
                    ['payment_id' => $result['merchantTransactionId'] ?? null],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getSuccessSubject($payment, $result);
        $body = MailTemplates::getSuccessBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'payment_success', [
            'payment_id' => $result['merchantTransactionId'] ?? null,
            'customer_email' => $to,
            'amount' => $result['amount'] ?? null,
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
                    ['payment_id' => $result['merchantTransactionId'] ?? null],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getFailureSubject($payment, $result);
        $body = MailTemplates::getFailureBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'payment_failure', [
            'payment_id' => $result['merchantTransactionId'] ?? null,
            'customer_email' => $to,
            'amount' => $result['amount'] ?? null,
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
        if (!$to || !($result['scheduledData']['scheduleId'] ?? null)) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Schedule confirmation email not sent - missing email or schedule ID',
                    [
                        'payment_id' => $result['merchantTransactionId'] ?? null,
                        'has_email' => !empty($to),
                        'has_schedule_id' => !empty($result['scheduledData']['scheduleId'] ?? null),
                    ],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getScheduleSubject($payment, $result);
        $body = MailTemplates::getScheduleBody($payment, $result);
        
        $sent = self::send($to, $subject, $body, 'schedule_confirmation', [
            'payment_id' => $result['merchantTransactionId'] ?? null,
            'customer_email' => $to,
            'schedule_id' => $result['scheduledData']['scheduleId'] ?? null,
            'amount' => $result['amount'] ?? null,
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
     * Send schedule cancellation confirmation to customer
     */
    public static function sendCancellationConfirmation(array $payment, array $result)
    {
        $to = $payment['email'] ?? '';
        if (!$to) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'Cancellation confirmation email not sent - no email address',
                    ['payment_id' => $result['merchantTransactionId'] ?? null],
                    'warning'
                );
            }
            return false;
        }

        $subject = MailTemplates::getScheduleSubject($payment, $result);
        $body = MailTemplates::getScheduleBody($payment, $result);

        $sent = self::send($to, $subject, $body, 'schedule_cancellation', [
            'payment_id' => $result['merchantTransactionId'] ?? null,
            'customer_email' => $to,
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

            // Log failed send with detailed error information
            if (!$result && Config::bool('ENABLE_LOGGING')) {
                $lastError = error_get_last();
                $errorDetails = array_merge($context, [
                    'email_type' => $emailType,
                    'recipient' => $to,
                    'subject' => $subject,
                    'method' => $smtpHost ? 'smtp' : 'php_mail',
                ]);
                
                // Add PHP error details if available
                if ($lastError) {
                    $errorDetails['php_error_type'] = $lastError['type'];
                    $errorDetails['php_error_message'] = $lastError['message'];
                    $errorDetails['php_error_file'] = $lastError['file'];
                    $errorDetails['php_error_line'] = $lastError['line'];
                }
                
                // Add mail configuration for debugging
                $errorDetails['mail_from'] = Config::get('MAIL_FROM_ADDRESS');
                $errorDetails['mail_host'] = $smtpHost ? $smtpHost : 'localhost (sendmail)';
                $errorDetails['mail_driver'] = Config::get('MAIL_DRIVER', 'default');
                
                Logger::logError(
                    "Failed to send {$emailType} email to {$to}" . ($lastError ? " - {$lastError['message']}" : ''),
                    $errorDetails,
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
        
        // Clear any previous errors
        error_clear_last();
        
        // Try to send email (don't suppress errors so we can capture them)
        $result = mail($to, $subject, $body, $headers);
        
        // If failed and logging enabled, capture the error details
        if (!$result && Config::bool('ENABLE_LOGGING')) {
            $lastError = error_get_last();
            if ($lastError && Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    "PHP mail() function failed: {$lastError['message']}",
                    [
                        'recipient' => $to,
                        'subject' => $subject,
                        'from' => $from,
                        'php_error_type' => $lastError['type'],
                        'php_error_message' => $lastError['message'],
                        'php_error_file' => $lastError['file'],
                        'php_error_line' => $lastError['line'],
                    ],
                    'warning'
                );
            }
        }
        
        return $result;
    }

    /**
     * Send email via SMTP
     */
    private static function sendViaSMTP($to, $subject, $body)
    {
        $host = Config::get('MAIL_SMTP_HOST');
        $port = Config::get('MAIL_SMTP_PORT', 587);
        $username = Config::get('MAIL_SMTP_USERNAME');
        $password = Config::get('MAIL_SMTP_PASSWORD');
        $encryption = Config::get('MAIL_SMTP_ENCRYPTION', 'tls');
        $from = Config::get('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = Config::get('MAIL_FROM_NAME', 'Payment System');
        
        if (!$host || !$username || !$password) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'SMTP configuration incomplete',
                    [
                        'has_host' => !empty($host),
                        'has_username' => !empty($username),
                        'has_password' => !empty($password),
                    ],
                    'warning'
                );
            }
            return false;
        }
        
        try {
            // Prepare SSL context
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);
            
            // Determine socket protocol based on encryption
            if ($encryption === 'ssl') {
                $socketAddr = "ssl://{$host}:{$port}";
            } else {
                $socketAddr = "tcp://{$host}:{$port}";
            }
            
            // Connect to SMTP server
            $socket = @stream_socket_client(
                $socketAddr,
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$socket) {
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        "SMTP connection failed: {$errstr}",
                        [
                            'host' => $host,
                            'port' => $port,
                            'errno' => $errno,
                            'errstr' => $errstr,
                        ],
                        'warning'
                    );
                }
                return false;
            }
            
            // Set stream blocking mode
            stream_set_blocking($socket, true);
            
            // Read SMTP greeting
            $response = fgets($socket, 515);
            if (strpos($response, '220') === false) {
                fclose($socket);
                return false;
            }
            
            // EHLO command
            fwrite($socket, "EHLO localhost\r\n");
            self::readSMTPResponse($socket);
            
            // STARTTLS if needed (upgrade connection to TLS)
            if ($encryption === 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                if (strpos($response, '220') !== false) {
                    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                }
            }
            
            // AUTH LOGIN
            fwrite($socket, "AUTH LOGIN\r\n");
            fgets($socket, 515);
            
            // Send username (base64 encoded)
            fwrite($socket, base64_encode($username) . "\r\n");
            fgets($socket, 515);
            
            // Send password (base64 encoded)
            fwrite($socket, base64_encode($password) . "\r\n");
            $response = fgets($socket, 515);
            
            if (strpos($response, '235') === false && strpos($response, '250') === false) {
                fclose($socket);
                if (Config::bool('ENABLE_LOGGING')) {
                    Logger::logError(
                        'SMTP authentication failed',
                        [
                            'response' => trim($response),
                            'host' => $host,
                        ],
                        'warning'
                    );
                }
                return false;
            }
            
            // MAIL FROM
            fwrite($socket, "MAIL FROM:<{$from}>\r\n");
            fgets($socket, 515);
            
            // RCPT TO
            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            fgets($socket, 515);
            
            // DATA
            fwrite($socket, "DATA\r\n");
            fgets($socket, 515);
            
            // Build email headers
            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "Reply-To: {$from}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: SK Website Payment System\r\n";
            
            // Write email
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $response = fgets($socket, 515);
            
            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return strpos($response, '250') !== false;
            
        } catch (Throwable $e) {
            if (Config::bool('ENABLE_LOGGING')) {
                Logger::logError(
                    'SMTP sending exception: ' . $e->getMessage(),
                    [
                        'host' => $host,
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                    'warning'
                );
            }
            return false;
        }
    }
    
    /**
     * Read SMTP response (may be multi-line)
     */
    private static function readSMTPResponse($socket)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Validate email address format
     */
    private static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
