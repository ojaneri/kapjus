# PDF URL Domain Fix

## Problem
PDF viewer was loading from `https://mozilla.github.io/storage/uploads/` instead of the correct kapjus domain.

## Root Cause
- File: `src/php/case_detail.php`
- Line 435: `currentPdfUrl` was set as a relative path `/storage/uploads/...`
- Line 441: The Mozilla iframe viewer received the relative URL and interpreted it as `https://mozilla.github.io/storage/uploads/...`

## Solution Applied
Modified line 441 to use an absolute URL by prepending `window.location.origin`:

```javascript
// Before (broken):
viewer.src = 'https://mozilla.github.io/pdf.js/web/viewer.html?file=' + encodeURIComponent(currentPdfUrl);

// After (fixed):
viewer.src = 'https://mozilla.github.io/pdf.js/web/viewer.html?file=' + encodeURIComponent(window.location.origin + currentPdfUrl);
```

## Result
- PDF URL now correctly resolves to `https://kapjus.kaponline.com.br/storage/uploads/...`
- Commit: `4872525`

## Date
2026-02-09
