<?php
/**
 * Mail Configuration
 * 
 * Add these to your .env file:
 * 
 * MAIL_FROM_ADDRESS=noreply@vasa-domena.rs
 * MAIL_FROM_NAME=Sistem za pla??anja
 * 
 * # For SMTP (optional - uses PHP mail if not set)
 * MAIL_SMTP_HOST=smtp.example.com
 * MAIL_SMTP_PORT=587
 * MAIL_SMTP_USERNAME=your-email@example.com
 * MAIL_SMTP_PASSWORD=your-password
 * MAIL_SMTP_ENCRYPTION=tls
 * 
 * SUPPORT_EMAIL=support@example.com
 * COMPANY_NAME=Va??a Kompanija
 * PAYMENT_ADMIN_EMAIL=admin@example.com
 */

// Example configuration - these values should come from .env file
return [
    // From address for all emails
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Payment System',
    
    // SMTP Configuration (optional)
    'smtp' => [
        'host' => getenv('MAIL_SMTP_HOST') ?: null,
        'port' => (int) getenv('MAIL_SMTP_PORT') ?: 587,
        'username' => getenv('MAIL_SMTP_USERNAME') ?: null,
        'password' => getenv('MAIL_SMTP_PASSWORD') ?: null,
        'encryption' => getenv('MAIL_SMTP_ENCRYPTION') ?: 'tls', // 'tls' or 'ssl'
    ],
    
    // Company Information
    'company' => [
        'name' => getenv('COMPANY_NAME') ?: 'Our Company',
        'support_email' => getenv('SUPPORT_EMAIL') ?: 'support@example.com',
        'admin_email' => getenv('PAYMENT_ADMIN_EMAIL') ?: 'admin@example.com',
    ],
];
