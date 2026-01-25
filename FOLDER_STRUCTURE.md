# Project Folder Structure

This document outlines the organization of files by user role.

## 📁 Directory Layout

```
product/
├── admin/                      # Admin-only functions
│   ├── dashboard.php          # Admin dashboard - manage users, view stats
│   ├── create_user.php        # Create new users
│   ├── audit_trail.php        # View audit logs
│   └── records.php            # View system records
│
├── staff/                      # Staff-only functions
│   └── dashboard.php          # Staff dashboard - manage products, approve orders
│
├── guest/                      # Guest/Customer functions
│   ├── shop.php               # Browse and view products
│   └── cart.php               # Shopping cart management
│
├── shared/                     # Shared files accessible to all roles
│   ├── database.php           # Database connection and helper functions
│   ├── index.php              # Home page / landing page
│   ├── login.php              # Login page
│   ├── login_otp.php          # OTP login interface
│   ├── otp_verify.php         # OTP verification form
│   ├── otp_verify_process.php # OTP verification processing
│   └── logout.php             # Logout functionality
│
├── vendor/                     # Composer dependencies
│
└── Root files (maintained for backward compatibility):
    ├── admin_dashboard.php
    ├── staff_dashboard.php
    ├── shop.php
    ├── cart.php
    ├── database.php
    ├── login.php
    ├── logout.php
    └── ... (other files)
```

## 📋 File Organization by Role

### Admin Role (`admin_sec`)
Files in `admin/` folder handle administrative functions:
- **dashboard.php** - Create and manage users, reset passwords, delete users
- **create_user.php** - Create new users with different roles
- **audit_trail.php** - View all user activities and audit logs
- **records.php** - System records and management

### Staff Role (`staff_user`)
Files in `staff/` folder handle staff functions:
- **dashboard.php** - Manage products (add, edit, delete), approve orders, ship items

### Guest/Customer Role (`regular_user`, `guest_user`)
Files in `guest/` folder handle customer functions:
- **shop.php** - Browse products, view product details
- **cart.php** - Add/remove items, checkout

### Shared Files
Files in `shared/` folder are used by all roles:
- **index.php** - Home page / initial landing
- **login.php** - User login
- **login_otp.php** - OTP login form
- **otp_verify.php** - OTP verification interface
- **otp_verify_process.php** - Server-side OTP verification
- **logout.php** - Logout/session termination
- **database.php** - Database connection, queries, and helper functions

## 🔗 How to Update Links

If you're using the new folder structure, update your redirect URLs:

```php
// Old way (still works - root level)
header("Location: admin_dashboard.php");

// New way (organized structure)
header("Location: admin/dashboard.php");
```

## 📝 Notes

- Root-level files are maintained for **backward compatibility**
- New developments should use the organized folder structure
- All files in `shared/` should be accessible from any role
- Use relative paths when redirecting between files in the same folder
- Use the `shared/` folder for common utilities and database connections
