<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Add New Remedy / Product | Bodhi Admin";

$errors = [];
$name = '';
$description = '';
$price = '';
$stock_quantity = '10';
$status = 'Active';
$imageUrl = 'a1.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token expired. Please reload and submit again.";
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rawPrice = trim($_POST['price'] ?? '');
    $rawStock = trim($_POST['stock_quantity'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');

    // 1. Validate Name
    if (empty($name)) {
        $errors[] = "Product name is required.";
    } elseif (mb_strlen($name) > 255) {
        $errors[] = "Product name cannot exceed 255 characters.";
    }

    // 2. Validate Description
    if (empty($description)) {
        $errors[] = "Product clinical indication/description is required.";
    }

    // 3. Validate Price
    if ($rawPrice === '' || !is_numeric($rawPrice)) {
        $errors[] = "A valid numeric price is required.";
    } else {
        $price = (float)$rawPrice;
        if ($price < 0) {
            $errors[] = "Product price cannot be negative.";
        }
    }

    // 4. Validate Stock
    $stockVal = filter_var($rawStock, FILTER_VALIDATE_INT);
    if ($stockVal === false || $stockVal < 0) {
        $errors[] = "Stock quantity must be a non-negative integer (0 or greater).";
    } else {
        $stock_quantity = $stockVal;
    }

    // 5. Validate Status
    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $errors[] = "Invalid product status selected.";
    }

    // 6. Handle Image Upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['product_image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Image upload failed with error code " . $file['error'];
        } else {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($fileExt, $allowedExts, true) || !in_array($mimeType, $allowedMimes, true)) {
                $errors[] = "Invalid image file format. Allowed formats: JPG, JPEG, PNG, WEBP.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = "Image file size exceeds the maximum limit of 5MB.";
            } else {
                $newFilename = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                $destination = __DIR__ . '/../images/' . $newFilename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $imageUrl = $newFilename;
                } else {
                    $errors[] = "Failed to save uploaded image to images directory.";
                }
            }
        }
    }

    // If no validation errors, execute INSERT
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO `products` (`name`, `description`, `price`, `stock_quantity`, `status`, `image_url`)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock_quantity, $status, $imageUrl]);
            $newId = $pdo->lastInsertId();

            $_SESSION['admin_product_msg'] = "Remedy '{$name}' (ID #{$newId}) added to catalog successfully!";
            $_SESSION['admin_product_msg_type'] = "success";
            header("Location: " . BASE_URL . "admin/products.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Database insert error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css?v=1.0.5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--dash-text-dark);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--dash-border);
            border-radius: 8px;
            font-size: 13.5px;
            font-family: inherit;
            color: var(--dash-text-dark);
            background: var(--dash-white);
            outline: none;
            transition: var(--dash-transition);
        }
        .form-control:focus {
            border-color: var(--dash-primary);
            box-shadow: 0 0 0 3px var(--dash-primary-light);
        }
        .alert-error {
            background: #FDE8E8;
            color: #9B1C1C;
            border: 1px solid #FBD5D5;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 22px;
        }
        .alert-error ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }
    </style>
</head>
<body class="dashboard-body">

    <!-- Mobile Backdrop -->
    <div class="dash-backdrop" id="dashBackdrop" onclick="toggleSidebar()"></div>

    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="dash-sidebar" id="dashSidebar">
            <div class="dash-brand">
                <img src="<?php echo BASE_URL; ?>assets/images/lgo.png" alt="Bodhi Logo">
                <div class="dash-brand-text">
                    <span class="dash-brand-title">Bodhi Admin</span>
                    <span class="dash-brand-subtitle">Clinic Management</span>
                </div>
            </div>

            <nav class="dash-nav">
                <span class="dash-nav-section">Core Modules</span>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="dash-nav-link">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>admin/patients.php" class="dash-nav-link">
                    <i class="fas fa-users"></i> Patients
                </a>
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="dash-nav-link">
                    <i class="fas fa-calendar-check"></i> Appointments
                </a>
                <a href="<?php echo BASE_URL; ?>admin/products.php" class="dash-nav-link active">
                    <i class="fas fa-pills"></i> Products / Medicines
                </a>
                <a href="<?php echo BASE_URL; ?>admin/orders.php" class="dash-nav-link">
                    <i class="fas fa-shopping-bag"></i> Orders
                </a>

            </nav>

            <div class="dash-sidebar-footer">
                <div class="dash-user-badge">
                    <div class="dash-avatar-circle">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="dash-user-info">
                        <div class="dash-user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <div class="dash-user-role">Administrator</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="dash-main">
            
            <!-- Top Header Bar -->
            <header class="dash-header">
                <div class="dash-header-left">
                    <button class="dash-menu-btn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="dash-portal-title">
                        <i class="fas fa-plus-circle"></i> Add Remedy Form
                        <span class="dash-header-badge">Admin Portal</span>
                    </div>
                </div>

                <div class="dash-header-right">
                    <a href="<?php echo BASE_URL; ?>admin/products.php" class="dash-header-link">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="dash-btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Main Body -->
            <main class="dash-body">
                
                <div class="dash-page-heading">
                    <div class="dash-page-title">
                        <h1>Add New Homeopathic Medicine</h1>
                        <p>Register a classical remedy, set initial stock, pricing, and upload prescription imagery</p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-error">
                        <div style="font-weight: 700; margin-bottom: 4px;">
                            <i class="fas fa-exclamation-triangle"></i> Please correct the following errors:
                        </div>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="dash-card" style="max-width: 800px;">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="form-grid">
                            
                            <!-- Product Name -->
                            <div class="form-group full-width">
                                <label class="form-label" for="prodName">Medicine / Remedy Name <span style="color: #E53E3E;">*</span></label>
                                <input type="text" 
                                       id="prodName" 
                                       name="name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($name); ?>" 
                                       placeholder="e.g., Arnica Montana 200C, Nux Vomica 30C..." 
                                       required>
                            </div>

                            <!-- Clinical Indication / Description -->
                            <div class="form-group full-width">
                                <label class="form-label" for="prodDesc">Clinical Indication & Details <span style="color: #E53E3E;">*</span></label>
                                <textarea id="prodDesc" 
                                          name="description" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Key indications, symptoms, dosage, and constitutional profile..." 
                                          required><?php echo htmlspecialchars($description); ?></textarea>
                            </div>

                            <!-- Price (₹) -->
                            <div class="form-group">
                                <label class="form-label" for="prodPrice">Selling Price (₹ INR) <span style="color: #E53E3E;">*</span></label>
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       id="prodPrice" 
                                       name="price" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($price); ?>" 
                                       placeholder="e.g., 140.00" 
                                       required>
                            </div>

                            <!-- Stock Quantity -->
                            <div class="form-group">
                                <label class="form-label" for="prodStock">Initial Stock Quantity <span style="color: #E53E3E;">*</span></label>
                                <input type="number" 
                                       step="1" 
                                       min="0" 
                                       id="prodStock" 
                                       name="stock_quantity" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($stock_quantity); ?>" 
                                       placeholder="e.g., 50" 
                                       required>
                            </div>

                            <!-- Status -->
                            <div class="form-group">
                                <label class="form-label" for="prodStatus">Catalog Status <span style="color: #E53E3E;">*</span></label>
                                <select id="prodStatus" name="status" class="form-control">
                                    <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active (Visible on Storefront)</option>
                                    <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive (Hidden from Patients)</option>
                                </select>
                            </div>

                            <!-- Product Image Upload -->
                            <div class="form-group">
                                <label class="form-label" for="prodImg">Remedy Image (JPG, PNG, WEBP max 5MB)</label>
                                <input type="file" 
                                       id="prodImg" 
                                       name="product_image" 
                                       class="form-control" 
                                       accept=".jpg,.jpeg,.png,.webp">
                                <small style="color: #718096; font-size: 11.5px; margin-top: 4px; display: block;">
                                    If left empty, default remedy graphic will be used.
                                </small>
                            </div>

                        </div>

                        <!-- Form Actions -->
                        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--dash-border);">
                            <a href="<?php echo BASE_URL; ?>admin/products.php" style="padding: 10px 22px; border: 1px solid var(--dash-border); border-radius: 8px; font-size: 13px; font-weight: 600; color: #4A5568; text-decoration: none; display: inline-flex; align-items: center;">
                                Cancel
                            </a>
                            <button type="submit" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; padding: 10px 24px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-plus-circle"></i> Add Remedy
                            </button>
                        </div>

                    </form>
                </div>

            </main>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('dashSidebar');
        const backdrop = document.getElementById('dashBackdrop');
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    }
    </script>
</body>
</html>
