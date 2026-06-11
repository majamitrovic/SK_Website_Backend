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
    'subject' => 'Pla??anje neuspe??no - Transakcija {transaction_id}',
    'template' => 'failure.html',
];
