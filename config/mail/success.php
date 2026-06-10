<?php
/**
 * Success Email Configuration
 * 
 * Customize the subject line and template for successful payment emails.
 * Available placeholders:
 * - {transaction_id}
 * - {amount}
 * - {currency}
 * - {first_name}
 * - {last_name}
 */

return [
    'subject' => 'Potvrda pla??anja - Transakcija {transaction_id}',
    'template' => 'success.html',
];
