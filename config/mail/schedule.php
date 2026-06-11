<?php
/**
 * Schedule Email Configuration
 * 
 * Customize the subject line and template for recurring payment schedule emails.
 * Available placeholders:
 * - {schedule_id}
 * - {transaction_id}
 * - {amount}
 * - {currency}
 * - {first_name}
 * - {last_name}
 */

return [
    'subject' => 'Potvrda periodi??nog pla??anja - Raspored {schedule_id}',
    'template' => 'schedule.html',
];
