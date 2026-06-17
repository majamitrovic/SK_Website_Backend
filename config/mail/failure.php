<?php
/**
 * Failure Email Configuration
 * 
 * Customize the subject line and template for failed payment emails.
 * Available placeholders:
 * - {transaction_id}
 * - {amount}
 * - {currency}
 * - {first_name}
 * - {last_name}
 */

return [
    'subject' => 'Plaćanje neuspešno - Transakcija {transaction_id}',
    'template' => 'failure.html',
];
