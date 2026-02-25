# Lawyer Invitation Flow - Password Registration + Access Email

## Problem
User requested changes to the lawyer invitation flow:
1. Lawyer receives magic link invitation email
2. Clicks the link → sees password registration form
3. After registering password → system sends NEW email with access link
4. When clicking the access link → login page with email pre-filled, just needs to type password
5. Add "Esqueci minha senha" (forgot password) option on login page

## Implementation

### 1. Magic Login Page (public/magic-login.php)
- Already modified to show password registration form
- After password registration, calls `/send_access_email` endpoint
- Shows success message with link to login page

### 2. Python Backend (src/python/processor.py)
Added new endpoint and function:
- `/send_access_email` endpoint (lines ~2378)
- `send_access_confirmation_email()` function to send access confirmation email
- The email includes the login link: `https://kapjus.kaponline.com.br/login?email=lawyer@email.com`

### 3. Login Page with Email Pre-fill (src/php/auth.php + public/index.php)
- Modified `render_login_page()` to accept optional `$prefill_email` parameter
- Modified login page route in index.php to read `?email=` parameter from URL
- When accessing `/login?email=lawyer@email.com`, the email field is pre-filled

### 4. Forgot Password (Already implemented)
- "Esqueci minha senha" link exists on login page (auth.php line 198)
- Forgot password page exists at `/forgot-password`
- Uses `/forgot_password` and `/reset_password` endpoints

## Files Modified
- `src/python/processor.py` - Added `/send_access_email` endpoint and email function
- `src/php/auth.php` - Added email pre-fill support to login page
- `public/index.php` - Read email parameter from URL and pass to login page

## Status
✅ Complete - All functionality implemented

## Test Flow
1. Invite lawyer → they receive magic link email
2. Click link → see password registration form
3. Register password → new access email is sent
4. Click access link → login page with email pre-filled
5. Type password → logged in to case
6. "Esqueci minha senha" on login page works for password reset
