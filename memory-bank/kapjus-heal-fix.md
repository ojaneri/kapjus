# Heal Check Fix - 2026-02-09

## Problem
The `--heal` command was always restarting the processor because the health check was failing.

## Root Causes Identified

1. **Missing `/health` endpoint**: The processor.py FastAPI app didn't have a `/health` endpoint, causing 404 responses.

2. **Faulty `check_socket()` function**: The function was outputting the HTTP code (200) and then line 94 was appending "yes", resulting in "200yes". The comparison on line 97 checked if `$socket_responsive` equals "yes" exactly, which failed.

## Fixes Applied

### 1. Added `/health` endpoint to processor.py
```python
@app.get("/health")
async def health_check():
    """Health check endpoint for service monitoring"""
    return {"status": "healthy", "service": "kapjus-rag"}
```

### 2. Fixed `check_socket()` function in service.sh
```bash
# Check socket responsiveness (returns 0 if HTTP 200, 1 otherwise)
check_socket() {
    if [ -S "$SOCKET_PATH" ]; then
        http_code=$(curl --unix-socket "$SOCKET_PATH" -s -o /dev/null -w "%{http_code}" "http://localhost/health" 2>/dev/null)
        if [ "$http_code" = "200" ]; then
            return 0
        fi
    fi
    return 1
}
```

## Result
- Socket responsive: yes
- Health check: pass
- Action taken: none needed

---

## Document Migration Fix - 2026-02-09

### Problem
Documents were not appearing on case/1 page because they were stored with case_id='teste123' instead of case_id='1'.

### Root Cause
The test documents were uploaded with test case_id='teste123' during development.

### Fix Applied
Migrated documents from case_id='teste123' to case_id='1':
```sql
UPDATE documents SET case_id='1' WHERE case_id='teste123';
```

### Documents Now Available on case/1
| ID | Filename | Page |
|----|----------|------|
| 16 | test.txt | 1 |
| 17 | test_ocr.png | 1 |
| 18 | test_real.pdf | 1 |
| 19 | test.docx | 1 |
| 20 | test.xlsx | 1 |

### Files in Storage
- storage/uploads/test.txt
- storage/uploads/test_ocr.png
- storage/uploads/test.docx
- storage/uploads/test.xlsx

### Status
- Database: Updated ✓
- Documents visible: case/1 shows 5 documents ✓
