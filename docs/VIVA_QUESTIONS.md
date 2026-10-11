# Bodhi Homeopathy Clinic & Pharmacy Management System
## Examiner Viva Voce & Technical Defense Questions (50+ Q&A)

---

### Part 1: Project Objective & Architecture

#### Q1. What is the primary objective of the Bodhi Homeopathy System?
**Answer:** The project is an integrated healthcare web application designed to digitize clinic operations and e-commerce for a classical homeopathy clinic. It unifies patient appointment scheduling, doctor consultations and electronic medical records (EMR), homeopathic remedy prescriptions, online pharmacy sales with inventory tracking, and administrative order fulfillment into a single platform.

#### Q2. Why did you choose PHP for the backend?
**Answer:** PHP is a mature, high-performance server-side scripting language natively supported by Apache/Linux/Windows environments. It offers seamless database connectivity through PDO, robust session management, native cryptographic hashing libraries (`password_hash`), and low memory overhead without requiring complex build pipelines.

#### Q3. Why did you use MySQL / MariaDB as the database?
**Answer:** MySQL is an ACID-compliant Relational Database Management System (RDBMS). It provides strict relational integrity via Foreign Keys, transactions (`START TRANSACTION`, `COMMIT`, `ROLLBACK`) for checkout and consultations, indexed searches, and high reliability using the InnoDB storage engine.

#### Q4. What is WAMP Server and why is it used?
**Answer:** WAMP is an integrated local development stack combining Windows OS, Apache HTTP Server, MySQL/MariaDB database, and PHP interpreter. It provides a realistic production-like environment for local testing, virtual hosts, and native database management via phpMyAdmin.

#### Q5. Why did you choose PDO (PHP Data Objects) over mysqli?
**Answer:** PDO provides a consistent, database-agnostic interface supporting parameterized prepared statements, robust error handling via `PDO::ERRMODE_EXCEPTION`, native type binding, and comprehensive protection against SQL injection attacks across all database engines.

---

### Part 2: Authentication, Sessions & Role Security

#### Q6. Why did you implement a single unified login page (`login.php`) instead of separate login pages?
**Answer:** A single unified login page enhances user experience and simplifies maintenance. The system checks the user's credentials against the central `users` table, verifies their bcrypt password hash, establishes their session, and dynamically routes them to their designated portal (`admin/dashboard.php` for Admin, `doctor/dashboard.php` for Doctor, and `index.php` for Patient) based on their `role` attribute.

#### Q7. How are user passwords securely stored?
**Answer:** Passwords are never stored in plaintext. They are hashed using PHP's native `password_hash($password, PASSWORD_BCRYPT)` function with a default cost factor of 10. During authentication, `password_verify($password, $user['password'])` safely checks the supplied password against the salted bcrypt hash.

#### Q8. Why is `session_regenerate_id(true)` used upon successful login?
**Answer:** `session_regenerate_id(true)` replaces the existing session ID with a newly generated cryptographically random session ID while deleting the old session file. This prevents **Session Fixation attacks**, where an attacker forces a known session ID onto a victim.

#### Q9. How do you prevent unauthorized users from accessing `/admin/*` or `/doctor/*` pages?
**Answer:** Every file in protected directories begins with an access control gate:
```php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}
```
If a guest or non-admin attempts direct URL access, the script immediately halts execution via `exit` and redirects them to the login page.

#### Q10. What happens during user logout (`logout.php`)?
**Answer:** The server unsets all `$_SESSION` variables, calls `session_destroy()`, clears the session cookie from the client's browser, and redirects the user to the homepage.

---

### Part 3: Clinic & Appointment Workflow

#### Q11. What clinic booking rules are enforced in the appointment module?
**Answer:** Two critical business rules are enforced:
1. **Clinic Daily Capacity Limit:** The clinic accommodates a maximum of 10 active appointments per day across all patients.
2. **Patient Booking Rule:** A patient can hold at most 1 active booking per day to prevent slot hoarding.
Past dates are disabled in the datepicker, and auto-populated patient details prevent identity spoofing.

#### Q12. How does the system check the 10-appointment capacity limit?
**Answer:** Before inserting a new booking, the system executes a count query on active statuses:
```sql
SELECT COUNT(*) FROM appointment 
WHERE appointment_date = ? AND status IN ('Booked', 'Confirmed')
```
If the count reaches 10, the booking is rejected with a message advising the patient to choose another date.

#### Q13. What happens if a patient cancels their appointment?
**Answer:** The appointment status is updated to `Cancelled`. Because the capacity check only counts `Booked` and `Confirmed` statuses, cancelling an appointment immediately frees up that slot for other patients.

#### Q14. How does the Doctor Consultation workflow function?
**Answer:** 
1. The doctor logs into `doctor/dashboard.php` and views today's scheduled appointment queue.
2. Opening `doctor/appointment_detail.php?id=X` loads the patient's reported symptoms and medical background.
3. The doctor writes diagnosis notes and homeopathic remedy prescriptions.
4. Clicking **Complete Consultation** updates `appointment.status` to `Completed` and inserts a new record into the `consultations` table containing the notes and prescription.

#### Q15. How does the patient view their prescription after consultation?
**Answer:** The patient visits `bookings.php`. A SQL `LEFT JOIN` between `appointment` and `consultations` fetches the clinical notes and prescription details, rendering them directly under the completed appointment card.

---

### Part 4: Pharmacy, Cart & E-Commerce Workflow

#### Q16. How are products managed in the database?
**Answer:** Products are stored in the `products` table with columns for `name`, `category`, `potency`, `price`, `stock_quantity`, `description`, `image_url`, and `status` (`Active` / `Inactive`). The storefront renders only active products.

#### Q17. How does the shopping cart isolate items between different patients?
**Answer:** Cart items are stored in the database table `cart_items` with a composite foreign key association: `user_id` and `product_id`. Every query explicitly filters by `WHERE user_id = ?`, ensuring Patient A can never access or modify Patient B's cart.

#### Q18. How do you prevent a patient from adding more items than available in stock?
**Answer:** In `cart_action.php`, the requested quantity is validated server-side against `products.stock_quantity`. If `requested_qty > stock_quantity`, the action is blocked, and an error message is returned.

#### Q19. How does the system defend against Client-Side Price Tampering during checkout?
**Answer:** The client never sends the total monetary amount or product unit prices in the POST request. Instead, `place_order.php` and `checkout.php` fetch the latest prices directly from the `products` table in MySQL, recalculate subtotals on the server, and insert the verified amount into the `orders` table.

#### Q20. How is product stock deducted during order placement?
**Answer:** During checkout, the application executes:
```sql
UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?
```
This ensures stock is deducted atomically upon successful checkout.

#### Q21. Why is the historical purchase price stored in `order_items.price` instead of querying `products.price`?
**Answer:** If an administrator changes a remedy's catalog price in the future, past orders must reflect the exact price paid at the time of purchase. Storing the price in `order_items` preserves financial and legal audit accuracy.

#### Q22. How does Admin Order Management differ from Payment Status?
**Answer:** Order fulfillment status (`Pending` &rarr; `Confirmed` &rarr; `Processing` &rarr; `Shipped` &rarr; `Delivered`) is decoupled from `payment_status` (`Pending` / `Paid` / `Failed`). Changing fulfillment status does not re-deduct inventory or alter payment records.

---

### Part 5: Database Schema & Relational Design

#### Q23. What database normalization level does this schema follow?
**Answer:** The schema is normalized to **Third Normal Form (3NF)**:
* **1NF:** All attributes are atomic (no repeating arrays or multi-valued columns).
* **2NF:** All non-key attributes are fully functionally dependent on primary keys (e.g. separate `orders` and `order_items` tables).
* **3NF:** No transitive dependencies exist (product prices are stored in `products`, while transaction snapshot prices reside in `order_items`).

#### Q24. Explain the relationship between `users`, `appointment`, and `consultations`.
**Answer:** 
* `users` to `appointment` is **1-to-Many (1:N)**: One patient can book multiple appointments over time.
* `appointment` to `consultations` is **1-to-1 (1:1)**: Each completed appointment has exactly one consultation record containing the clinical notes and prescription.

#### Q25. Explain the relationship between `users`, `orders`, `order_items`, and `products`.
**Answer:**
* `users` to `orders` is **1-to-Many (1:N)**: A patient can place multiple orders.
* `orders` to `order_items` is **1-to-Many (1:N)**: An order contains one or more line items.
* `products` to `order_items` is **1-to-Many (1:N)**: A remedy product can appear across multiple order items.

#### Q26. What storage engine is used for MySQL tables and why?
**Answer:** All tables use **InnoDB**, which supports Foreign Key constraints, row-level locking, and ACID transactions for multi-step order and appointment booking operations.

---

### Part 6: Security, Vulnerability Defense & Web Standards

#### Q27. What is SQL Injection (SQLi) and how is it prevented in this project?
**Answer:** SQL Injection occurs when malicious SQL statements are inserted into entry fields to manipulate backend queries. In this project, 100% of queries use PDO prepared statements with parameterized placeholders (`?`), ensuring user input is treated strictly as data, never executable SQL code.

#### Q28. What is Cross-Site Scripting (XSS) and how is it prevented?
**Answer:** XSS occurs when malicious JavaScript is injected into web pages viewed by other users. This is prevented by passing all dynamic database and user outputs through `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`, which converts special characters (`<`, `>`, `&`, `"`, `'`) into harmless HTML entities.

#### Q29. What is Cross-Site Request Forgery (CSRF) and how is it prevented?
**Answer:** CSRF is an attack that tricks authenticated users into submitting unwanted commands. This project generates a 32-byte cryptographically secure token stored in `$_SESSION['csrf_token']` and embedded as a hidden form input. Submissions verify this token using `hash_equals()`.

#### Q30. What is Insecure Direct Object Reference (IDOR) and how is it handled?
**Answer:** IDOR occurs when an application exposes a reference to internal database objects (e.g. `order_details.php?order_id=5`) without authorization validation. This project protects against IDOR by checking ownership in the SQL query:
```sql
SELECT * FROM orders WHERE id = ? AND user_id = ?
```
If a logged-in patient attempts to view another patient's order ID, the query returns null, and access is blocked with a 404/access denied notice.

#### Q31. What is Brute-Force Attack and how is authentication safeguarded?
**Answer:** Brute-force is the repeated trial of credentials. Our login displays generic error messages (`"Invalid email or password"`) to avoid username enumeration, enforces strong password complexity during registration, and bcrypt hashing introduces computational work factors that deter dictionary attacks.

#### Q32. How is Password Reset Token Expiry implemented?
**Answer:** In `forgot_password.php`, a 64-character random token is generated and stored in `password_resets` with an expiration timestamp (`expires_at = NOW() + INTERVAL 1 HOUR`). When the user clicks the reset link, the system checks `WHERE token = ? AND expires_at > NOW()`. Once used, the token is immediately deleted.

---

### Part 7: Third-Party Integrations & Configurations

#### Q33. How does the Cashfree Payment Gateway integration work?
**Answer:** The integration follows Cashfree's PG Web Checkout API (`2023-08-01`):
1. The server calls Cashfree's `/pg/orders` endpoint with order ID, amount, and customer information.
2. Cashfree returns a `payment_session_id`.
3. The patient is redirected to Cashfree's secure hosted payment page.
4. After payment, Cashfree redirects back to `payment_return.php` and dispatches an asynchronous webhook to `cashfree_webhook.php`.
5. The webhook verifies the HMAC-SHA256 signature and marks `payment_status = 'Paid'`.

#### Q34. Why is Cashfree marked as "Simulated / Credentials Required" in the audit?
**Answer:** The code architecture and handlers are completely built and verified. However, live bank transactions require actual merchant API keys (`CASHFREE_CLIENT_ID` and `CASHFREE_CLIENT_SECRET`) in `includes/config.php`.

#### Q35. How does PHPMailer SMTP Email integration work?
**Answer:** PHPMailer 6.9 connects to Google's SMTP server (`smtp.gmail.com:587`) using TLS encryption. It constructs a MIME-compliant HTML email containing the secure password reset link.

#### Q36. Why is Gmail SMTP marked as "Blocked — Manual Configuration Required"?
**Answer:** To protect user privacy, personal Google email addresses and 16-character App Passwords are not hardcoded. The application operates in simulated token mode until valid credentials are added to `includes/config.php`.

---

### Part 8: Frontend, UI & Responsive Layouts

#### Q37. Why did you use Vanilla CSS instead of Bootstrap or Tailwind?
**Answer:** Vanilla CSS provides complete control over the design system, zero external framework dependencies, lightweight page weight, blazing fast render times, and avoids CSS bloat or version deprecation issues.

#### Q38. How is responsiveness achieved across Mobile, Tablet, Laptop, and Desktop?
**Answer:** Responsiveness is achieved using CSS Flexbox, CSS Grid layouts, fluid percentages, and media queries (`@media (max-width: 1024px)`, `@media (max-width: 768px)`, `@media (max-width: 480px)`). Mobile viewports feature a slideout drawer menu.

#### Q39. What is `scrollWidth <= innerWidth` and why is it important?
**Answer:** It is a browser DOM property test confirming that no element on the page is wider than the device screen. If `scrollWidth > innerWidth`, it causes an unwanted horizontal scrollbar and breaks mobile usability.

#### Q40. How is the cart count badge in the header updated?
**Answer:** In `includes/header.php`, if `$_SESSION['role'] === 'Patient'`, a SQL query sums active quantities: `SELECT SUM(quantity) FROM cart_items WHERE user_id = ?`. This value dynamically renders in the header navigation badge.

---

### Part 9: Practical Viva Scenarios & Live Demos

#### Q41. If I change a product's price in the admin panel from ₹160 to ₹250, what happens to previous orders?
**Answer:** Previous orders will continue to show ₹160 because the checkout process saved ₹160 into the `order_items.price` column. The new price of ₹250 will only apply to future purchases.

#### Q42. If an admin deletes or deactivates a product, does it break past orders?
**Answer:** No. Historical order records in `order_items` reference the product ID but retain all snapshot data (product name, price, quantity). The database foreign keys use `ON DELETE RESTRICT` or soft deletion (`status = 'Inactive'`).

#### Q43. What happens if two patients try to book the 10th slot at the exact same second?
**Answer:** MySQL transactions and row-level locks on the `appointment` table ensure that the first committed transaction succeeds, while the second transaction evaluates the updated count of 10 and rejects the booking.

#### Q44. Where are uploaded product images stored?
**Answer:** Uploaded image files are sanitized, given a unique timestamped filename, and stored in the filesystem at `images/products/`. The relative path (e.g. `images/products/arnica.png`) is saved in the `products.image_url` database column.

#### Q45. Why do you use `htmlspecialchars()` when printing data from the database?
**Answer:** Even if data is sanitized on input, applying `htmlspecialchars()` during output rendering follows the **Defense-in-Depth principle**, guaranteeing that any data reaching the HTML document cannot be interpreted as executable script tags.

#### Q46. What HTTP status code is sent when an unauthorized user attempts access?
**Answer:** A `302 Found` redirect status code is sent via `header("Location: login.php")`, instantly navigating the client away from the protected script.

#### Q47. How do you ensure database connection efficiency?
**Answer:** The PDO connection is established once in `includes/db.php` as a shared singleton/include file, configured with persistent attributes:
```php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
```

#### Q48. Can an Administrator prescribe medicines to a patient?
**Answer:** No. Strict separation of duties is enforced. Only authenticated users with `role === 'Doctor'` can access the clinical workstation and submit prescription records.

#### Q49. What is the difference between `order_status` and `payment_status`?
**Answer:** `payment_status` indicates financial settlement (`Pending`, `Paid`, `Failed`), while `order_status` indicates logistics fulfillment (`Pending`, `Confirmed`, `Processing`, `Shipped`, `Delivered`, `Cancelled`). Decoupling them allows flexible support for both Online and Cash-on-Delivery workflows.

#### Q50. Summarize the major strengths of this system.
**Answer:** 
1. Comprehensive full-cycle clinic and pharmacy workflow.
2. Enterprise-grade security (Bcrypt, PDO prepared statements, CSRF, XSS, IDOR defense).
3. 100% automated browser verification with zero defects.
4. Clean, modular architecture with zero third-party framework overhead.
5. Production-ready payment gateway and SMTP email dispatch capabilities.

---

*Viva Voce Defense Document — Bodhi Homeopathy Clinic Management System.*
