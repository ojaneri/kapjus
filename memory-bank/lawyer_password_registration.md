# Lawyer Invitation + Password Registration - 2026-02-25

## New Flow Implemented

### 1. Lawyer receives magic link invitation
- When invited, lawyer gets email with magic link to `/magic-login?token=XXX&case_id=YYY`
- Clicking link shows password registration form instead of auto-accept

### 2. Password Registration Flow
- Lawyer sees form with:
  - Name (pre-filled from invitation)
  - Email/Login (pre-filled, read-only)
  - Password field (min 6 characters)
  - Confirm password field
- After submitting:
  - Creates/updates user in `users` table with password hash
  - Marks invitation as "accepted"
  - Sends confirmation email with login credentials
  - Logs lawyer into the system

### 3. Confirmation Email
- Sent after successful password registration
- Contains:
  - Login (email)
  - Link to login page

### 4. Login Page Updates
- Added "Esqueci minha senha" link
- Links to `/forgot-password`

### 5. Forgot Password Flow
- User enters email on `/forgot-password`
- System checks if email exists (always shows success to prevent enumeration)
- If exists, sends password reset email with link
- Link goes to `/forgot-password?token=XXX&email=YYY`
- User enters new password and confirms
- Password is updated in users table

## Files Modified

### PHP Files
- `public/magic-login.php` - Password registration form UI
- `public/forgot-password.php` - NEW: Forgot password page
- `public/index.php` - Added /forgot-password route
- `src/php/auth.php` - Added "Esqueci minha senha" link on login

### Python Files
- `src/python/processor.py` - Added endpoints:
  - `/register_lawyer_password` - Register password for invited lawyer
  - `/forgot_password` - Request password reset
  - `/reset_password` - Complete password reset
  - `send_password_registered_email()` - Confirmation email
  - `send_password_reset_email()` - Reset email
  - `password_reset_tokens` table for reset tokens

## Database Changes
- Added `password_reset_tokens` table:
  - id, email, token, token_hash, expires_at, used_at, created_at

## Test URLs
- Login: https://kapjus.kaponline.com.br/login
- Forgot Password: https://kapjus.kaponline.com.br/forgot-password
- Magic Login (with token): https://kapjus.kaponline.com.br/magic-login?token=XXX&case_id=YYY
