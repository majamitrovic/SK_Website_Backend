# Email Logging Guide

Complete email sending logging has been added to help you troubleshoot mail delivery issues.

## What Gets Logged

### Successful Email Sends
**Log Level:** Transaction (info)
**Location:** `storage/logs/transactions.log`

Logged when an email is successfully sent:
```json
{
    "type": "email_sent",
    "email_type": "payment_success",
    "recipient": "customer@example.com",
    "subject": "Payment Confirmed",
    "success": true,
    "method": "php_mail",
    "customer_email": "customer@example.com",
    "amount": 99.99,
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Email Failures
**Log Level:** Error
**Location:** `storage/logs/errors.log`

Logged when email sending fails:
```json
{
    "type": "error",
    "severity": "error",
    "message": "Failed to send payment_success email to customer@example.com",
    "context": {
        "email_type": "payment_success",
        "recipient": "customer@example.com",
        "subject": "Payment Confirmed",
        "method": "php_mail",
        "customer_email": "customer@example.com",
        "amount": 99.99
    },
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Email Exceptions
**Log Level:** Critical
**Location:** `storage/logs/errors.log`

Logged when email sending throws an exception:
```json
{
    "type": "error",
    "severity": "critical",
    "message": "Email sending exception (payment_success): SMTP connection failed",
    "context": {
        "email_type": "payment_success",
        "recipient": "customer@example.com",
        "exception": "Exception",
        "file": "/src/EmailService.php",
        "line": 150,
        "trace": "..."
    },
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Invalid Email Addresses
**Log Level:** Warning
**Location:** `storage/logs/errors.log`

Logged when email address format is invalid:
```json
{
    "type": "error",
    "severity": "warning",
    "message": "Invalid email address: not-an-email",
    "context": {
        "email_type": "payment_success",
        "invalid_email": "not-an-email",
        "customer_email": "..."
    },
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Missing Email Addresses
**Log Level:** Warning
**Location:** `storage/logs/errors.log`

Logged when email address is not provided:
```json
{
    "type": "error",
    "severity": "warning",
    "message": "Payment success email not sent - no email address",
    "context": {
        "payment_id": "PAY_123"
    },
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Missing Configuration
**Log Level:** Warning
**Location:** `storage/logs/errors.log`

Logged when required configuration is missing (e.g., admin email):
```json
{
    "type": "error",
    "severity": "warning",
    "message": "Callback notification email not sent - no admin email configured",
    "context": {
        "configured_admin_email": false
    },
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

## Email Types Being Logged

| Email Type | Trigger | Recipient |
|------------|---------|-----------|
| `payment_success` | Successful payment completed | Customer |
| `payment_failure` | Payment failed or was declined | Customer |
| `schedule_confirmation` | Recurring payment scheduled | Customer |
| `callback_notification` | Payment callback received | Admin |

## Debugging Email Issues

### Check if Emails Are Being Sent
```bash
# View all successful email sends
grep "email_sent" storage/logs/transactions.log | tail -20

# Format as JSON
cat storage/logs/transactions.log | grep "email_sent" | jq .
```

### Check for Email Errors
```bash
# View all email-related errors
grep "email" storage/logs/errors.log | tail -50

# View critical email errors only
grep "email" storage/logs/errors.log | grep "critical" | jq .

# View email address validation errors
grep "Invalid email" storage/logs/errors.log | jq .
```

### Troubleshooting Steps

#### 1. Check if Emails Are Being Attempted
```bash
# Count email send attempts
grep -c "email_sent\|Failed to send.*email" storage/logs/*.log
```

If no emails are being logged:
- **Problem:** EmailService methods not being called
- **Solution:** Verify you're calling `EmailService::sendPaymentSuccess()` etc. in your callback handler

#### 2. Check for Invalid Email Addresses
```bash
# Find invalid email addresses
grep "Invalid email" storage/logs/errors.log | jq '.context.invalid_email'
```

If invalid emails are logged:
- **Problem:** Payment form validation not checking email format
- **Solution:** Add email validation in frontend form

#### 3. Check for Missing Configuration
```bash
# Check mail configuration
grep "mail configuration" storage/logs/errors.log
```

If configuration is missing:
- **Problem:** .env file missing mail settings
- **Solution:** Add required mail settings to .env:
  ```env
  MAIL_DRIVER=smtp
  MAIL_HOST=smtp.mailtrap.io
  MAIL_PORT=465
  MAIL_USERNAME=your_email@example.com
  MAIL_PASSWORD=your_app_password
  MAIL_FROM_ADDRESS=noreply@example.com
  MAIL_FROM_NAME=Solidarni kolektiv
  MAIL_ENCRYPTION=ssl
  PAYMENT_ADMIN_EMAIL=admin@example.com
  ```

#### 4. Check PHP Mail Function
If using `php_mail` method (not SMTP):
```bash
# Check if PHP mail is working
php -r "echo mail('test@example.com', 'Test', 'Test message') ? 'Works' : 'Failed';"
```

Common issues:
- **sendmail not configured:** Contact hosting provider
- **Hostname mismatch:** Check server hostname in mail headers
- **SPF/DKIM records:** Add SPF records to your domain's DNS

#### 5. Check SMTP Connection
If using SMTP:
```bash
# Test SMTP connection
telnet smtp.mailtrap.io 465
```

Common issues:
- **Connection refused:** Check MAIL_HOST and MAIL_PORT
- **Authentication failed:** Check MAIL_USERNAME and MAIL_PASSWORD
- **TLS/SSL errors:** Check MAIL_ENCRYPTION setting

## Email Sending Integration

### In Your Callback Handler

Add email sending after successful callback processing:

```php
// In public/callback.php or wherever you process callbacks

try {
    $callback = $service->readCallback($body);
    $callbackData = AllSecureService::callbackResultToArray($callback);
    PaymentStorage::append('callbacks.jsonl', $callbackData);

    // Add email sending based on callback result
    $result = $callback->getResult(); // 'confirmed', 'failed', etc.
    
    if ($result === 'confirmed') {
        // Send success email to customer
        EmailService::sendPaymentSuccess($paymentData, $callbackData);
        
        // If recurring, send schedule confirmation
        if (!empty($callbackData['scheduleId'])) {
            EmailService::sendScheduleConfirmation($paymentData, $callbackData);
        }
    } elseif ($result === 'failed') {
        // Send failure email to customer
        EmailService::sendPaymentFailure($paymentData, $callbackData);
    }

    // Always send admin notification
    EmailService::sendCallbackNotification($callbackData);

    Logger::logPaymentStatus($callback->getMerchantTransactionId(), $result, ...);
} catch (Throwable $exception) {
    Logger::logError(...);
}
```

## Email Configuration (.env)

```env
# Mail Driver: smtp, sendmail, mailgun, ses
MAIL_DRIVER=smtp

# SMTP Configuration
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_email@example.com
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=Solidarni kolektiv
MAIL_ENCRYPTION=ssl

# Admin Email for Notifications
PAYMENT_ADMIN_EMAIL=admin@example.com

# Support Email
SUPPORT_EMAIL=support@example.com
```

### Recommended Email Services (Free)

#### 1. **Mailtrap** (Free - Testing)
- Perfect for testing email functionality
- Doesn't actually send emails, just captures them
- Great for development and debugging
```env
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_DRIVER=smtp
MAIL_ENCRYPTION=ssl
```

#### 2. **Gmail SMTP** (Free)
```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_DRIVER=smtp
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password  # Not your Gmail password
```

**Note:** For Gmail, you need to:
1. Enable 2-factor authentication
2. Generate an app-specific password
3. Use the app password instead of your Gmail password

#### 3. **SendGrid** (Free - 100 emails/day)
```env
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_DRIVER=smtp
MAIL_ENCRYPTION=tls
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
```

#### 4. **PHP Mail** (Server's sendmail)
```env
# No SMTP configuration needed
# Uses server's sendmail function
# Must be properly configured on your hosting
```

## Log File Locations

```
storage/logs/
├── transactions.log    (includes successful emails)
├── errors.log          (includes email errors)
├── payments.log        (payment status callbacks)
└── api_requests.log    (other API interactions)
```

## View Email Logs in Real-Time

```bash
# Watch email logs as they're generated
tail -f storage/logs/transactions.log | grep email

# Watch for errors
tail -f storage/logs/errors.log | grep -i email

# Format output as JSON
tail -f storage/logs/errors.log | grep -i email | jq .
```

## Complete Email Flow Logging

```
1. Payment created → logs in payment/api/pay.php
2. Payment confirmed via callback → logs in public/callback.php
3. Email sending attempted → logs in EmailService
4. Email sent/failed → logs in transactions.log or errors.log
```

All with full context including:
- Customer email addresses
- Payment amounts and IDs
- Email subjects
- Success/failure reasons
- Exception details with stack traces

## Testing Email Configuration

### Test with Mailtrap
1. Create free account at https://mailtrap.io
2. Get SMTP credentials from Mailtrap dashboard
3. Update .env with Mailtrap credentials
4. Trigger a payment transaction
5. Check Mailtrap inbox for received email

This proves your email code is working before switching to production email service.

### Test Logs After Sending
```bash
# View the most recent email attempt
tail -1 storage/logs/transactions.log | jq '.context'

# Check if it succeeded
tail -1 storage/logs/transactions.log | jq '.success'
```
