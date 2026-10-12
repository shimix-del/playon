# 🎈 Play-On Mombasa – Backend, Database & Admin Setup Guide

This guide walks you through setting up the complete booking backend, MySQL database, and management admin dashboard on standard **cPanel hosting (PHP 8.0+ / MySQL 5.7+ or 8.0+)** for **Play-On Mombasa** at Mombasa Mall.

---

## 📁 Directory Structure

```text
backend/
├── admin/                         # Admin Dashboard Management
│   ├── css/
│   │   └── admin.css              # Branded modern dashboard styling (Fredoka & Nunito)
│   ├── js/
│   │   └── admin.js               # Modal handlers, toasts, alerts & quick actions
│   ├── auth.php                   # Session security guard & CSRF protection
│   ├── booking_action.php         # POST handler for status changes, edits & deletions
│   ├── bookings.php               # Full paginated table with search & multi-filter
│   ├── export_csv.php             # Streaming Excel-compatible UTF-8 CSV exporter
│   ├── index.php                  # Dashboard overview & real-time metric cards
│   ├── login.php                  # Secure login with bcrypt & brute-force lockout
│   └── logout.php                 # Session termination & cleanup
├── api/
│   ├── .htaccess                  # API headers & access control
│   └── submit_booking.php         # Public booking API endpoint (CORS, validation, rate limiting)
├── config/
│   ├── .env                       # Credentials (protected from HTTP access)
│   ├── .env.example               # Template environment configuration
│   ├── .htaccess                  # Apache protection: "Require all denied"
│   └── db.php                     # Singleton PDO connection & .env loader
├── lib/
│   └── PHPMailer/                 # Standalone PHPMailer 6.9.1 (No Composer required!)
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
├── mailer.php                     # HTML email templates (Admin + Customer) & email_log tracking
├── mombasaM_booking.sql           # Single phpMyAdmin importable SQL database schema
└── SETUP_GUIDE.md                 # This setup documentation
```

---

## 🚀 Step 1: Create the MySQL Database in cPanel

1. Log in to your **cPanel Account** (e.g. `https://mombasamall.co.ke:2083`).
2. Navigate to **Databases** &rarr; **MySQL® Databases** (or **MySQL Database Wizard**).
3. **Create New Database**:
   - Database Name: `mombasaM_booking`
   *(Note: cPanel often prefixes your cPanel username, e.g. `yourcpaneluser_mombasaM_booking`. Take note of the exact full name).*
4. **Create New User**:
   - Username: `mombasaM_admin` (or `yourcpaneluser_mombasaM_admin`)
   - Password: `playon@4000`
5. **Add User to Database**:
   - Select User: `mombasaM_admin`
   - Select Database: `mombasaM_booking`
   - Click **Add**.
   - On the Manage User Privileges screen, check **ALL PRIVILEGES** and click **Make Changes**.

---

## 🗄️ Step 2: Import the Database Schema (`mombasaM_booking.sql`)

1. In cPanel, navigate to **Databases** &rarr; **phpMyAdmin**.
2. In the left sidebar, click on your newly created database (e.g. `mombasaM_booking`).
3. Click the **Import** tab at the top.
4. Under **File to import**, click **Choose File** and select `mombasaM_booking.sql` from your computer (located in the `backend/` folder or project root).
5. Ensure Format is set to **SQL**, then scroll to the bottom and click **Import** (or **Go**).
6. You will see a success message: `Import has been successfully finished`.

### Tables Created:
* **`admins`**: Stores management accounts, bcrypt passwords, and brute-force lockout counters. Includes the default admin account.
* **`bookings`**: Stores customer bookings, unique references (`PO-YYMMDD-XXXX`), guests, session times, estimated totals, and status (`pending`, `confirmed`, `cancelled`).
* **`email_log`**: Logs every outbound email attempt (admin notifications & customer passes) with timestamps and status (`sent` or `failed`).
* **`rate_limits`**: Tracks request IP timestamps to protect the API from spam and DDoS.

---

## 🔐 Step 3: Configure Environment Credentials (`config/.env`)

1. Open `backend/config/.env` in cPanel File Manager or your text editor.
2. Update the values to match your cPanel setup:

```ini
# ==============================================================================
# Play-On Mombasa Booking System Configuration
# ==============================================================================

# Database Connection
DB_HOST=localhost
DB_NAME=mombasaM_booking
DB_USER=mombasaM_admin
DB_PASS=playon@4000
DB_CHARSET=utf8mb4

# CORS & Allowed Frontend Domains (comma-separated)
ALLOWED_ORIGINS=https://shimix-del.github.io,http://localhost:8000,http://127.0.0.1:8000
FRONTEND_URL=https://shimix-del.github.io/playon

# SMTP Email Settings (cPanel Webmail or Third-Party SMTP)
SMTP_ENABLED=true
SMTP_HOST=mail.mombasamall.co.ke
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_AUTH=true
SMTP_USER=info@mombasamall.co.ke
SMTP_PASS=YourActualEmailPasswordHere
SMTP_FROM_EMAIL=info@mombasamall.co.ke
SMTP_FROM_NAME=Play-On Mombasa

# Admin Notification Recipients (comma-separated)
ADMIN_EMAILS=playonkenya@gmail.com,mombasamall01@gmail.com

# Admin Security & Sessions
SESSION_NAME=PLAYON_ADMIN_SESS
SESSION_LIFETIME=7200
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_MINUTES=15
```

> [!TIP]
> If your cPanel host prefixed your database name and user, make sure `DB_NAME` and `DB_USER` in `.env` reflect the exact prefixed names (e.g. `DB_NAME=mombasa_mombasaM_booking`).

> [!IMPORTANT]
> The `backend/config/.htaccess` file automatically blocks all direct web access to `.env` using Apache `Require all denied`. Alternatively, you can also place `.env` one directory level above `public_html` (`/home/username/.env`), and `db.php` will automatically detect and load it.

---

## 📤 Step 4: Upload Backend Files to Server

1. Open **cPanel File Manager** and go to `public_html`.
2. Upload the `backend/` directory directly into `public_html/`:
   ```text
   public_html/
   └── backend/
       ├── admin/
       ├── api/
       ├── config/
       ├── lib/
       └── mailer.php
   ```
3. Your endpoints will be accessible at:
   - **Admin Dashboard:** `https://mombasamall.co.ke/backend/admin/`
   - **Booking API:** `https://mombasamall.co.ke/backend/api/submit_booking.php`

---

## 💻 Step 5: Connecting the Frontend to the Backend

The frontend booking form in `js/main.js` is already coded to submit to `BOOKING_API_URL`.

When hosted on GitHub Pages (`https://shimix-del.github.io/playon/`), it sends requests across origins to `https://mombasamall.co.ke/backend/api/submit_booking.php`.

The API endpoint automatically permits CORS requests from:
- `https://shimix-del.github.io`
- Local testing origins (`http://localhost:8000`, `http://127.0.0.1:8000`)

---

## 🔑 Step 6: Admin Dashboard Access & Default Credentials

Navigate in your browser to:
**`https://mombasamall.co.ke/backend/admin/`**

| Field | Default Value |
|---|---|
| **Username** | `admin` |
| **Email** | `playonkenya@gmail.com` |
| **Password** | `PlayOn@Admin2026!` |

### Dashboard Features:
1. **Overview Dashboard (`index.php`)**:
   - Total Bookings count
   - Bookings Today count
   - Pending, Confirmed, and Cancelled count badges
   - 10 Most Recent Bookings table with one-click status transitions
2. **Bookings Manager (`bookings.php`)**:
   - Search across Reference, Customer Name, Phone, and Email
   - Filter by Status (`pending`, `confirmed`, `cancelled`), Package Type, and Date Range
   - Sort by Date (newest/oldest) or Status
   - Pagination (15 bookings per page)
   - **Action Modals**:
     - 👁️ **View Details**: Full guest counts, notes, timestamps, IP address
     - ✏️ **Edit Details**: Update date, session time, guests, status, admin notes
     - 🔄 **Quick Status**: One-click Confirm / Cancel with CSRF protection
     - 🗑️ **Delete**: With safety confirmation dialog
3. **CSV Export (`export_csv.php`)**:
   - Exports the currently filtered bookings to a Microsoft Excel-compatible UTF-8 CSV file.
4. **Security Hardening**:
   - Bcrypt password verification
   - Brute-force account lockout (locks out after 5 failed attempts for 15 minutes)
   - CSRF tokens on all POST requests
   - Session idle timeout (2 hours)
   - XSS sanitization on all output

---

## 🧪 Step 7: End-to-End Verification Checklist

Use this checklist to confirm everything is running smoothly:

- [ ] **Database Imported**: Check phpMyAdmin to verify `admins`, `bookings`, `email_log`, and `rate_limits` tables exist.
- [ ] **Admin Login Test**:
  - Visit `/backend/admin/login.php`
  - Enter `admin` / `PlayOn@Admin2026!`
  - Verify that you are redirected to the Overview dashboard with metric cards and the sample booking (`PO-SAMPLE-001`).
- [ ] **Frontend Submission Test**:
  - Open the website (`index.html`)
  - Scroll down to the booking form
  - Fill out: Parent Name, Phone, Email, Date, Session Slot
  - Click **Submit Booking & Celebrate 🎉**
  - Verify button shows loading spinner: *"Reserving Your Spot & Sending Emails..."*
  - Verify confetti blast triggers and confirmation modal appears with a unique booking reference (e.g. `PO-261012-A1B2`).
- [ ] **Database Record Check**:
  - Open phpMyAdmin &rarr; `bookings` table. Confirm the new record is present with status `pending`.
- [ ] **Admin Notification Check**:
  - Check the `email_log` table in phpMyAdmin to see the dispatch record.
  - Verify that `playonkenya@gmail.com` and `mombasamall01@gmail.com` received the notification email.
- [ ] **Customer Email Check**:
  - Check the customer's inbox for the branded welcome pass with Mombasa Mall location details and arrival tips.
- [ ] **Admin Actions Check**:
  - In `/backend/admin/bookings.php`, click **Confirm** on the pending booking.
  - Verify flash notification: *"Booking #X status updated to CONFIRMED successfully!"*.
  - Click **Export CSV** and verify the downloaded `.csv` opens cleanly in Excel.
- [ ] **Spam & Security Verification**:
  - **Honeypot**: Automated bots that fill the hidden `hp_website` input are silently caught and given a mock response without polluting the database.
  - **Rate Limiting**: Submitting more than 5 requests from the same IP within 10 minutes returns HTTP 429 Too Many Requests.
  - **Brute-Force Protection**: 5 consecutive incorrect passwords on `/backend/admin/login.php` locks the account for 15 minutes.

---

## 📞 Support & Contacts

- **Play-On Inquiries / WhatsApp:** +254 780 611 074
- **Email:** `playonkenya@gmail.com` / `info@mombasamall.co.ke`
- **Location:** Mombasa Mall, 2nd Floor, Nyali, Mombasa, Kenya
