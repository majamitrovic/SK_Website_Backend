<?php
/**
 * Callback Email Configuration
 * 
 * Customize the subject line and template for payment callback notifications.
 * Available placeholders:
 * - {transaction_id}
 */

return [
    'subject' => 'Payment Callback Received - {transaction_id}',
    'template' => 'callback.html',
];
