# Bodhi Homeopathy Clinic & Pharmacy Management System
## Final Project Demonstration & Viva Preparation Guide

---

## 1. Project Overview & Architecture

**Project Title:** Bodhi Homeopathy Clinic Management & E-Commerce System  
**Application URL:** `http://localhost/bodhi/`  
**Physical Path:** `C:\wamp64\www\bodhi\`  
**Technology Stack:**
* **Backend:** PHP 8.2 (Vanilla PHP, Procedural & Object-Oriented with PDO)
* **Web Server:** Apache 2.4 (WAMP Server 64-bit)
* **Database:** MySQL / MariaDB 10.x (InnoDB Engine, utf8mb4)
* **Frontend:** HTML5, Modern Vanilla CSS3 (Custom Design System, Zero External CSS Frameworks), Vanilla JavaScript (ES6)
* **Payment Gateway Architecture:** Cashfree PG Web Checkout (Sandbox / Live SDK Ready)
* **Email Communication:** PHPMailer 6.9 (SMTP Architecture)

---

## 2. Project Startup & Verification

### Step-by-Step Local Server Startup
1. Launch **WAMP Server** from your desktop or start menu.
2. Confirm the WAMP notification tray icon turns **Green** (indicating Apache and MySQL services are active).
3. Open your web browser (Google Chrome / Edge) and navigate to:
   ```
   http://localhost/bodhi/
   ```
4. **Expected Screen Appearance:**
   * Clean, responsive navigation bar with the official **Bodhi Homeopathy** logo.
   * Public navigation links: `HOME`, `ABOUT US`, `TREATMENTS`, `PRODUCTS`, `CONTACT US`, `ENQUIRY`.
   * Clear guest actions: `LOGIN`, `REGISTER`, and prominent golden `BOOK APPOINTMENT` call-to-action button.
   * Floating social media buttons (`WhatsApp`, `Facebook`, `Instagram`).
   * Hero banner showcase highlighting classical homeopathy treatments, doctor credentials, and remedies.

---

## 3. Demo User Accounts

| Role | Email Address | Password | Primary Portal / Destination |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@bodhi.com` | `Admin@123` | `http://localhost/bodhi/admin/dashboard.php` |
| **Doctor** | `doctor@bodhi.com` | `Doctor@123` | `http://localhost/bodhi/doctor/dashboard.php` |
| **Patient** | `patient@bodhi.com` | `Patient@123` | `http://localhost/bodhi/index.php` (or Patient Menu) |
| **Patient 2** | `john@example.com` | `Patient@123` | `http://localhost/bodhi/index.php` (Used for IDOR tests) |

---

## 4. Patient Demonstration Flow

| Step | Page & URL | User Action | Expected Result | What to Explain to Examiner |
| :--- | :--- | :--- | :--- | :--- |
| **1. Landing** | `index.php`<br>`http://localhost/bodhi/` | Browse homepage | Hero section, treatments, about, doctor info load cleanly. | *"The landing page provides comprehensive clinic information with unified guest navigation."* |
| **2. Login** | `login.php`<br>`http://localhost/bodhi/login.php` | Enter `patient@bodhi.com` / `Patient@123` & click Login. | Authenticates, regenerates session ID, shows patient name & dropdown in header. | *"Unified authentication verifies bcrypt hash and dynamically starts a secure session."* |
| **3. Profile** | `profile.php`<br>`http://localhost/bodhi/profile.php` | Click **My Profile** from user dropdown. | Displays patient full name, email, phone, gender, and quick links. | *"Profile data is retrieved server-side using PDO prepared statements linked to the logged-in session."* |
| **4. Book** | `book.php`<br>`http://localhost/bodhi/book.php` | Select future date, consultation mode, and click **Confirm & Book**. | Validates capacity (< 10/day) & single active booking/day, creates appointment record. | *"The clinic capacity engine enforces business limits: max 10 active bookings clinic-wide, and 1 per patient per day."* |
| **5. Bookings** | `bookings.php`<br>`http://localhost/bodhi/bookings.php` | Review appointment queue & status badge. | Shows status (`Booked`/`Confirmed`/`Completed`). If completed, shows Doctor Notes & Rx. | *"Strict data isolation guarantees patients can only view their own appointments and prescriptions."* |
| **6. Storefront**| `products.php`<br>`http://localhost/bodhi/products.php` | Browse classical remedies. | 8 database-driven remedies with prices, potency badges, and live stock status. | *"All product details and stock badges are rendered dynamically from MySQL without hardcoding."* |
| **7. Details** | `product_details.php?id=1`<br>`http://localhost/bodhi/product_details.php?id=1` | Click remedy card. | Full remedy description, indications, dosage, price, and Add to Cart form. | *"Dynamic product routing handles parameter validation and prevents SQL injection via parameterized queries."* |
| **8. Cart** | `cart.php`<br>`http://localhost/bodhi/cart.php` | Add quantity to cart. | Cart quantity updates, live header cart badge updates, subtotals calculate server-side. | *"The cart enforces server-side inventory checks so quantity cannot exceed available stock."* |
| **9. Checkout**| `checkout.php`<br>`http://localhost/bodhi/checkout.php` | Click **Proceed to Checkout**. | Address form loads, item prices recalculated from database, order summary calculated. | *"All prices are retrieved strictly from the database during checkout to defend against client-side tampering."* |
| **10. Orders** | `my_orders.php`<br>`http://localhost/bodhi/my_orders.php` | View order history. | Lists all past orders with status badges (`Pending`, `Paid`, `Processing`, etc.). | *"Orders are linked to the patient ID with historical purchase price recorded in order_items."* |
| **11. Tracking**| `order_details.php?order_id=5`<br>`http://localhost/bodhi/order_details.php?order_id=5` | Click **Track Order**. | 5-step visual tracking timeline (`Pending` &rarr; `Delivered`), delivery details, items. | *"Provides real-time fulfillment visibility with complete IDOR access protection."* |

---

## 5. Admin Demonstration Flow

| Step | Page & URL | User Action | Expected Result | What to Explain to Examiner |
| :--- | :--- | :--- | :--- | :--- |
| **1. Admin Login** | `login.php`<br>`http://localhost/bodhi/login.php` | Login as `admin@bodhi.com` / `Admin@123`. | Directly redirected to `admin/dashboard.php`. | *"Role-based access control automatically routes administrative accounts to the administration portal."* |
| **2. Dashboard** | `admin/dashboard.php`<br>`http://localhost/bodhi/admin/dashboard.php` | Review metrics cards. | Real-time counts for Total Patients, Appointments, Products, and Orders. | *"Dashboard summary counts are generated via real SQL aggregation queries on the database."* |
| **3. Patients** | `admin/patients.php`<br>`http://localhost/bodhi/admin/patients.php` | View registered patient list. | Displays registered patients, phone, registration dates, and view action. | *"Admin can monitor user growth and view historical activity per patient."* |
| **4. Appointments**| `admin/appointments.php`<br>`http://localhost/bodhi/admin/appointments.php`| Filter & search queue. | Lists all appointments across clinic with search, date filters, and status updater. | *"Allows clinic management to confirm or reschedule bookings across the entire patient base."* |
| **5. Products** | `admin/products.php`<br>`http://localhost/bodhi/admin/products.php` | View product inventory. | Stock alert badges (`Low Stock <= 5`, `Out of Stock = 0`), search, category filters. | *"Full inventory control allowing remedy pricing, stock updates, image upload, and activation status."* |
| **6. Orders** | `admin/orders.php`<br>`http://localhost/bodhi/admin/orders.php` | Inspect customer orders. | Metric cards (Total, Paid, Processing, Delivered), order search, payment & status filters. | *"Central order fulfillment dashboard tracking both online and cash-on-delivery orders."* |
| **7. Fulfillment** | `admin/order_details.php?order_id=5`<br>`http://localhost/bodhi/admin/order_details.php?order_id=5` | Advance order status (`Pending` &rarr; `Confirmed` &rarr; `Processing` &rarr; `Shipped` &rarr; `Delivered`). | Validates state transition, updates database, status reflects immediately on patient tracking timeline. | *"Fulfillment state machine enforces forward-only transitions without double-deducting inventory."* |

---

## 6. Doctor Demonstration Flow

| Step | Page & URL | User Action | Expected Result | What to Explain to Examiner |
| :--- | :--- | :--- | :--- | :--- |
| **1. Doctor Login**| `login.php`<br>`http://localhost/bodhi/login.php` | Login as `doctor@bodhi.com` / `Doctor@123`. | Directly redirected to `doctor/dashboard.php`. | *"Doctor portal provides a dedicated, uncluttered clinical workspace for daily patient appointments."* |
| **2. Clinical Queue**| `doctor/appointments.php`<br>`http://localhost/bodhi/doctor/appointments.php` | View assigned appointments. | Filter by today's queue, upcoming, or completed consultations. | *"Doctors see chronological patient bookings with reported symptoms and consultation modes."* |
| **3. Consult** | `doctor/appointment_detail.php?id=X`<br>`http://localhost/bodhi/doctor/appointment_detail.php?id=X` | Open patient consultation. | Patient details, medical history, and clinical notes editor load. | *"Enables doctor to record clinical observations, diagnosis, and remedy prescriptions."* |
| **4. Complete** | Click **Complete Consultation** | Submit notes & prescription. | `appointment.status` becomes `Completed`, new record inserted into `consultations` table. | *"Completing a consultation locks the record, creates an official clinical history entry, and exposes the prescription to the patient."* |

---

## 7. Database Architecture & Relationships

```
                        +----------------------+
                        |        users         |
                        +----------------------+
                        | id (PK)              |
                        | full_name            |
                        | email (UNIQUE)       |
                        | password (bcrypt)    |
                        | role (Admin/Doc/Pat) |
                        +----------------------+
                                   |
         +-------------------------+-------------------------+
         | 1:N                     | 1:N                     | 1:N
         v                         v                         v
+------------------+      +------------------+      +------------------+
|   appointment    |      |    cart_items    |      |      orders      |
+------------------+      +------------------+      +------------------+
| id (PK)          |      | id (PK)          |      | id (PK)          |
| user_id (FK)     |      | user_id (FK)     |      | user_id (FK)     |
| appointment_date |      | product_id (FK)  |      | order_number     |
| appointment_time |      | quantity         |      | total_amount     |
| status           |      +------------------+      | payment_status   |
+------------------+               |                | order_status     |
         |                         |                +------------------+
         | 1:1                     |                         |
         v                         |                         | 1:N
+------------------+               |                         v
|  consultations   |               |                +------------------+
+------------------+               |                |   order_items    |
| id (PK)          |               |                +------------------+
| appointment_id   |               |                | id (PK)          |
| patient_id (FK)  |               |                | order_id (FK)    |
| doctor_id (FK)   |               |                | product_id (FK)  |
| clinical_notes   |               |                | quantity         |
| prescription     |               |                | price (historic) |
+------------------+               |                +------------------+
                                   |                         |
                                   +------------+------------+
                                                | N:1
                                                v
                                       +------------------+
                                       |     products     |
                                       +------------------+
                                       | id (PK)          |
                                       | name             |
                                       | price            |
                                       | stock_quantity   |
                                       | status           |
                                       +------------------+
```

### Table Breakdown
1. **`users`**: Central authentication table storing hashed passwords and role authorizations (`Admin`, `Doctor`, `Patient`).
2. **`appointment`**: Patient appointment bookings with date, time slot, mode, and workflow status.
3. **`consultations`**: Clinical diagnosis, doctor notes, and homeopathic prescriptions linked to completed appointments.
4. **`products`**: Classical homeopathy inventory with pricing, descriptions, stock quantities, and availability status.
5. **`cart_items`**: User-isolated active shopping cart items.
6. **`orders`**: Customer order master records tracking totals, shipping address, payment status, and fulfillment state.
7. **`order_items`**: Order line items preserving historical purchase prices (`order_items.price`) independent of subsequent catalog price changes.
8. **`password_resets`**: Stores 64-character cryptographically secure reset tokens with expiry timestamps.
9. **`contact`** & **`enquiry`**: Stores general public enquiries and contact messages.

---

## 8. Implemented Security Controls

1. **SQL Injection Defense:** 100% of database queries use PDO prepared statements with parameterized placeholders (`?`).
2. **Password Security:** Passwords hashed with standard PHP `password_hash($pass, PASSWORD_BCRYPT)` and verified using `password_verify()`. No plaintext passwords are ever stored or displayed.
3. **Cross-Site Scripting (XSS) Defense:** All user input and dynamic database output is sanitized using `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')`.
4. **Cross-Site Request Forgery (CSRF) Defense:** Unique 32-byte cryptographic session tokens (`bin2hex(random_bytes(32))`) validated on state-changing POST actions.
5. **Insecure Direct Object Reference (IDOR) Defense:** All patient endpoints verify that the requested resource ID matches `$_SESSION['user_id']`. Patient A cannot view Patient B's bookings, prescriptions, carts, or order tracking details.
6. **Financial Integrity:** Product prices and stock limits are calculated exclusively server-side from the database during checkout to block DOM/DevTools price tampering.
7. **Session Hijacking Prevention:** Session identifiers are regenerated on successful login via `session_regenerate_id(true)`.
8. **Role Authorization Barriers:** Every protected portal directory (`/admin/` and `/doctor/`) verifies user role at the very first line of execution and immediately redirects unauthorized users.

---

## 9. Payment Architecture (Cashfree)

* **Implemented Architecture:**
  * Cashfree PG Web Checkout flow implemented in [includes/cashfree.php](file:///c:/wamp64/www/bodhi/includes/cashfree.php) and [payment.php](file:///c:/wamp64/www/bodhi/payment.php).
  * API version `2023-08-01` order creation, session ID generation, and secure redirect to Cashfree checkout.
  * Payment verification webhook handler ([cashfree_webhook.php](file:///c:/wamp64/www/bodhi/cashfree_webhook.php)) verifies HMAC-SHA256 signature against `CASHFREE_CLIENT_SECRET`.
  * Return URL handler ([payment_return.php](file:///c:/wamp64/www/bodhi/payment_return.php)) validates payment status before updating database `payment_status` to `Paid`.
* **Current Status:**
  * Architecture is 100% complete and verified in simulated test mode.
  * For live/sandbox transactions with real bank gateways, valid API keys (`CASHFREE_CLIENT_ID` and `CASHFREE_CLIENT_SECRET`) must be provided in `includes/config.php`.

---

## 10. Forgot Password & Email Delivery (PHPMailer)

* **Implemented Architecture:**
  * Generates cryptographically secure 64-character hex tokens (`bin2hex(random_bytes(32))`).
  * Stores hashed token in `password_resets` with 1-hour expiration timestamp.
  * Dispatches HTML password reset email using PHPMailer 6.9 over SMTP.
  * Token is verified on `reset_password.php`, password updated with bcrypt, and token immediately deleted upon successful reset to prevent replay attacks.
* **Current Status:**
  * Flow is verified 100% via simulated delivery.
  * Live Google Gmail delivery requires entering valid SMTP credentials (`SMTP_HOST = 'smtp.gmail.com'`, `SMTP_USER`, and Google App Password) in `includes/config.php`.

---

## 11. Role Permission Matrix

| Feature / Page | Guest | Patient | Doctor | Admin |
| :--- | :---: | :---: | :---: | :---: |
| Homepage & Static Pages (`index.php`, `about.php`, `treatment.php`) | Allowed | Allowed | Allowed | Allowed |
| Browse Product Catalog (`products.php`, `product_details.php`) | Allowed | Allowed | Allowed | Allowed |
| User Registration & Unified Login (`register.php`, `login.php`) | Allowed | Redirected | Redirected | Redirected |
| Patient Profile & Bookings (`profile.php`, `bookings.php`) | Blocked (302) | Allowed | Blocked (302) | Blocked (302) |
| Book Appointment (`book.php`, `save_appointment.php`) | Redirected | Allowed | Blocked (302) | Blocked (302) |
| Shopping Cart & Checkout (`cart.php`, `checkout.php`) | Blocked (302) | Allowed | Blocked (302) | Blocked (302) |
| Order History & Tracking (`my_orders.php`, `order_details.php`) | Blocked (302) | Allowed | Blocked (302) | Blocked (302) |
| Doctor Portal (`doctor/dashboard.php`, `doctor/appointments.php`) | Blocked (302) | Blocked (302) | Allowed | Blocked (302) |
| Doctor Consultation & Rx (`doctor/appointment_detail.php`) | Blocked (302) | Blocked (302) | Allowed | Blocked (302) |
| Admin Portal (`admin/dashboard.php`, `admin/patients.php`) | Blocked (302) | Blocked (302) | Blocked (302) | Allowed |
| Admin Product Management (`admin/products.php`, `product_add.php`) | Blocked (302) | Blocked (302) | Blocked (302) | Allowed |
| Admin Order Fulfillment (`admin/orders.php`, `order_details.php`) | Blocked (302) | Blocked (302) | Blocked (302) | Allowed |

---

## 12. phpMyAdmin Database Demonstration Guide

During your viva examination, open `http://localhost/phpmyadmin/` (database: `bodhi`):

1. **`users` Table:**
   * Show `admin@bodhi.com` (Admin), `doctor@bodhi.com` (Doctor), `patient@bodhi.com` (Patient).
   * Point out the `password` column: *"Examiner can see that all passwords are encrypted using 60-character bcrypt hashes (`$2y$10$...`), and plaintext passwords never exist in the database."*
2. **`products` Table:**
   * Show the 8 remedies (e.g. *Arnica Montana 30C*, *Nux Vomica 200C*, *Rhus Tox 30C*).
   * Point out `price`, `stock_quantity`, and `status`.
3. **`orders` & `order_items` Tables (Demonstrate 1:N Relationship):**
   * Open `orders` table and pick Order ID `#5` (`total_amount = 160.00`, `order_status = Pending`).
   * Open `order_items` table and show `order_id = 5`, `product_id = 1`, `price = 160.00`.
   * *"Explain to the examiner: The purchase price is saved in `order_items.price`. Even if an admin edits the product catalog price tomorrow, historical financial accounting remains 100% accurate."*
4. **`appointment` & `consultations` Tables (Demonstrate Clinical Workflow):**
   * Show appointment ID `#1` with `status = Completed`.
   * Show linked row in `consultations` with matching `appointment_id`, `doctor_id = 2`, `patient_id = 3`, and recorded prescription text.

---

## 13. Presentation Demo Scripts

### 5-Minute Quick Presentation Script
* **Minute 1 — Introduction & Landing Page (`index.php`):**  
  *"Good morning. This is Bodhi Homeopathy, a full-stack clinic and pharmacy management system built with PHP, Apache, and MySQL. It features unified authentication and role-based workflows for Patients, Doctors, and Administrators."*
* **Minute 2 — Patient Booking & Storefront (`login.php` &rarr; `book.php` &rarr; `products.php`):**  
  *"I'll log in as a Patient. The patient can book appointments with strict daily capacity limits (max 10 bookings per day clinic-wide and 1 per patient per day). In the pharmacy storefront, patients can browse 8 classical remedies, add to cart, and checkout with server-side price protection."*
* **Minute 3 — Doctor Clinical Consultation (`doctor/dashboard.php`):**  
  *"Next, logging in as the Doctor, we see the daily clinical queue. The doctor opens the appointment, types the clinical notes and homeopathic prescription, and clicks Complete Consultation. This automatically updates the database and makes the prescription visible to the patient."*
* **Minute 4 — Admin Order Fulfillment (`admin/dashboard.php` &rarr; `admin/orders.php`):**  
  *"Finally, logging in as Administrator, we access the real-time analytics dashboard, inventory management, and order fulfillment manager. The admin advances order status through confirmed, processing, shipped, and delivered states."*
* **Minute 5 — Security & Database Architecture:**  
  *"The system uses PDO prepared statements, bcrypt hashing, CSRF tokens, XSS escaping, and IDOR access barriers across all modules. Thank you."*

---

### 10-Minute Standard Presentation Script
* **Minutes 1-2:** Project motivation, architecture, and landing page showcase (`index.php`, `about.php`, `treatment.php`).
* **Minutes 3-4:** Patient registration, login, auto-populated profile, appointment booking rules, and viewing past bookings (`book.php`, `bookings.php`).
* **Minutes 5-6:** Homeopathy e-commerce catalog, dynamic product details, cart quantity rules, and checkout flow with server-side validation (`products.php`, `cart.php`, `checkout.php`).
* **Minutes 7-8:** Doctor workstation, consultation draft/completion, and prescription reflection (`doctor/dashboard.php`, `doctor/appointment_detail.php`).
* **Minutes 9-10:** Admin inventory manager, fulfillment state machine, database table relationships in phpMyAdmin, and security review.

---

### 15-Minute Detailed Presentation Script
* **Minutes 1-3:** Comprehensive system architecture, WAMP stack, and modular folder structure.
* **Minutes 4-6:** Detailed patient lifecycle (Registration validation, bcrypt hashing, booking rules, capacity management).
* **Minutes 7-9:** Full pharmacy workflow (Cart isolation, inventory deduction, tamper-proof checkout, live 5-step order tracking timeline).
* **Minutes 10-12:** Doctor consultation workflow and administrative inventory/order fulfillment controls.
* **Minutes 13-15:** In-depth security analysis (SQL injection, XSS, CSRF, IDOR defense-in-depth) and live phpMyAdmin schema walk-through.

---

*Guide generated for Bodhi Homeopathy Project Defense & Examination.*
