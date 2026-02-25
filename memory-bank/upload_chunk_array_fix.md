# Upload Chunk Array to String Fix

## Date: 2026-02-25

## Problem
Upload was failing with error:
```
PHP Error Array to string conversion in /var/www/html/kapjus.kaponline.com.br/public/index.php:27
```

## Root Cause Analysis
The `debug_log()` function signature is:
```php
function debug_log($level, $message, $context = []) {
```

The `$message` parameter is expected to be a string. However, developers were calling it with an array as the second parameter:
```php
debug_log('UPLOAD_CHUNK_REQUEST', [
    'method' => $_SERVER['REQUEST_METHOD'],
    ...
]);
```

This passed the array as `$message`, which was then used in string interpolation at line 27:
```php
$log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}\n";
```

This caused PHP's "Array to string conversion" error.

## Fix Applied
Changed three debug_log calls in `public/index.php` to pass a proper string message as the second parameter, with the array as the third parameter (context):

1. **Line 678** - UPLOAD_CHUNK_REQUEST:
```php
debug_log('UPLOAD_CHUNK_REQUEST', 'Processing upload chunk request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    ...
]);
```

2. **Line 702** - UPLOAD_CHUNK_PARAMS:
```php
debug_log('UPLOAD_CHUNK_PARAMS', 'Extracting upload parameters', [
    'upload_id' => $upload_id,
    ...
]);
```

3. **Line 728** - UPLOAD_CHUNK_RESPONSE:
```php
debug_log('UPLOAD_CHUNK_RESPONSE', 'Chunk upload response received', [
    'response' => substr($response, 0, 500)
]);
```

## Files Modified
- `public/index.php` - Fixed debug_log parameter ordering

## Verification
- PHP syntax validated: `php -l public/index.php` ✓
- File permissions applied: chown www-data:webdev, chmod 775 ✓
- Git commit: 5977cbf
