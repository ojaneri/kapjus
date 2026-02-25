# Lawyer Invitation Email Fix - 2026-02-25

## Problem
The "Convidar advogado" (Invite lawyer) option in case/X was not sending emails.

## Root Causes & Fixes Applied

### 1. Email sender not verified in AWS SES
- **Issue**: Email was being rejected by AWS SES because sender (`nao-responda@kapjus.com.br`) was not verified
- **Fix**: Changed `SMTP_FROM` in `.env` from `nao-responda@kapjus.com.br` to `noreply@janeri.com.br` (verified email)

### 2. Frontend JavaScript parsing incorrect API response format
- **Issue**: Frontend expected direct arrays but API returned objects with `status` + `invitations`/`logs`
- **Fix**: Updated `loadInvitations()`, `loadAccessHistory()`, and `revokeInvitation()` in `src/php/case_detail.php` to parse `data.invitations` and `data.logs`

### 3. Missing case_id in revoke invitation
- **Issue**: `revokeInvitation()` function was missing required `case_id` parameter
- **Fix**: Added `case_id` parameter to the revoke request

### 4. Enhanced email with case information
- **Issue**: Invitation emails didn't include case name and description
- **Fix**: Updated `send_invitation_email()` function to accept `case_name` and `case_description` parameters and include them in the email template. Updated `/invite_lawyer` endpoint to fetch case info from database and pass to email function.

### 5. Missing /magic-login route (FIXED)
- **Issue**: Accessing `/magic-login` returned 404 page not found
- **Fix**: Added `/magic-login` route in `public/index.php` to include magic-login.php

## Files Modified
- `.env` - Changed SMTP_FROM
- `src/php/case_detail.php` - Fixed JavaScript parsing
- `src/python/processor.py` - Enhanced email with case info
- `public/index.php` - Added /magic-login route

## Status
✅ Complete - All fixes applied and service restarted

## Test URL
https://kapjus.kaponline.com.br/magic-login?token=xY_cu7MGuWyIPdbyV0ZHafSX1dU9CIuQD7OJmrDtJwH0IfY-bA2cylS3W4apVxCx33IRBgas-CFPy4qvikCsQw&case_id=6
