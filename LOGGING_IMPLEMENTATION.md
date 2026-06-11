# Payment Logging Implementation Summary

## Overview
Comprehensive logging has been integrated into all key payment processing files. The system logs:
- **Payment transactions** - All payment creation attempts with details
- **Errors** - All validation and processing errors with severity levels
- **Callbacks** - Payment status callbacks from the gateway
- **Status queries** - Payment status lookups

## Files Updated

### 1. **backend/bootstrap.php**
**What was added:** Logger initialization
```php
// Initialize logging system
try {
    if (Config::bool('ENABLE_LOGGING', true)) {
        if (Config::bool('ENABLE_DATABASE_LOGGING', false)) {
            $dbType = Config::get('DB_TYPE', 'sqlite');
            $db = new \App\DatabaseManager($dbType);
            Logger::initialize($db);
        } else {
            Logger::initialize();
        }
    }
} catch (\Exception $e) {
    error_log("Failed to initialize logging: " . $e->getMessage());
}
```
**Impact:** Logger is initialized on every request, supporting both file and database logging

---

### 2. **backend/api/pay.php** (Payment Creation)
**What was added:** 
- Logging successful payment creation with all transaction details
- Logging validation errors with error details
- Logging processing errors with exception trace

**Logged information on success:**
- Transaction ID (merchantTransactionId)
- Amount and currency
- Customer email
- Payment success status
- UUID and purchase ID
- Payment method used
- Schedule ID (if recurring)
- Any payment errors

**Logged information on error:**
- Validation errors (field-specific)
- Exception type and message
- File and line number
- Full stack trace
- Customer email

**Log calls:**
```php
// Success
Logger::logTransaction(array(
    'type' => 'debit_request',
    'transaction_id' => $payment['merchantTransactionId'],
    'amount' => $payment['amount'],
    'currency' => $payment['currency'],
    'customer_email' => $input['email'] ?? null,
    'success' => $payment['result']['success'],
    ...
));

// Validation error
Logger::logError('Payment validation failed', array(
    'errors' => $exception->errors(),
    'file' => __FILE__,
    'line' => __LINE__,
), 'warning');

// Processing error
Logger::logError('Payment processing failed: ...', array(
    'exception' => get_class($exception),
    'file' => $exception->getFile(),
    'line' => $exception->getLine(),
    'trace' => $exception->getTraceAsString(),
    'input_email' => $input['email'] ?? null,
), 'critical');
```

---

### 3. **backend/api/status.php** (Payment Status Lookup)
**What was added:**
- Logging successful status queries
- Logging status query errors

**Logged information on success:**
- Transaction ID
- Payment status
- UUID
- Query success status

**Logged information on error:**
- Transaction ID
- Exception type and message
- File and line number

**Log calls:**
```php
// Success
Logger::logTransaction(array(
    'type' => 'status_query',
    'transaction_id' => $merchantTransactionId,
    'payment_status' => $status['result'] ?? null,
    'uuid' => $status['uuid'] ?? null,
    'success' => $status['success'] ?? false,
));

// Error
Logger::logError('Status query failed: ...', array(
    'transaction_id' => $merchantTransactionId,
    'exception' => get_class($exception),
    'file' => $exception->getFile(),
    'line' => $exception->getLine(),
), 'error');
```

---

### 4. **public/callback.php** (Payment Gateway Callback)
**What was added:**
- Logging successful callback processing
- Logging callback signature validation failures
- Logging callback processing errors

**Logged information on success:**
- Merchant transaction ID
- Payment status
- UUID
- Purchase ID
- Transaction type
- Schedule information (if recurring)

**Logged information on validation failure:**
- Signature validation failure reason
- Request URI
- Body length

**Logged information on error:**
- Exception type and message
- Body length
- File and line number
- Full stack trace

**Log calls:**
```php
// Callback rejection
Logger::logError('Callback signature validation failed', array(
    'reason' => 'invalid_signature',
    'request_uri' => $requestUri,
    'body_length' => strlen($body),
), 'warning');

// Success
Logger::logPaymentStatus(
    $callback->getMerchantTransactionId(),
    $callback->getResult(),
    array(
        'type' => 'callback',
        'uuid' => $callback->getUuid(),
        'purchase_id' => $callback->getPurchaseId(),
        'transaction_type' => $callback->getTransactionType(),
        'schedule_id' => $callback->getScheduleId(),
        'schedule_status' => $callback->getScheduleStatus(),
    )
);

// Error
Logger::logError('Callback processing failed: ...', array(
    'exception' => get_class($exception),
    'body_length' => strlen($body),
    'file' => $exception->getFile(),
    'line' => $exception->getLine(),
    'trace' => $exception->getTraceAsString(),
), 'critical');
```

---

### 5. **src/PaymentStorage.php** (Data Storage)
**What was added:**
- Integration with Logger for all stored records
- Automatic logging of payment storage events
- Error detection and logging for failed records

**Logged information:**
- All data being stored (transactions, callbacks, errors)
- Distinguishes between success and error records
- Passes complete record context to logger

**Log calls:**
```php
// In append() method
if (Config::bool('ENABLE_LOGGING')) {
    if (strpos($file, 'callback_errors') !== false || strpos($file, 'transactions.jsonl') !== false) {
        if ($record['success'] === false || isset($record['exception'])) {
            Logger::logError('Payment storage error: ...', $record, 'warning');
        } else {
            Logger::logTransaction($record);
        }
    }
}
```

---

## Log Types and Their Purposes

### **Transactions** (transactions.log)
Logs all payment transaction activity:
- Payment creation requests
- Payment status queries
- Recurring payment schedules
- Transaction amounts and currencies
- Customer information

### **Errors** (errors.log)
Logs all errors with severity levels:
- **Critical**: Payment processing failures, callback errors
- **Error**: Status query failures
- **Warning**: Validation failures, signature rejections
- Includes full stack traces for debugging

### **Payments** (payments.log)
Logs payment status changes:
- Callback notifications from gateway
- Schedule updates
- Payment confirmation details
- Authorization codes

### **Transactions (backup)** (api_requests.log)
Logs API interactions for audit trail

---

## Configuration

### Enable/Disable Logging
In `.env`:
```env
ENABLE_LOGGING=true          # Enable file-based logging (default: true)
ENABLE_DATABASE_LOGGING=false # Enable database logging (default: false)
DB_TYPE=sqlite               # Database type (sqlite, mysql, pgsql)
```

### File Locations
```
storage/logs/
├── transactions.log     # All payment transactions
├── errors.log           # All errors with stack traces
├── payments.log         # Payment status changes
└── api_requests.log     # API calls
```

### Database Storage
If database logging is enabled:
```
storage/database.sqlite (SQLite) or MySQL/PostgreSQL database
Tables: logs_transactions, logs_errors, logs_payments, logs_api_requests
```

---

## Log Entry Examples

### Successful Payment Transaction
```json
{
    "type": "debit_request",
    "transaction_id": "TXN_20240611123456",
    "amount": 99.99,
    "currency": "EUR",
    "customer_email": "customer@example.com",
    "success": true,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "purchase_id": "PUR_123456",
    "payment_method": "card",
    "error_count": 0,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-06-11T12:34:56+00:00"
}
```

### Payment Validation Error
```json
{
    "timestamp": "2024-06-11T12:34:56+00:00",
    "type": "error",
    "severity": "warning",
    "message": "Payment validation failed",
    "context": {
        "errors": {
            "email": "Invalid email format",
            "amount": "Amount must be greater than 0"
        }
    },
    "file": "/backend/api/pay.php",
    "line": 25,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0..."
}
```

### Payment Status Callback
```json
{
    "payment_id": "PAY_98765",
    "status": "confirmed",
    "details": {
        "type": "callback",
        "uuid": "550e8400-e29b-41d4-a716-446655440000",
        "purchase_id": "PUR_123456",
        "transaction_type": "debit",
        "schedule_id": "SCH_12345"
    },
    "ip_address": "203.0.113.42",
    "timestamp": "2024-06-11T12:35:00+00:00"
}
```

### Processing Error
```json
{
    "timestamp": "2024-06-11T12:34:56+00:00",
    "type": "error",
    "severity": "critical",
    "message": "Payment processing failed: Connection timeout",
    "context": {
        "exception": "RuntimeException",
        "input_email": "customer@example.com"
    },
    "file": "/backend/api/pay.php",
    "line": 50,
    "trace": "at ...",
    "ip_address": "192.168.1.100"
}
```

---

## Monitoring & Maintenance

### View Recent Errors
```bash
# Last 50 errors
tail -50 storage/logs/errors.log

# Critical errors only
grep "critical" storage/logs/errors.log | tail -20

# Format as JSON
cat storage/logs/errors.log | jq .
```

### View Transactions
```bash
# Last 100 transactions
tail -100 storage/logs/transactions.log

# Successful transactions
grep "success.*true" storage/logs/transactions.log | tail -20
```

### Cleanup Old Logs
```php
// Keep only 30 days of logs
Logger::clearOldLogs(30);
```

---

## Integration Points Summary

| File | What's Logged | Log Type | Severity |
|------|---------------|----------|----------|
| `backend/api/pay.php` | Payment creation success/failure | Transaction + Error | info/warning/critical |
| `backend/api/status.php` | Status query attempts | Transaction + Error | info/error |
| `public/callback.php` | Callback received/processed | PaymentStatus + Error | info/warning/critical |
| `src/PaymentStorage.php` | All stored records | Transaction + Error | info/warning |

---

## Next Steps

1. ✅ **Logging implemented** - All payment files now log transactions and errors
2. ✅ **File storage** - Logs stored in JSON format in `storage/logs/`
3. ✅ **Optional database** - SQLite ready for production use
4. → **Production deployment** - Review logs in production environment
5. → **Monitoring setup** - Monitor error logs for issues
6. → **Cleanup schedule** - Set up automated log cleanup

---

## Testing

To test the logging system:

1. **Create a payment** - POST to `/backend/api/pay.php`
   - Check `storage/logs/transactions.log` for entry

2. **Query payment status** - GET `/backend/api/status.php?tx=TRANSACTION_ID`
   - Check `storage/logs/transactions.log` for status query entry

3. **Trigger an error** - POST with invalid data
   - Check `storage/logs/errors.log` for error entry

4. **Simulate callback** - POST to `/public/callback.php`
   - Check `storage/logs/payments.log` for status entry
   - Check `storage/logs/errors.log` if signature validation fails

All logs are stored as JSON (one per line) for easy parsing and analysis.
