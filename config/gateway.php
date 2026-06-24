<?php

/**
 * Gateway Configuration
 * Error handling and messaging preferences
 */

return array(
    /**
     * Error message language for API responses
     * Supported values: 'sr' (Serbian), 'en' (English)
     * Default: 'en'
     */
    'GATEWAY_ERROR_LANGUAGE' => getenv('GATEWAY_ERROR_LANGUAGE') ?: 'sr',

    /**
     * Show adapter messages and codes in API responses
     * When false (default), only translated gateway error messages are shown to end users
     * When true, includes raw adapter messages (for debugging/admin use)
     * Default: false
     */
    'GATEWAY_SHOW_ADAPTER_DETAILS' => (bool) getenv('GATEWAY_SHOW_ADAPTER_DETAILS') ?: false,
);
