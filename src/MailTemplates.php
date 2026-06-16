<?php

namespace App;

final class MailTemplates
{
    /**
     * Get success email subject
     */
    public static function getSuccessSubject(array $payment, array $result): string
    {
        $config = self::loadConfig('success');
        $subject = $config['subject'] ?? 'Potvrda plaćanja - Transakcija {transaction_id}';
        
        return self::replacePlaceholders($subject, $payment, $result);
    }

    /**
     * Get success email body
     */
    public static function getSuccessBody(array $payment, array $result): string
    {
        $config = self::loadConfig('success');
        $template = $config['template'] ?? 'success.html';
        
        return self::renderTemplate($template, $payment, $result, 'success');
    }

    /**
     * Get failure email subject
     */
    public static function getFailureSubject(array $payment, array $result): string
    {
        $config = self::loadConfig('failure');
        $subject = $config['subject'] ?? 'Plaćanje neuspešno - Transakcija {transaction_id}';
        
        return self::replacePlaceholders($subject, $payment, $result);
    }

    /**
     * Get failure email body
     */
    public static function getFailureBody(array $payment, array $result): string
    {
        $config = self::loadConfig('failure');
        $template = $config['template'] ?? 'failure.html';
        
        return self::renderTemplate($template, $payment, $result, 'failure');
    }

    /**
     * Get schedule email subject
     */
    public static function getScheduleSubject(array $payment, array $result): string
    {
        $config = self::loadConfig('schedule');
        $subject = $config['subject'] ?? 'Potvrda periodičnog plaćanja - Raspored {schedule_id}';
        
        return self::replacePlaceholders($subject, $payment, $result);
    }

    /**
     * Get schedule email body
     */
    public static function getScheduleBody(array $payment, array $result): string
    {
        $config = self::loadConfig('schedule');
        $template = $config['template'] ?? 'schedule.html';
        
        return self::renderTemplate($template, $payment, $result, 'schedule');
    }

    /**
     * Get callback email subject
     */
    public static function getCallbackSubject(array $callback): string
    {
        $config = self::loadConfig('callback');
        $subject = $config['subject'] ?? 'Payment Callback Received - {transaction_id}';
        
        return self::replacePlaceholders($subject, $callback);
    }

    /**
     * Get callback email body
     */
    public static function getCallbackBody(array $callback): string
    {
        $config = self::loadConfig('callback');
        $template = $config['template'] ?? 'callback.html';
        
        return self::renderTemplate($template, $callback, [], 'callback');
    }

    /**
     * Load configuration for a specific email type
     */
    private static function loadConfig(string $type): array
    {
        $configFile = Config::projectPath("config/mail/{$type}.php");
        
        if (!file_exists($configFile)) {
            return [];
        }
        
        return include $configFile;
    }

    /**
     * Render template file with variables
     */
    private static function renderTemplate(string $template, array $payment, array $result = [], string $type = 'success'): string
    {
        $templateFile = Config::projectPath("resources/mail/{$template}");
        
        if (!file_exists($templateFile)) {
            return "Template not found: {$template}";
        }
        
        // Prepare data for template
        $data = self::prepareData($payment, $result, $type);
        
        // Start output buffering
        ob_start();

        try {
            // Extract variables to template scope
            extract($data, EXTR_SKIP);

            // Include template
            include $templateFile;

            // Capture rendered template
            $content = ob_get_clean();

            return $content;
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Prepare data for template rendering
     */
    private static function prepareData(array $payment, array $result = [], string $type = 'success'): array
    {
        $data = [
            // Basic payment info
            'merchantTransactionId' => htmlspecialchars($result['merchantTransactionId'] ?? ''),
            'amount' => htmlspecialchars($result['amount'] ?? '0'),
            'currency' => htmlspecialchars($result['currency'] ?? 'EUR'),
            
            // Customer info
            'firstName' => htmlspecialchars($result['customer']['first_name'] ?? ''),
            'lastName' => htmlspecialchars($result['customer']['last_name'] ?? ''),
            'email' => htmlspecialchars($payment['email'] ?? ''),
            
            // Company info
            'companyName' => htmlspecialchars(Config::get('COMPANY_NAME', 'Our Company')),
            'supportEmail' => htmlspecialchars(Config::get('SUPPORT_EMAIL', 'support@example.com')),
            'companyUrl' => htmlspecialchars(Config::baseUrl()),
            
            // Result info (for success/failure)
            'paymentStatus' => $payment['paymentStatus'],
            'paymentMethod' => htmlspecialchars(self::formatPaymentMethod($result['paymentMethod'] ?? 'Card')),
            'authCode' => htmlspecialchars($result['authCode'] ?? $result['uuid'] ?? ''),
            'card' => $result['card'] ?? [],
            'lastFour' => htmlspecialchars($result['card']['lastFourDigits'] ?? ''),
            'cardType' => htmlspecialchars($result['card']['type'] ?? ''),
            'transactionDate' => (new \DateTime('now'))
                            ->setTimezone(new \DateTimeZone('Europe/Belgrade'))
                            ->format('Y-m-d H:i:s'),
            'cancelLink' => htmlspecialchars($payment['cancelLink'] ?? ''),
            'errors' => self::formatErrors($result['errors'] ?? []),
        ];

        // Add recurring info if applicable
        if (!empty($result['scheduledData'])) {
            $data['scheduleId'] = htmlspecialchars($result['scheduledData']['scheduleId'] ?? '');
            $data['scheduleStatus'] = htmlspecialchars($result['scheduledData']['scheduleStatus'] ?? '');
            $data['scheduledAt'] = htmlspecialchars(
                isset($result['scheduledData']['scheduledAt'])
                    ? $result['scheduledData']['scheduledAt']
                    ->setTimezone(new \DateTimeZone('Europe/Belgrade'))
                    ->format('Y-m-d H:i:s')
                    : ''
            );
        }

        // Add callback data if provided
        if (isset($result['result'])) {
            $data['uuid'] = htmlspecialchars($result['uuid'] ?? '');
            $data['purchaseId'] = htmlspecialchars($result['purchaseId'] ?? '');
            $data['transactionType'] = htmlspecialchars($result['transactionType'] ?? '');
            $data['result'] = htmlspecialchars($result['result'] ?? '');
        }

        return $data;
    }

    /**
     * Replace placeholders in string
     */
    private static function replacePlaceholders(string $text, array $payment, array $result = []): string
    {
        $replacements = [
            '{transaction_id}' => $result['merchantTransactionId'] ?? '',
            '{schedule_id}' => $result['scheduleId'] ?? '',
            '{amount}' => $result['amount'] ?? '',
            '{currency}' => $result['currency'] ?? '',
            '{first_name}' => $result['customer']['firstName'] ?? '',
            '{last_name}' => $result['customer']['lastName'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Format payment method for display
     */
    private static function formatPaymentMethod($method): string
    {
        return htmlspecialchars($method ?? 'Card');
    }

    /**
     * Format period unit in Serbian
     */
    private static function formatPeriodUnit(string $unit): string
    {
        $units = [
            'DAY' => 'day',
            'WEEK' => 'week',
            'MONTH' => 'month',
            'YEAR' => 'year',
        ];
        
        return $units[strtoupper($unit)] ?? 'month';
    }

    /**
     * Format error messages from payment result
     */
    private static function formatErrors(array $errors): string
    {
        if (empty($errors)) {
            return 'Unfortunately, your payment could not be processed. Please check your card details and try again.';
        }
        
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error['message'] ?? $error['adapterMessage'] ?? 'Unknown error';
        }
        
        return implode('; ', $messages);
    }
}
