# Upload Chunk Debug Session

## Date: 2026-02-09

## Problem
- `uploadChunks` JavaScript loop never fires
- No `/api/upload_chunk` requests in debug.log
- Metadata files show `uploaded_chunks: []`
- No `chunk_*` files in chunk directories

## Root Cause Analysis
Debug log shows sequence:
1. `/api/upload_init` - ✅ Called
2. `/api/upload_status` - ✅ Called  
3. `/api/upload_complete` - ✅ Called
4. `/api/upload_chunk` - ❌ NEVER called

## Diagnosis
Added comprehensive JavaScript logging to trace:
1. `upload_init` response and `uploadId` extraction
2. `upload_status` response
3. `uploadChunks` function entry and parameters
4. Each chunk loop iteration
5. XHR request send, onload, onerror, ontimeout, onabort events
6. Catch block error logging

## Changes Made
- Added `console.log()` calls in [`case_detail.php`](src/php/case_detail.php):
  - Line ~773: Log `upload_init` response
  - Line ~776: Log `uploadId` value
  - Line ~779: Error if `uploadId` is null
  - Line ~783: Log `upload_status` response
  - Line ~791: Log `uploadChunks` entry
  - Line ~796-797: Log skipped chunks
  - Line ~801: Log chunk processing
  - Line ~860: Log XHR send
  - Line ~868-876: Log XHR callbacks (onload, onerror, ontimeout, onabort)
  - Line ~887: Log catch block errors

## Next Steps
1. Trigger a file upload from the browser
2. Check browser console for the new `[UPLOAD]` log messages
3. Identify which log message is missing to pinpoint the failure location

## Files Modified
- `src/php/case_detail.php` - Added diagnostic logging
