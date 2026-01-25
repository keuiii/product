# E-Commerce System - Implementation Guide

## Complete System Overview

This document provides a comprehensive overview of all implemented features and components.

---

## 1. SECURITY IMPLEMENTATION

### 1.1 Password Hashing
- **Algorithm**: PHP's `password_hash()` with PASSWORD_DEFAULT (bcrypt)
- **Verification**: `password_verify()` for login validation
- **Storage**: 255-character hashed passwords in database
- **File**: `database.php`, `login.php`, `index.php`

### 1.2 Account Lockout System
- **Trigger**: 3 failed login attempts
- **Lock Duration**: 15 minutes (900 seconds)
- **Fields**: 
  - `failed_login_attempts` - tracks attempts
  - `is_locked` - boolean flag
  - `locked_until` - DATETIME for unlock time
- **Functions in database.php**:
  - `lockAccount()` - Locks account
  - `unlockAccount()` - Unlocks account
  - `incrementFailedAttempts()` - Increments counter
  - `resetFailedAttempts()` - Resets to 0
  - `isAccountLocked()` - Checks lock status

### 1.3 OTP Verification System
- **OTP Length**: 6 digits
- **Expiry**: 10 minutes (600 seconds)
- **Table**: `otp_registrations`
- **Flow**: 
  1. User registers with email
  2. OTP generated and stored
  3. Email sent to user
  4. User verifies OTP on `/otp_verify.php`
  5. Account created upon verification

### 1.4 Audit Trail
- **Table**: `audit_trail` with 6 fields
- **Tracked Actions**:
  - USER_CREATE, USER_DELETE, USER_UPDATE
  - PASSWORD_RESET
  - LOGIN, LOGIN_FAILED
  - PRODUCT_ADD, PRODUCT_EDIT, PRODUCT_DELETE
  - ADD_TO_CART
  - CHECKOUT
  - ORDER_APPROVED
  - REGISTRATION
- **Access**: Admin only via `/audit_trail.php`
- **Function**: `logAudit()` in database.php

---

## 2. ROLE-BASED ACCESS CONTROL

### 2.1 User Roles
| Role | Permissions | Access |
|------|-------------|--------|
| admin_sec | manage_users, reset_password, view_audit_trail, view_orders, manage_products, approve_orders | Admin Dashboard |
| staff_user | manage_products, approve_orders, view_orders | Staff Dashboard |
| regular_user | view_products, add_to_cart, checkout, view_orders | Shop, Cart |
| guest_user | view_products | Shop (view-only) |

### 2.2 Permission System
- **Table**: `permissions` (role + permission mapping)
- **Function**: `hasPermission($conn, $user_id, $permission)` in database.php
- **Implemented**: Role checking on each protected page

### 2.3 Role-Based Redirects
- **Admin**: `/admin_dashboard.php`
- **Staff**: `/staff_dashboard.php`
- **Regular User**: `/shop.php`
- **Guest**: `/shop.php` (read-only)

---

## 3. USER MANAGEMENT

### 3.1 Admin Dashboard (`admin_dashboard.php`)
**Features:**
- Create new users (staff, regular, guest roles)
- Edit user profile (email, role)
- Delete users (except self)
- Reset user passwords
- View all users with status
- Dashboard statistics:
  - Total users
  - Total orders
  - Pending orders
  - Shipped orders

**Functionality:**
- Form validation for new passwords (8+ chars, uppercase, lowercase, number, special char)
- Modal dialogs for edit and reset operations
- Prevents self-deletion
- Comprehensive error handling

### 3.2 User Registration (`index.php`)
**Flow:**
1. Email-based registration (not username)
2. Password validation with requirements display
3. Generate OTP and send via email
4. Store OTP registration record

**Validation:**
- Password strength requirements display
- Confirm password matching
- Email format validation
- Duplicate email checking

### 3.3 Email-Based OTP Registration (`otp_verify.php`)
**Features:**
- Verify OTP code (6 digits)
- Auto-generate unique username from email
- Create account as `regular_user` role
- Resend OTP functionality
- Automatic login after verification
- Session cleanup

### 3.4 Login System (`login.php`)
**Security:**
- Account lockout detection
- Failed attempt counter
- Display remaining unlock time
- Password verification
- Session-based authentication
- Audit logging

**Login Flow:**
1. Username/password entry
2. Check if account locked
3. Verify password hash
4. Check for lockout
5. Create session
6. Log audit entry
7. Redirect to role-specific dashboard

---

## 4. E-COMMERCE FUNCTIONALITY

### 4.1 Product Management (`staff_dashboard.php`)
**Staff Capabilities:**
- Add products:
  - Product name, description
  - Price (decimal), quantity
  - Automatically tracked by creator
  
- Edit products:
  - Modal-based editing
  - Update all fields
  - Real-time availability

- Delete products:
  - Confirmation dialog
  - Soft delete recommended (cascade handling)

**Statistics:**
- Total products count
- Pending orders count

### 4.2 Product Catalog (`shop.php`)
**Display:**
- Grid layout of all products
- Product name, description (truncated)
- Price formatting
- Stock status (in stock, low, out of stock)

**Interactions:**
- Guest users: View only
- Regular users: Add to cart with quantity selection
- Authentication-aware UI

**Features:**
- Stock availability checking
- Quantity validation
- Cart count badge
- Role-based button states

### 4.3 Shopping Cart (`cart.php`)
**Operations:**
- View cart items with details
- Update quantities
- Remove items
- Calculate total

**Cart Management:**
- Unique constraint on (user_id, product_id)
- Quantity incrementing for existing items
- Stock verification before checkout
- Empty cart after successful order

**Checkout Process:**
1. Verify cart not empty
2. Check stock availability
3. Calculate total amount
4. Create order with "pending" status
5. Create order items with snapshot prices
6. Update product inventory
7. Clear cart
8. Log audit entry

---

## 5. ORDER & PAYMENT SYSTEM

### 5.1 Order Management (`staff_dashboard.php`)
**Staff Actions:**
- View all pending orders
- Order details:
  - Customer info (username, email)
  - Order total
  - Order date
  - Current status

**Order Approval:**
1. Staff clicks "Approve & Ship"
2. Order status changes to "approved"
3. Approval timestamp recorded
4. Approved by staff user ID stored
5. Email notification sent to customer
6. Audit log created

### 5.2 Order Tables
**Orders Table:**
- Order ID, User ID
- Total amount
- Status (pending, approved, shipped, cancelled)
- Approved by (staff user ID)
- Approved at (timestamp)
- Shipped at (timestamp)
- Shipping email sent flag

**Order Items Table:**
- Order ID, Product ID
- Quantity purchased
- Price at time of order (snapshot)

### 5.3 Email Notifications
**Trigger:** Staff approves order

**Content:**
- Order ID
- Customer greeting
- Order total
- "Will be shipped soon" message

**Implementation:**
- Uses `sendEmail()` function
- HTML formatted email
- Sent to customer email address
- Shipping email sent flag updated

---

## 6. DATABASE TABLES

### Users Table
```sql
id, username, email, password (varchar 255),
role (enum), is_active, is_locked, locked_until,
failed_login_attempts, created_at, updated_at
```

### Products Table
```sql
id, product_name, description, price, quantity,
created_by (user_id), created_at, updated_at
```

### Cart Table
```sql
id, user_id, product_id, quantity, added_at
```

### Orders Table
```sql
id, user_id, total_amount, status, approved_by,
approved_at, shipped_at, shipping_email_sent,
order_date
```

### Order Items Table
```sql
id, order_id, product_id, quantity, price
```

### OTP Registrations Table
```sql
id, email, otp_code, is_verified,
created_at, expires_at
```

### Audit Trail Table
```sql
id, user_id, username, action, details, datetime
```

### Permissions Table
```sql
id, role, permission
```

### Records Table (Legacy)
```sql
id, first_name, last_name, created_at
```

---

## 7. KEY UTILITY FUNCTIONS

### In database.php:
```php
// Authentication
hasPermission($conn, $user_id, $permission)
getUserRole($conn, $user_id)

// Password & Account Management
lockAccount($conn, $user_id)
unlockAccount($conn, $user_id)
incrementFailedAttempts($conn, $user_id)
resetFailedAttempts($conn, $user_id)
isAccountLocked($conn, $user_id)

// OTP & Email
generateOTP()
sendEmail($to, $subject, $message)

// Audit
logAudit($conn, $user_id, $username, $action, $details)
```

---

## 8. FILE-BY-FILE BREAKDOWN

| File | Purpose | Role Access |
|------|---------|------------|
| index.php | User registration | Guest/All |
| login.php | User authentication | Guest/All |
| logout.php | Session termination | All |
| otp_verify.php | OTP verification | New users |
| shop.php | Product catalog | All |
| cart.php | Shopping cart | Regular users |
| admin_dashboard.php | User management | Admin |
| staff_dashboard.php | Product/Order mgmt | Staff |
| audit_trail.php | Audit logs | Admin |
| records.php | Legacy CRUD | All logged-in |
| database.php | Utilities & DB conn | All |
| setup_database.sql | Schema creation | DBA |

---

## 9. TEST ACCOUNTS (After Setup)

Generate passwords using:
```php
echo password_hash('Admin@123', PASSWORD_DEFAULT);
echo password_hash('Staff@123', PASSWORD_DEFAULT);
echo password_hash('Guest@123', PASSWORD_DEFAULT);
```

Then insert:
```sql
INSERT INTO users (username, email, password, role) VALUES 
('admin_sec', 'admin@test.com', '[hashed]', 'admin_sec'),
('staff_user', 'staff@test.com', '[hashed]', 'staff_user'),
('guest_user', 'guest@test.com', '[hashed]', 'guest_user');
```

---

## 10. SECURITY CHECKLIST

- ✅ Password hashing (bcrypt)
- ✅ Account lockout (3 attempts, 15 min)
- ✅ Prepared statements (SQL injection prevention)
- ✅ htmlspecialchars (XSS prevention)
- ✅ Session-based auth
- ✅ Role-based access control
- ✅ Audit logging
- ✅ OTP email verification
- ⚠️ TODO: CSRF tokens
- ⚠️ TODO: HTTPS requirement
- ⚠️ TODO: Rate limiting
- ⚠️ TODO: Password reset functionality
- ⚠️ TODO: 2FA implementation

---

## 11. WORKFLOW EXAMPLES

### Example 1: New User Registration & Purchase

1. User visits `/index.php`
2. Fills in email and password
3. Submits form → OTP generated
4. Receives email with OTP
5. Visits `/otp_verify.php`
6. Enters OTP → Account created
7. Automatically logged in → Shop page
8. Browses products on `/shop.php`
9. Adds items to cart
10. Goes to `/cart.php`
11. Checkout → Order placed (pending)
12. Admin sees pending order on `/staff_dashboard.php`
13. Clicks "Approve & Ship"
14. Customer receives email notification
15. Admin can see audit log on `/audit_trail.php`

### Example 2: Account Lockout

1. User enters wrong password
2. Counter increments (1/3)
3. After 3 failed attempts
4. Account locked for 15 minutes
5. User shown unlock time
6. After 15 minutes → Auto unlocked
7. OR Admin can manually unlock on admin dashboard

### Example 3: Admin User Management

1. Admin logs into `/admin_dashboard.php`
2. Clicks "Create User" button
3. Fills in username, email, password, role
4. New staff user created
5. Staff user logs in to `/staff_dashboard.php`
6. Can manage products and approve orders
7. Admin can reset staff password if needed
8. Admin can view all actions in audit trail

---

**Implementation Date**: January 21, 2026
**Version**: 1.0
