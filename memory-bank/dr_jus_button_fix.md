# Perguntar ao Dr. Jus Button Fix

## Problem
When a basic search returns "Nenhum resultado encontrado", clicking "Perguntar ao Dr. Jus" button did nothing.

## Root Cause
The button's onclick handler at [`case_detail.php:2071`](src/php/case_detail.php:2071) was incomplete:

```javascript
// BEFORE (broken)
onclick="setSearchMode('ia'); document.getElementById('unified-input').value = ${JSON.stringify(_searchState.query)}; document.getElementById('unified-input').focus();"
```

The button only:
1. Switched to IA mode
2. Filled the input with the previous search query
3. Focused the input

**It did NOT actually submit the search!** The user would need to manually press Enter, which was not obvious.

## Fix Applied
Added `dispatchUnifiedSearch()` call with a small timeout to ensure the UI updates first:

```javascript
// AFTER (fixed)
onclick="setSearchMode('ia'); document.getElementById('unified-input').value = ${JSON.stringify(_searchState.query)}; setTimeout(() => dispatchUnifiedSearch(), 100);"
```

## Changes
- [`src/php/case_detail.php`](src/php/case_detail.php): Line 2071 - Added `setTimeout(() => dispatchUnifiedSearch(), 100);`

## Status
- ✅ Syntax validated
- ✅ Permissions applied (www-data:webdev, 775)
- ✅ Committed
