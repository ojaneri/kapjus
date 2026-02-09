# Upload Chunk Fix - Root Cause Analysis

## Date: 2026-02-09

## Problem
Files uploaded via chunked upload were not appearing. The metadata showed `uploaded_chunks: []` meaning no chunks were uploaded. Two issues were identified:

1. **JavaScript Error**: "Failed to execute 'json' on 'Response': body stream already read"
2. **Logic Bug**: Frontend marked ALL chunks as "already uploaded" and skipped upload loop

## Root Cause

### Issue 1: Response Body Already Read
The frontend called `statusResponse.json()` multiple times without cloning the response first. In the Fetch API, the response body can only be read once.

### Issue 2: missing_chunks Logic Bug (PRIMARY CAUSE)
The frontend incorrectly populated `uploadedChunks` with `missing_chunks` from the server response. The `missing_chunks` array contains chunk indices that are MISSING and need to be uploaded, but the code was adding them to `uploadedChunks` (chunks already uploaded), causing the upload loop to skip ALL chunks.

**Buggy code flow:**
1. Server returns `missing_chunks: [0, 1, 2]` (chunks that need uploading)
2. Frontend adds these to `uploadedChunks: [0, 1, 2]`
3. Upload loop checks `if (uploadedChunks.includes(chunkIndex))` → TRUE for all chunks
4. All chunks marked as "already uploaded" → upload loop skips everything

## Fix Applied

### 1. Clone Response Before Multiple Reads
[`src/php/case_detail.php`](src/php/case_detail.php:796) line 796:
```javascript
const statusData = await statusResponse.clone().json();
```

[`src/php/case_detail.php`](src/php/case_detail.php:927) line 927:
```javascript
return response.clone().json();
```

### 2. Remove missing_chunks Logic Bug
[`src/php/case_detail.php`](src/php/case_detail.php:797-810) lines 797-810:
- Removed code that adds `missing_chunks` to `uploadedChunks`
- `uploadedChunks` should only contain chunks that are actually uploaded

### 3. Fix Python Response Format
[`src/python/processor.py`](src/python/processor.py:511) line 511:
```python
return {"status": "ok", "uploaded_chunks": metadata.get("uploaded_chunks", [])}
```
Changed `upload_status` to return `uploaded_chunks` (array) instead of `uploaded_chunks` (count).

## Files Modified
- `src/php/case_detail.php` - Fixed response cloning and removed buggy logic
- `src/python/processor.py` - Fixed upload_status response format

## Lessons Learned
1. Never read a Fetch Response body multiple times without cloning
2. Understand the semantic meaning of variables: `missing_chunks` ≠ `uploaded_chunks`
3. Test edge cases: what happens when ALL chunks are missing?
