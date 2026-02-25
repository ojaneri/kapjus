# Notes Persistence Fix - Memory Bank Entry

## Problem
1. Notes were being saved to SQLite database but not appearing on case detail page (storage mismatch - case_detail.php used localStorage instead of database)
2. PDF viewer didn't display page-specific notes when navigating

## Solution
1. Created `/api/get_notes` endpoint in public/index.php that fetches from SQLite `case_notes` table
2. Modified src/php/case_detail.php to fetch notes from database instead of localStorage
3. Added page-specific filtering to `/api/get_notes` endpoint (source_file + source_page params)
4. Added `loadPageNotes()` function in pdf-viewer.php that displays notes for current page
5. Notes now load on page navigation, after saving, and on initial PDF load

## Database Schema
```sql
CREATE TABLE case_notes (
    id INTEGER PRIMARY KEY,
    case_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    source_file TEXT,
    source_page INTEGER,
    source_snippet TEXT,
    color TEXT DEFAULT 'yellow',
    created_at DATETIME
);
```

## Files Changed
- public/index.php - Added /api/get_notes endpoint
- src/php/case_detail.php - Changed initNotes() to fetch from database
- public/pdf-viewer.php - Added page-specific notes display

## Validation
- PHP syntax: All files pass
- File ownership: www-data:webdev, 775
- Git committed

## Date
2026-02-25
