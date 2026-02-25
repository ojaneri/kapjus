# PDF Display and Filename Decoding Fix

## Problem
1. When clicking on a PDF source, the viewer appeared empty or failed to load.
2. Files with spaces or special characters in their names were not being found (404).

## Root Cause
1. **External Viewer & Authentication:** The application was using an external PDF.js viewer hosted on `mozilla.github.io`. Since `/storage/uploads/` is protected by session authentication, the external viewer (cross-origin) could not send the required session cookies, resulting in a 403 error or redirect to login.
2. **Missing URL Decoding:** In `public/index.php`, the filename extracted from the URL path was not being decoded. If a filename contained spaces (encoded as `%20`), `download.php` would look for a file with the literal string `%20` in its name, which didn't exist on disk.

## Solution Applied

### 1. Switch to Native Browser Viewer
Modified `src/php/case_detail.php` to use the browser's native PDF viewer instead of the external Mozilla one. This ensures the request is same-origin, allowing session cookies to be sent automatically.

```javascript
// File: src/php/case_detail.php
// Before:
viewer.src = 'https://mozilla.github.io/pdf.js/web/viewer.html?file=' + encodeURIComponent(window.location.origin + currentPdfUrl) + pageHash;

// After:
viewer.src = currentPdfUrl + pageHash;
```

### 2. Implement Filename Decoding
Modified `public/index.php` to decode the filename from the URL path before passing it to the download controller.

```php
// File: public/index.php
// Before:
$filename = basename(substr($path, strlen('/storage/uploads/')));

// After:
$filename = rawurldecode(basename(substr($path, strlen('/storage/uploads/'))));
```

## Result
- PDFs now load correctly in the embedded viewer because authentication is preserved.
- Files with spaces or special characters are correctly found and served.
- Jump-to-page functionality (`#page=N`) is preserved as most modern browsers support it natively.

## Date
2026-02-24
