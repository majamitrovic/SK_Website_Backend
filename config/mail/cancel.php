<?php
/**
 * Cancel Email Configuration
 *
 * Available placeholders:
 * - {transaction_id}
 * - {schedule_id}
 */

return [
    'subject' => 'Potvrda otkazivanja pretplate - Transakcija {transaction_id}',
    'template' => 'cancel.html',
];
