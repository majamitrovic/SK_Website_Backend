<?php

namespace App;

/**
 * Centralized error handling service for Allsecure gateway errors
 * 
 * Handles error translation, formatting, and filtering based on configuration.
 * Used across API responses, email templates, logging, and other services.
 */
final class ErrorService
{
    /**
     * Format errors for API responses
     * 
     * Returns structured array with translated messages and optional adapter details
     * based on GATEWAY_SHOW_ADAPTER_DETAILS configuration
     */
    public static function formatForApi(array $errors, string $language = null): array
    {
        $data = array();
        $language = $language ?? self::getLanguage();
        $showAdapterDetails = self::shouldShowAdapterDetails();

        foreach ($errors as $error) {
            $errorCode = (int) ($error->getCode() ?? $error['code'] ?? 0);
            $translatedMessage = ErrorMessages::translate($errorCode, $language);

            $errorArray = array(
                'message' => $translatedMessage,
                'code' => $errorCode,
                'originalMessage' => self::getOriginalMessage($error),
            );

            // Only include adapter details if explicitly enabled
            if ($showAdapterDetails) {
                $errorArray['adapterMessage'] = self::getAdapterMessage($error);
                $errorArray['adapterCode'] = self::getAdapterCode($error);
            }

            $data[] = $errorArray;
        }

        return $data;
    }

    /**
     * Format errors for email templates
     * 
     * Returns user-friendly formatted string suitable for email display
     */
    public static function formatForEmail(array $errors, string $language = null): string
    {
        if (empty($errors)) {
            $lang = $language ?? self::getLanguage();
            if ($lang === 'sr') {
                return 'Nažalost, vaše plaćanje nije moglo biti obrađeno. Molimo proverite podatke vaše kartice i pokušajte ponovo.';
            }
            return 'Unfortunately, your payment could not be processed. Please check your card details and try again.';
        }

        $language = $language ?? self::getLanguage();
        $messages = array();

        foreach ($errors as $error) {
            $messages[] = $error['message'];
        }

        return implode('; ', $messages);
    }

    /**
     * Format errors for logging
     * 
     * Returns detailed error information including original messages and adapter details
     * for debugging and monitoring purposes
     */
    public static function formatForLogging(array $errors): array
    {
        $data = array();

        foreach ($errors as $error) {
            $errorCode = (int) ($error->getCode() ?? $error['code'] ?? 0);
            $language = self::getLanguage();
            $translatedMessage = ErrorMessages::translate($errorCode, $language);

            $errorArray = array(
                'code' => $errorCode,
                'translatedMessage' => $translatedMessage,
                'originalMessage' => self::getOriginalMessage($error),
                'adapterMessage' => self::getAdapterMessage($error),
                'adapterCode' => self::getAdapterCode($error),
                'timestamp' => date('Y-m-d H:i:s'),
            );

            $data[] = $errorArray;
        }

        return $data;
    }

    /**
     * Translate a single error code
     */
    public static function translate(int $code, string $language = null): string
    {
        $language = $language ?? self::getLanguage();
        return ErrorMessages::translate($code, $language);
    }

    /**
     * Get configured error message language
     * Falls back to 'en' if not configured
     */
    public static function getLanguage(): string
    {
        $lang = trim((string) Config::get('GATEWAY_ERROR_LANGUAGE', 'en'));
        return in_array($lang, array('sr', 'en'), true) ? $lang : 'en';
    }

    /**
     * Check if adapter details should be shown
     * Defaults to false (hidden from end users)
     */
    public static function shouldShowAdapterDetails(): bool
    {
        return Config::bool('GATEWAY_SHOW_ADAPTER_DETAILS', false);
    }

    /**
     * Extract original message from error object or array
     */
    private static function getOriginalMessage($error): string
    {
        if (is_object($error)) {
            return self::normalizeStringValue(method_exists($error, 'getMessage') ? $error->getMessage() : '');
        }
        return self::normalizeStringValue($error['originalMessage'] ?? $error['message'] ?? '');
    }

    /**
     * Extract adapter message from error object or array
     */
    private static function getAdapterMessage($error): string
    {
        if (is_object($error)) {
            return self::normalizeStringValue(method_exists($error, 'getAdapterMessage') ? $error->getAdapterMessage() : '');
        }
        return self::normalizeStringValue($error['adapterMessage'] ?? '');
    }

    /**
     * Extract adapter code from error object or array
     */
    private static function getAdapterCode($error): string
    {
        if (is_object($error)) {
            return self::normalizeStringValue(method_exists($error, 'getAdapterCode') ? $error->getAdapterCode() : '');
        }
        return self::normalizeStringValue($error['adapterCode'] ?? '');
    }

    /**
     * Normalize values to a safe string
     */
    private static function normalizeStringValue($value): string
    {
        return is_string($value) ? $value : (string) ($value ?? '');
    }
}
