# Forgot Password Implementation

## Overview
A complete forgot password functionality has been added to your E-Shop application. Users can now reset their passwords by receiving a secure reset link via email.

## Files Created

### 1. forgot_password.php
- **Location**: `shared/forgot_password.php`
- **Purpose**: Allows users to request a password reset by entering their email address
- **Features**:
  - Email validation
  - Secure token generation
  - Email notification with reset link
  - User-friendly UI matching the existing design
  - Security: Doesn't reveal if email exists in the system

### 2. reset_password.php
- **Location**: `shared/reset_password.php`
- **Purpose**: Allows users to set a new password using the reset token
- **Features**:
  - Token validation (checks expiry and usage)
  - Password confirmation
  - Minimum password length validation (8 characters)
  - Password visibility toggle
  - Marks token as used after successful reset
  - Sends confirmation email after password change
  - Logs action in audit trail

### 3. database_update_password_reset.sql
- **Location**: Root directory
- **Purpose**: SQL script to create the password_resets table
- **Table Structure**:
  - `id`: Primary key
  - `user_id`: Foreign key to users table
  - `token`: Unique 64-character token
  - `expires_at`: Token expiration time (1 hour)
  - `used`: Boolean flag to prevent token reuse
  - `created_at`: Timestamp of creation

## Updated Files

### login.php
- Added "Forgot Password?" link below the login form
- Links to `forgot_password.php`

## Database Changes

A new table `password_resets` has been created with the following features:
- Stores password reset tokens
- Tracks token expiration (1 hour validity)
- Prevents token reuse
- Cascades deletion when user is deleted
- Indexed for fast lookups

## How It Works

1. **Request Reset**:
   - User clicks "Forgot Password?" on login page
   - Enters their registered email address
   - System generates a unique token and sends reset link via email

2. **Email Notification**:
   - User receives an email with a reset link
   - Link contains the unique token as a parameter
   - Token expires after 1 hour

3. **Reset Password**:
   - User clicks the link and lands on reset_password.php
   - System validates the token (checks if valid, not expired, not used)
   - User enters and confirms new password
   - Password must be at least 8 characters
   - Token is marked as used after successful reset

4. **Confirmation**:
   - User receives confirmation email
   - Failed login attempts are reset
   - Action is logged in audit trail
   - User can now login with new password

## Security Features

1. **Token Security**:
   - Cryptographically secure random tokens (64 hex characters)
   - One-time use only
   - 1-hour expiration
   - Stored securely in database

2. **Email Privacy**:
   - Doesn't reveal if email exists in system (prevents user enumeration)
   - Generic success message for invalid emails

3. **Password Requirements**:
   - Minimum 8 characters
   - Password confirmation required
   - Hashed using PHP's password_hash()

4. **Audit Trail**:
   - All password reset requests logged
   - Successful resets logged with user ID

## Testing the Feature

1. **Navigate to Login**: http://localhost/product/shared/login.php
2. **Click**: "Forgot Password?" link
3. **Enter**: A registered email address
4. **Check Email**: Look for the reset email
5. **Click Link**: In the email to reset password
6. **Set New Password**: Enter and confirm new password
7. **Login**: Use new password to login

## Email Configuration

The system uses the existing PHPMailer configuration in `shared/mailer.php`:
- SMTP Server: Gmail (smtp.gmail.com)
- Port: 587 (TLS)
- Sender: kevinjan.buenvenida06@gmail.com

**Note**: Ensure users have valid email addresses in the database for this feature to work properly.

## Troubleshooting

- **Email not received**: Check spam folder, verify SMTP settings in mailer.php
- **Token expired**: Request a new reset link (tokens expire after 1 hour)
- **Token already used**: Request a new reset link
- **Invalid token**: Ensure the full URL is copied from the email
