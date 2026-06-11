# Logging System - Quick Reference

## Files Created

1. **src/Logger.php** - Main logging service
2. **src/DatabaseManager.php** - Database connectivity 
3. **src/LoggingExamples.php** - Usage examples
4. **LOGGING_README.md** - Complete documentation
5. **LOGGING_INTEGRATION.md** - Integration guide
6. **LOGGING_QUICK_REFERENCE.md** - This file

## Quick Start (30 seconds)

### 1. Initialize in bootstrap.php
```php
\App\Logger::initialize();
// OR with SQLite database:
// $db = new \App\DatabaseManager('sqlite');
// \App\Logger::initialize($db);
```

### 2. Use in your code
```php
// Log a transaction
\App\Logger::logTransaction(['id' => 123, 'amount' => 99.99]);

// Log an error
\App\Logger::logError('Something went wrong', ['file' => __FILE__]);

// Log payment status
\App\Logger::logPaymentStatus('PAY_123', 'completed');

// Log API call
\App\Logger::logApiRequest('/api/pay', 'POST', $data, $response);
```

### 3. View logs
- **File logs**: `storage/logs/transactions.log`, `errors.log`, etc.
- **Database logs**: Tables in SQLite `storage/database.sqlite`

## Log Types

```
transactions.log  → All payment transactions
errors.log        → All errors with severity levels
payments.log      → Payment status changes
api_requests.log  → API calls and responses
```

## Database Setup

### SQLite (Recommended - Free, No Server)
```env
DB_TYPE=sqlite
```
Database file: `storage/database.sqlite`

### MySQL
```env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=payments
DB_USERNAME=root
DB_PASSWORD=password
```

### PostgreSQL
```env
DB_TYPE=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=payments
DB_USERNAME=postgres
DB_PASSWORD=password
```

## API Reference

### Log Transaction
```php
Logger::logTransaction([
    'transaction_id' => 'TXN_123',
    'amount' => 99.99,
    'currency' => 'EUR',
    'status' => 'completed'
]);
```

### Log Error
```php
Logger::logError(
    'Error message',
    ['file' => __FILE__, 'line' => __LINE__],
    'critical' // 'info', 'warning', 'error', 'critical'
);
```

### Log Payment Status
```php
Logger::logPaymentStatus('PAY_123', 'confirmed', [
    'code' => 'AUTH_456'
]);
```

### Log API Request
```php
Logger::logApiRequest(
    'https://api.example.com/endpoint',
    'POST',
    ['data' => 'value'],
    ['status' => 200, 'code' => 'SUCCESS']
);
```

### Query Logs (Database only)
```php
$logs = Logger::getLogs('transactions', [
    'from_date' => gmdate('c', strtotime('-7 days'))
]);
```

### Clean Old Logs
```php
Logger::clearOldLogs(30); // Keep 30 days
```

## Integration Checklist

- [ ] Add Logger::initialize() to bootstrap.php
- [ ] Create storage/logs/ directory
- [ ] Update PaymentStorage.php to call Logger::logTransaction()
- [ ] Add error handler for Logger::logError()
- [ ] Log API calls in payment processing
- [ ] Log callback processing
- [ ] Set file permissions: chmod 775 storage/
- [ ] Update .env with DB_TYPE=sqlite
- [ ] Test by checking storage/logs/ directory

## Log File Locations

```
storage/
├── logs/
│   ├── transactions.log   (JSON, one per line)
│   ├── errors.log         (JSON, one per line)
│   ├── payments.log       (JSON, one per line)
│   └── api_requests.log   (JSON, one per line)
└── database.sqlite        (if using database logging)
```

## View Logs

### Tail Log Files
```bash
tail -f storage/logs/transactions.log
```

### Format JSON Logs
```bash
cat storage/logs/transactions.log | jq .
```

### Get Recent Errors
```bash
tail -100 storage/logs/errors.log | jq 'select(.severity=="critical")'
```

## Environment Configuration

```env
# Enable/disable file logging
ENABLE_LOGGING=true

# Enable/disable database logging
ENABLE_DATABASE_LOGGING=false

# Database type: sqlite, mysql, pgsql
DB_TYPE=sqlite

# Only needed for MySQL/PostgreSQL:
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=payments
DB_USERNAME=root
DB_PASSWORD=password
```

## For Free Hosting

✅ **Recommended Setup:**
- Use SQLite (no server needed)
- Store files in `storage/logs/`
- Keep `storage/` directory outside web root
- Regular log cleanup to prevent disk space issues

```php
// Initialize with SQLite
$db = new \App\DatabaseManager('sqlite');
\App\Logger::initialize($db);

// Clean old logs monthly
Logger::clearOldLogs(30);
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Logs not created | Check `storage/` permissions: `chmod 775 storage/` |
| SQLite error | Ensure `storage/` is writable |
| DB connection failed | Verify .env database credentials |
| Logs not appearing | Confirm `Logger::initialize()` is in bootstrap |

## Example: Complete Payment Handler

```php
try {
    // Log API request
    Logger::logApiRequest('payment/create', 'POST', $data);
    
    // Process payment
    $result = processPayment($data);
    
    // Log transaction
    Logger::logTransaction(['status' => 'success']);
    
} catch (\Exception $e) {
    // Log error
    Logger::logError(
        $e->getMessage(),
        ['file' => __FILE__, 'line' => __LINE__],
        'critical'
    );
}
```

## Support

For more details, see:
- **LOGGING_README.md** - Full documentation
- **LOGGING_INTEGRATION.md** - Step-by-step integration
- **src/LoggingExamples.php** - Code examples
