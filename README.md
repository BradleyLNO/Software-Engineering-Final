# CampusSafe - Campus Emergency Response System

CampusSafe is a web-based emergency alert and incident management system designed for university campuses. It allows students, staff, and faculty to report emergencies in real time and enables security personnel to respond, track, and resolve those incidents from a dedicated dashboard.

---

## Features

### University Users
- Register and log in with a verified university email address
- Send emergency alerts by selecting an emergency type and pinning a location
- Choose a campus location from a dropdown or drop a pin on an interactive Leaflet map
- Use GPS to automatically detect and submit your current location
- Track the status of submitted alerts in real time
- View full alert history from a personal dashboard

### Security Personnel
- Register and log in with a staff ID and verified email address
- View all active, pending, and in-progress alerts from a live dashboard
- Accept, respond to, and resolve alerts with outcome notes
- Toggle on-duty and off-duty status
- View complete request history and filed incident records

---

## Tech Stack

- **Backend:** PHP 8 with MySQLi (procedural, no framework)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, Bootstrap 5.3, Font Awesome 6.5, vanilla JavaScript
- **Maps:** Leaflet.js with OpenStreetMap tiles (no API key required)
- **Email:** PHPMailer 6.12 via Composer, using Gmail SMTP with app passwords
- **Testing:** PHPUnit 10

---

## Project Structure

```
MainFolder/
    campus_safety_system/
        assets/             # Global CSS stylesheet
        partials/           # Shared layout templates (sidebar, top bar)
        tests/              # PHPUnit test files
        vendor/             # Composer dependencies (PHPMailer)
        config.php          # Database connection, constants, and helper functions
        login.php           # Login page for both user types
        register_user.php   # University user registration
        register_security.php  # Security personnel registration
        dashboard_user.php  # University user dashboard
        dashboard_security.php # Security personnel dashboard
        verify_email.php    # Email verification handler
        forgot_password.php # Password reset request
        reset_password.php  # Password reset form
        logout.php          # Session teardown
        mailer.php          # PHPMailer wrapper for verification and reset emails
    database/
        schooldb.sql        # Full database schema with migrations included
        migration_email_verification.sql  # Standalone migration for existing installs
```

---

### Production And Deployment
For our app deployment we used  Microsoft azure to run the app on a VM. Please visit 
http://4.222.217.92/Software-Engineering-Final/MainFolder/campus_safety_system/login.php


## Installation

### Requirements
- PHP 8.0 or higher
- MySQL 5.7 or MariaDB 10.4 or higher
- Composer
- A local server environment such as XAMPP, Laragon, or similar

### Steps

1. Clone or download the repository into your web server root directory.

2. Navigate to the `campus_safety_system` directory and install PHP dependencies:
   ```
   composer install
   ```

3. Create a database named `schooldb` in your MySQL instance, then import the schema:
   ```
   mysql -u root -p schooldb < MainFolder/database/schooldb.sql
   ```
   If you are upgrading an existing installation, run the migration file instead:
   ```
   mysql -u root -p schooldb < MainFolder/database/migration_email_verification.sql
   ```

4. Open `campus_safety_system/config.php` and update the database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'schooldb');
   ```

5. Configure your SMTP credentials for email delivery. The system uses Gmail SMTP by default. To use it, enable two-factor authentication on your Google account, generate an app password at `myaccount.google.com/apppasswords`, and update the following constants in `config.php`:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'your-email@gmail.com');
   define('SMTP_PASS', 'your-app-password');
   ```

6. Visit the application in your browser. The default path assuming XAMPP is:
   ```
   http://localhost/Software-Engineering-Final/MainFolder/campus_safety_system/login.php
   ```

### SMTP Test

A diagnostic script is included to verify your SMTP setup before testing email flows:
```
http://localhost/.../campus_safety_system/test_mail.php
```
Delete this file after confirming delivery.

---

## Running Tests

PHPUnit tests cover authentication helpers, CSRF token generation, password validation, rate limiting, and input sanitization. Tests run without a database connection using a test environment flag.

```
cd MainFolder/campus_safety_system
./vendor/bin/phpunit
```

Test results are written to `build/logs/junit.xml`.

---

## Security Notes

- All forms are protected with CSRF tokens
- Passwords are hashed with bcrypt at cost 12
- Session IDs are regenerated on login
- Login attempts are rate-limited per IP using session-based counters
- All user input is sanitized before use; all output is HTML-escaped
- Email verification is required before any user can sign in
- Password reset tokens expire after one hour
- Security headers including `X-Frame-Options`, `X-Content-Type-Options`, and a strict `Content-Security-Policy` are set on every response

---

## License

This project was built as a software engineering course final project. No license is applied; all rights are retained by the authors.
