# Upload Chunk Fix - Root Cause Analysis

## Problem
Files uploaded via chunked upload were not appearing. The metadata showed `uploaded_chunks: []` meaning no chunks were uploaded.

## Root Cause
The frontend sends chunk upload requests using `XMLHttpRequest` directly to `/api/upload_chunk`. While this should route through `index.php` → `socket_client.php`, the request was not being handled properly because:

1. The `upload_chunk` action was not being processed correctly in `socket_client.php`
2. The multipart form data parsing was failing silently
3. No debug logs were being created for the `upload_chunk` endpoint (unlike `upload_init`, `upload_status`, and `upload_complete`)

## Evidence from Logs
```
# Working endpoints (logged):
/api/upload_init -> logged
/api/upload_status -> logged
/api/upload_complete -> logged (but fails with "Missing chunks: [0]")

# Broken endpoint (NOT logged):
/api/upload_chunk -> never appears in logs
```

## Solution
Added direct handling for `upload_chunk` in `public/index.php`:

1. Added `call_python_api()` function to handle socket communication with the Python service
2. Added special route for `upload_chunk` that:
   - Logs the request details for debugging
   - Parses multipart form data from `$_FILES` if available
   - Falls back to parsing raw multipart data from `php://input` if `$_FILES` is empty
   - Forwards the chunk data to the Python service via Unix socket
   - Returns the response to the frontend

## Files Modified
- `public/index.php` - Added direct `upload_chunk` handling with multipart parsing

## Files Created
- `public/upload_debug.php` - Debug script to trace upload_chunk requests
