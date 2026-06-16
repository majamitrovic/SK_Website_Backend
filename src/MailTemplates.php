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
        $subject = $config['subject'] ?? 'Potvrda pla??anja - Transakcija {transaction_id}';
        
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
        $subject = $config['subject'] ?? 'Pla??anje neuspe??no - Transakcija {transaction_id}';
        
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
        $subject = $config['subject'] ?? 'Potvrda periodi??nog pla??anja - Raspored {schedule_id}';
        
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

            // For customer-facing emails ensure a standardized summary is prepended
            if (in_array($type, ['success', 'failure', 'schedule'], true)) {
                $dt = new \DateTime($data['transactionDate'] ?? 'now', new \DateTimeZone('UTC'));
                try {
                    $dt->setTimezone(new \DateTimeZone('Europe/Belgrade'));
                } catch (Exception $e) {
                    // ignore timezone conversion errors
                }

                $cardLast = $data['card']['lastFourDigits'] ?? ($data['lastFour'] ?? '');
                $auth = $data['bankAuthCode'] ?? ($data['authCode'] ?? '');
                $resultText = $data['result'] ?? ($data['success'] ? 'Successful' : 'Failed');

                $summary = "<p>Merchant Transaction ID: " . htmlspecialchars($data['merchantTransactionId'] ?? '') . "<br>";
                $summary .= "Authorization Code: " . htmlspecialchars($auth) . "<br>";
                $summary .= "Amount: " . htmlspecialchars($data['amount'] ?? '') . " " . htmlspecialchars($data['currency'] ?? '') . "<br>";
                $summary .= "Card: " . htmlspecialchars($data['paymentMethod'] ?? '') . " ****" . htmlspecialchars($cardLast) . "<br>";
                $summary .= "Transaction Time: " . htmlspecialchars($dt->format('Y-m-d H:i:s T')) . "<br>";
                $summary .= "Result: " . htmlspecialchars($resultText) . "</p>";

                return $summary . $content;
            }

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
            'merchantTransactionId' => htmlspecialchars($payment['merchantTransactionId'] ?? ''),
            'amount' => htmlspecialchars($payment['amount'] ?? '0'),
            'currency' => htmlspecialchars($payment['currency'] ?? 'EUR'),
            'description' => htmlspecialchars($payment['description'] ?? ''),
            
            // Customer info
            'firstName' => htmlspecialchars($payment['first_name'] ?? ''),
            'lastName' => htmlspecialchars($payment['last_name'] ?? ''),
            'email' => htmlspecialchars($payment['email'] ?? ''),
            'billingAddress' => htmlspecialchars($payment['billing_address'] ?? ''),
            'billingCity' => htmlspecialchars($payment['billing_city'] ?? ''),
            'billingPostcode' => htmlspecialchars($payment['billing_postcode'] ?? ''),
            'billingState' => htmlspecialchars($payment['billing_state'] ?? ''),
            'billingCountry' => htmlspecialchars($payment['billing_country'] ?? ''),
            
            // Company info
            'companyName' => htmlspecialchars(Config::get('COMPANY_NAME', 'Our Company')),
            'supportEmail' => htmlspecialchars(Config::get('SUPPORT_EMAIL', 'support@example.com')),
            'companyUrl' => htmlspecialchars(Config::baseUrl()),
            
            // Result info (for success/failure)
            'success' => $result['success'] ?? false,
            'paymentMethod' => htmlspecialchars(self::formatPaymentMethod($result['paymentMethod'] ?? 'Card')),
            'bankAuthCode' => htmlspecialchars($result['uuid'] ?? ''),
            'authCode' => htmlspecialchars($result['authCode'] ?? $result['uuid'] ?? ''),
            'card' => $payment['card'] ?? $result['card'] ?? [],
            'lastFour' => htmlspecialchars($payment['card']['lastFourDigits'] ?? $result['card']['lastFourDigits'] ?? ''),
            'cardType' => htmlspecialchars($payment['card']['type'] ?? $result['card']['type'] ?? ''),
            'transactionDate' => date('Y-m-d H:i:s', strtotime($result['scheduledAt'] ?? 'now')),
            'errors' => self::formatErrors($result['errors'] ?? []),
        ];

        // Add recurring info if applicable
        if (!empty($payment['recurring'])) {
            $data['recurringEnabled'] = (bool) $payment['recurring']['enabled'];
            $data['recurringAmount'] = htmlspecialchars($payment['recurring']['amount'] ?? '');
            $data['recurringPeriodLength'] = htmlspecialchars($payment['recurring']['periodLength'] ?? '1');
            $data['recurringPeriodUnit'] = self::formatPeriodUnit($payment['recurring']['periodUnit'] ?? 'MONTH');
            $data['recurringStartDateTime'] = htmlspecialchars($payment['recurring']['startDateTime'] ?? '');
            $data['scheduleId'] = htmlspecialchars($result['scheduleId'] ?? '');
            $data['scheduleStatus'] = htmlspecialchars($result['scheduleStatus'] ?? '');
        }

        // Add callback data if provided
        if (isset($payment['result'])) {
            $data['uuid'] = htmlspecialchars($payment['uuid'] ?? '');
            $data['purchaseId'] = htmlspecialchars($payment['purchaseId'] ?? '');
            $data['transactionType'] = htmlspecialchars($payment['transactionType'] ?? '');
            $data['result'] = htmlspecialchars($payment['result'] ?? '');
        }

        return $data;
    }

    /**
     * Replace placeholders in string
     */
    private static function replacePlaceholders(string $text, array $payment, array $result = []): string
    {
        $replacements = [
            '{transaction_id}' => $payment['merchantTransactionId'] ?? '',
            '{schedule_id}' => $result['scheduleId'] ?? '',
            '{amount}' => $payment['amount'] ?? '',
            '{currency}' => $payment['currency'] ?? '',
            '{first_name}' => $payment['first_name'] ?? '',
            '{last_name}' => $payment['last_name'] ?? '',
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
