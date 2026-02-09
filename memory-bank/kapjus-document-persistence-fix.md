rece c# KAPJUS Debug Session - Document Persistence Issue

## Problem
Documents uploaded to case 1 were not persisting across page refreshes. Users reported that:
- Uploaded files showed up immediately after upload
- But disappeared after page refresh
- Only old documents were visible

## Root Cause
**Multiple Python processor instances running concurrently** (2 processes: PID 2244396 and 2244817)

This caused:
1. Socket conflicts - requests were routed to wrong process instances
2. `/upload_chunk` endpoint returning 404 errors
3. Upload failures with "Missing chunks" message
4. Broken document storage flow

## Investigation Steps Performed
1. Checked SQLite database - Documents ARE stored with correct case_id
2. Verified `/documents` endpoint query - Correctly filters by case_id
3. Checked case_detail.php fetchDocuments() - Correctly passes case_id
4. Examined processor.log - Found 404 errors on `/upload_chunk`
5. Identified duplicate Python processes via `ps aux`

## Fix Applied
1. Killed all duplicate processor processes: `pkill -9 -f "python3.*processor.py"`
2. Cleaned up stale socket files
3. Enabled and started systemd service: `kapjus-rag.service`
4. Verified documents API returns correct data for case_id=1

## Files Modified
- None (configuration/process management issue, not code bug)

## Service Configuration
- Systemd service: `/etc/systemd/system/kapjus-rag.service`
- Socket path: `/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock`
- Socket permissions: 666

## Verification
```bash
# Documents API returns correct data
php -r 'require "/var/www/html/kapjus.kaponline.com.br/src/php/socket_client.php"; 
       $resp = call_python_api("/documents", ["case_id" => "1"]); 
       echo $resp;'
# Output: [{"filename":"web_test.pdf","page_count":1},{"filename":"test_document.pdf",...}]
```

## Prevention
- Use systemd service for process management
- Avoid manual nohup commands
- Monitor for duplicate processes

## Date
2026-02-09
