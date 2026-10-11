<?php
// Securely load configurations and database connection
require_once __DIR__ . '/db.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$authPages = ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php'];
$isAuthPage = in_array($currentPage, $authPages);

// Live Cart Count for Logged-in Patients
$cartCount = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Patient') {
    try {
        $cStmt = $pdo->prepare("SELECT SUM(`quantity`) FROM `cart_items` WHERE `user_id` = ?");
        $cStmt->execute([(int)$_SESSION['user_id']]);
        $cartCount = (int)$cStmt->fetchColumn();
    } catch (Exception $e) {
        $cartCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dynamic Page Title -->
    <title><?php echo isset($pageTitle) ? $pageTitle : "Bodhi Homeopathy"; ?></title>

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/nstyle.css?v=1.0.4">
    <?php if ($isAuthPage): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/auth.css?v=1.0.3">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<?php if (!$isAuthPage): ?>
    <!-- Floating Social Media Buttons (Hidden on Auth Pages) -->
    <div class="side-social">
        <a href="#" class="whatsapp" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
        <a href="#" class="facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" class="instagram" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
    </div>

    <!-- Main Navigation Header -->
    <header style="border-top:3px solid #657266;">
        <div class="top-nav">
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>index.php"><img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo"></a>
            </div>

            <!-- Desktop Navigation Links -->
            <nav class="main-nav" id="mainNav">
                <ul class="nav-links-list">
                    <li><a href="<?php echo BASE_URL; ?>index.php">HOME</a></li>
                    <li><a href="<?php echo BASE_URL; ?>about.php">ABOUT US</a></li>
                    <li><a href="<?php echo BASE_URL; ?>treatment.php">TREATMENTS</a></li>
                    <li><a href="<?php echo BASE_URL; ?>products.php">PRODUCTS</a></li>
                    <li><a href="<?php echo BASE_URL; ?>contact.php">CONTACT US</a></li>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] === 'Patient'): ?>
                            <li><a href="<?php echo BASE_URL; ?>cart.php" style="display: inline-flex; align-items: center; gap: 6px;"><i class="fas fa-shopping-cart"></i> CART <?php if ($cartCount > 0): ?><span style="background: var(--secondary); color: #fff; padding: 2px 7px; border-radius: 12px; font-size: 11px; font-weight: 700;"><?php echo $cartCount; ?></span><?php endif; ?></a></li>
                        <?php elseif ($_SESSION['role'] === 'Doctor'): ?>
                            <!-- Doctor Navigation Links -->
                            <li><a href="<?php echo BASE_URL; ?>doctor/dashboard.php">DOCTOR DASHBOARD</a></li>
                            <li><a href="<?php echo BASE_URL; ?>logout.php" class="btn-logout-nav" title="Logout"><i class="fas fa-sign-out-alt"></i></a></li>
                        <?php elseif ($_SESSION['role'] === 'Admin'): ?>
                            <!-- Admin Navigation Links -->
                            <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php">ADMIN DASHBOARD</a></li>
                            <li><a href="<?php echo BASE_URL; ?>logout.php" class="btn-logout-nav" title="Logout"><i class="fas fa-sign-out-alt"></i></a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guest Navigation Links -->
                        <li><a href="<?php echo BASE_URL; ?>login.php" class="btn-login-nav">LOGIN</a></li>
                        <li><a href="<?php echo BASE_URL; ?>register.php" class="btn-register-nav" style="font-size: 13px; font-weight: 600; color: var(--primary-dark); padding: 8px 16px; border: 1px solid rgba(79, 111, 82, 0.35); border-radius: 50px; text-decoration: none; display: inline-block; white-space: nowrap; transition: all 0.25s ease;" onmouseover="this.style.borderColor='var(--primary)'; this.style.color='var(--primary)';" onmouseout="this.style.borderColor='rgba(79, 111, 82, 0.35)'; this.style.color='var(--primary-dark)';">REGISTER</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Navigation Actions: User/Mobile Hamburger -->
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Patient'): ?>
                    <!-- Logged-in Patient Book Appointment Button -->
                    <a href="<?php echo BASE_URL; ?>book.php" class="btn-book">
                        BOOK APPOINTMENT
                    </a>

                    <!-- Logged-in Patient Hamburger User Menu Trigger -->
                    <div class="user-menu-wrapper">
                        <button type="button" class="user-menu-btn" id="userMenuBtn" aria-label="Toggle patient account menu" aria-expanded="false" onclick="toggleUserDropdown(event)">
                            <i class="fas fa-bars"></i>
                            <span class="user-menu-avatar"><i class="fas fa-user"></i></span>
                        </button>

                        <!-- Desktop Account Dropdown Panel -->
                        <div class="user-dropdown-panel" id="userDropdownPanel" role="menu">
                            <div class="user-dropdown-header">
                                <div class="dropdown-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                <div class="dropdown-user-role">Patient Account</div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i> My Profile</a>
                            <a href="<?php echo BASE_URL; ?>bookings.php" class="dropdown-item"><i class="fas fa-calendar-check"></i> My Bookings</a>
                            <a href="<?php echo BASE_URL; ?>cart.php" class="dropdown-item"><i class="fas fa-shopping-cart"></i> My Cart <?php if ($cartCount > 0): ?>(<?php echo $cartCount; ?>)<?php endif; ?></a>
                            <a href="<?php echo BASE_URL; ?>my_orders.php" class="dropdown-item"><i class="fas fa-shopping-bag"></i> My Products</a>
                            <a href="<?php echo BASE_URL; ?>forgot_password.php" class="dropdown-item"><i class="fas fa-key"></i> Change Password</a>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item dropdown-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Responsive Mobile Hamburger Toggle -->
                <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu" aria-expanded="false" onclick="toggleMobileNav(event)">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Slideout Navigation Panel -->
        <div class="mobile-nav-panel" id="mobileNavPanel">
            <div class="mobile-nav-header">
                <img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo" class="mobile-nav-logo">
                <button type="button" class="mobile-nav-close" aria-label="Close mobile menu" onclick="closeMobileNav()">&times;</button>
            </div>
            <ul class="mobile-nav-list">
                <li><a href="<?php echo BASE_URL; ?>index.php" onclick="closeMobileNav()"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>about.php" onclick="closeMobileNav()"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="<?php echo BASE_URL; ?>treatment.php" onclick="closeMobileNav()"><i class="fas fa-stethoscope"></i> Treatments</a></li>
                <li><a href="<?php echo BASE_URL; ?>products.php" onclick="closeMobileNav()"><i class="fas fa-pills"></i> Products</a></li>
                <li><a href="<?php echo BASE_URL; ?>contact.php" onclick="closeMobileNav()"><i class="fas fa-map-marker-alt"></i> Contact Us</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'Patient'): ?>
                        <li class="mobile-nav-divider"></li>
                        <li class="mobile-section-label">Patient Menu</li>
                        <li><a href="<?php echo BASE_URL; ?>profile.php" onclick="closeMobileNav()"><i class="fas fa-user-circle"></i> My Profile</a></li>
                        <li><a href="<?php echo BASE_URL; ?>bookings.php" onclick="closeMobileNav()"><i class="fas fa-calendar-check"></i> My Bookings</a></li>
                        <li><a href="<?php echo BASE_URL; ?>cart.php" onclick="closeMobileNav()"><i class="fas fa-shopping-cart"></i> Shopping Cart <?php if ($cartCount > 0): ?>(<?php echo $cartCount; ?>)<?php endif; ?></a></li>
                        <li><a href="<?php echo BASE_URL; ?>my_orders.php" onclick="closeMobileNav()"><i class="fas fa-shopping-bag"></i> My Products</a></li>
                        <li><a href="<?php echo BASE_URL; ?>forgot_password.php" onclick="closeMobileNav()"><i class="fas fa-key"></i> Change Password</a></li>
                        <li class="mobile-nav-divider"></li>
                        <li><a href="<?php echo BASE_URL; ?>logout.php" class="mobile-logout-link" onclick="closeMobileNav()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php elseif ($_SESSION['role'] === 'Doctor'): ?>
                        <li class="mobile-nav-divider"></li>
                        <li><a href="<?php echo BASE_URL; ?>doctor/dashboard.php" onclick="closeMobileNav()"><i class="fas fa-user-md"></i> Doctor Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>logout.php" class="mobile-logout-link" onclick="closeMobileNav()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php elseif ($_SESSION['role'] === 'Admin'): ?>
                        <li class="mobile-nav-divider"></li>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" onclick="closeMobileNav()"><i class="fas fa-user-shield"></i> Admin Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>logout.php" class="mobile-logout-link" onclick="closeMobileNav()"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="mobile-nav-divider"></li>
                    <li><a href="<?php echo BASE_URL; ?>login.php" onclick="closeMobileNav()"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" onclick="closeMobileNav()"><i class="fas fa-user-plus"></i> Register / Sign Up</a></li>
                <?php endif; ?>
            </ul>

            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Patient'): ?>
                <div class="mobile-nav-cta">
                    <a href="<?php echo BASE_URL; ?>book.php" class="btn-book" style="display: block; text-align: center;" onclick="closeMobileNav()">BOOK APPOINTMENT</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="mobile-nav-backdrop" id="mobileNavBackdrop" onclick="closeMobileNav()"></div>
    </header>
    <div class="header-divider"></div>
<?php endif; ?>