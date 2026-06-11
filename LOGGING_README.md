# Logging and Database System

A comprehensive transaction and error logging system with optional database support for the SK Website Backend.

## Features

- **File-based logging**: JSON-formatted logs stored in `storage/logs/`
- **Database logging**: Optional SQLite, MySQL, or PostgreSQL support
- **Transaction tracking**: Log all payment transactions
- **Error handling**: Detailed error logging with stack traces
- **Payment status**: Track payment status changes
- **API requests**: Log all external API calls
- **Free hosting friendly**: SQLite requires no server, works with free hosting

## Architecture

### Three Main Components

1. **Logger** (`Logger.php`)
   - Main logging service
   - Supports file and database logging
   - Methods for different log types

2. **DatabaseManager** (`DatabaseManager.php`)
   - Handles database connectivity
   - Supports SQLite, MySQL, PostgreSQL
   - Creates and manages log tables
   - Provides query interface

3. **Configuration** (`.env`)
   - Database type selection
   - Optional database credentials

## Installation

### Step 1: Files Created

- `src/Logger.php` - Main logging class
- `src/DatabaseManager.php` - Database connectivity class
- `src/LoggingExamples.php` - Usage examples
- `.env.example` - Updated with database configuration

### Step 2: Update Your Bootstrap/Entry Point

Add to your `bootstrap.php` or main entry file:

```php
// File-based logging only (recommended for free hosting)
\App\Logger::initialize();

// OR with database support
// $db = new \App\DatabaseManager('sqlite');
// \App\Logger::initialize($db);
```

## Database Options

### SQLite (Recommended for Free Hosting)

**Pros:**
- No server required
- Works on free hosting plans
- Perfect for small to medium projects
- Simple setup

**Setup:**
```php
$db = new \App\DatabaseManager('sqlite');
\App\Logger::initialize($db);
```

Database file: `storage/database.sqlite`

### MySQL

**Setup:**
```php
// .env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=payments
DB_USERNAME=root
DB_PASSWORD=password
```

```php
$db = new \App\DatabaseManager('mysql');
\App\Logger::initialize($db);
```

### PostgreSQL

**Setup:**
```php
// .env
DB_TYPE=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=payments
DB_USERNAME=postgres
DB_PASSWORD=password
```

```php
$db = new \App\DatabaseManager('pgsql');
\App\Logger::initialize($db);
```

## Usage Examples

### Log a Transaction

```php
use App\Logger;

Logger::logTransaction([
    'transaction_id' => 'TXN_12345',
    'payment_id' => 'PAY_98765',
    'amount' => 99.99,
    'currency' => 'EUR',
    'status' => 'completed',
]);
```

### Log an Error

```php
Logger::logError(
    "Payment processing failed",
    [
        'payment_id' => 'PAY_98765',
        'file' => __FILE__,
        'line' => __LINE__,
        'trace' => debug_backtrace(),
    ],
    'critical' // Severity: 'info', 'warning', 'error', 'critical'
);
```

### Log Payment Status Change

```php
Logger::logPaymentStatus('PAY_98765', 'confirmed', [
    'previous_status' => 'pending',
    'gateway_response' => 'approved',
    'authorization_code' => 'AUTH_12345',
]);
```

### Log API Request

```php
Logger::logApiRequest(
    'https://api.payment-gateway.com/process',
    'POST',
    ['amount' => 99.99, 'currency' => 'EUR'],
    ['status' => 200, 'response_code' => 'SUCCESS']
);
```

## Integration Points

### 1. PaymentStorage Integration

Update `src/PaymentStorage.php`:

```php
public static function append($file, array $record)
{
    // ... existing code ...
    
    // Add logging
    Logger::logTransaction($record);
}
```

### 2. Error Handler Integration

In your error handling code:

```php
try {
    // Payment processing
} catch (Exception $e) {
    Logger::logError(
        $e->getMessage(),
        [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ],
        'critical'
    );
}
```

### 3. API Call Integration

When calling AllSecure API:

```php
Logger::logApiRequest(
    $endpoint,
    'POST',
    $requestData,
    $response
);
```

### 4. Callback Handler Integration

In `public/callback.php`:

```php
Logger::logPaymentStatus($paymentId, $status, $callbackData);
```

## Log Storage

### File Logs

Location: `storage/logs/`

Files:
- `transactions.log` - Payment transactions (JSON, one per line)
- `errors.log` - Errors and exceptions (JSON, one per line)
- `payments.log` - Payment status changes (JSON, one per line)
- `api_requests.log` - API calls (JSON, one per line)

### Database Logs

If using database logging, tables are automatically created:

- `logs_transactions` - Transaction records
- `logs_errors` - Error records with severity levels
- `logs_payments` - Payment status changes
- `logs_api_requests` - API request logs

All tables include timestamps and IP address tracking.

## Querying Logs

### From File (Manual)

Logs are JSON format, one entry per line. Use a text editor or:

```bash
tail -f storage/logs/transactions.log | jq .
```

### From Database

```php
use App\Logger;

// Get recent transactions from past 7 days
$logs = Logger::getLogs('transactions', [
    'from_date' => gmdate('c', strtotime('-7 days')),
]);

// Get errors by IP address
$logs = Logger::getLogs('errors', [
    'ip_address' => '192.168.1.1',
]);

// Clean up logs older than 30 days
Logger::clearOldLogs(30);
```

## Configuration (.env)

```env
# Enable/disable logging
ENABLE_LOGGING=true
ENABLE_DATABASE_LOGGING=false

# Database type: sqlite, mysql, pgsql
DB_TYPE=sqlite

# MySQL settings (optional)
# DB_HOST=localhost
# DB_PORT=3306
# DB_DATABASE=payments
# DB_USERNAME=root
# DB_PASSWORD=password

# PostgreSQL settings (optional)
# DB_HOST=localhost
# DB_PORT=5432
# DB_DATABASE=payments
# DB_USERNAME=postgres
# DB_PASSWORD=password
```

## Security Considerations

- Log files contain sensitive data (payment IDs, IP addresses)
- Keep `storage/` directory outside web root or protect with `.htaccess`
- For SQLite, ensure database file is not accessible via web
- Regularly clean old logs to prevent disk space issues
- Never log passwords or credit card numbers

## Performance

- **File logging**: Minimal overhead, async recommended for high volume
- **SQLite**: Good for up to millions of log entries
- **MySQL/PostgreSQL**: Best for high-volume production use

## Troubleshooting

### SQLite file not created

Ensure `storage/` directory is writable:

```bash
chmod 775 storage/
```

### Database connection errors

Check `.env` file for correct database credentials and ensure PDO drivers are installed:

```bash
php -m | grep pdo
```

### Logs not appearing

1. Check `storage/logs/` directory exists and is writable
2. Verify `Logger::initialize()` is called in bootstrap
3. Check file permissions: `chmod 755 storage/logs/`

## License

Part of SK Website Backend project.
