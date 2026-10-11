<?php
require_once __DIR__ . '/../includes/db.php';

// Security check: strictly enforce Admin role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$pageTitle = "Edit Remedy / Product | Bodhi Admin";

$rawId = $_GET['id'] ?? '';
$productId = filter_var($rawId, FILTER_VALIDATE_INT);
$product = null;

if ($productId !== false && $productId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $product = null;
    }
}

$errors = [];

if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security token expired. Please reload and submit again.";
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rawPrice = trim($_POST['price'] ?? '');
    $rawStock = trim($_POST['stock_quantity'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $imageUrl = $product['image_url'];

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

    // 6. Handle Image Upload if provided
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

    // If no validation errors, execute UPDATE
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE `products`
                SET `name` = ?, `description` = ?, `price` = ?, `stock_quantity` = ?, `status` = ?, `image_url` = ?
                WHERE `id` = ?
            ");
            $stmt->execute([$name, $description, $price, $stock_quantity, $status, $imageUrl, $productId]);

            $_SESSION['admin_product_msg'] = "Remedy '{$name}' (ID #{$productId}) updated successfully!";
            $_SESSION['admin_product_msg_type'] = "success";
            header("Location: " . BASE_URL . "admin/products.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Database update error: " . $e->getMessage();
        }
    } else {
        // Update local object for sticky inputs
        $product['name'] = $name;
        $product['description'] = $description;
        $product['price'] = $rawPrice;
        $product['stock_quantity'] = $rawStock;
        $product['status'] = $status;
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
                        <i class="fas fa-edit"></i> Edit Remedy Form
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
                
                <?php if ($product): ?>
                    
                    <div class="dash-page-heading">
                        <div class="dash-page-title">
                            <h1>Edit Remedy: <?php echo htmlspecialchars($product['name']); ?></h1>
                            <p>Update prescription details, adjust selling price, modify stock levels, or toggle visibility</p>
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
                                           value="<?php echo htmlspecialchars($product['name']); ?>" 
                                           required>
                                </div>

                                <!-- Clinical Indication / Description -->
                                <div class="form-group full-width">
                                    <label class="form-label" for="prodDesc">Clinical Indication & Details <span style="color: #E53E3E;">*</span></label>
                                    <textarea id="prodDesc" 
                                              name="description" 
                                              class="form-control" 
                                              rows="4" 
                                              required><?php echo htmlspecialchars($product['description']); ?></textarea>
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
                                           value="<?php echo htmlspecialchars($product['price']); ?>" 
                                           required>
                                </div>

                                <!-- Stock Quantity -->
                                <div class="form-group">
                                    <label class="form-label" for="prodStock">Stock Quantity in Pharmacy <span style="color: #E53E3E;">*</span></label>
                                    <input type="number" 
                                           step="1" 
                                           min="0" 
                                           id="prodStock" 
                                           name="stock_quantity" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" 
                                           required>
                                </div>

                                <!-- Status -->
                                <div class="form-group">
                                    <label class="form-label" for="prodStatus">Catalog Status <span style="color: #E53E3E;">*</span></label>
                                    <select id="prodStatus" name="status" class="form-control">
                                        <option value="Active" <?php echo $product['status'] === 'Active' ? 'selected' : ''; ?>>Active (Visible on Storefront)</option>
                                        <option value="Inactive" <?php echo $product['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive (Hidden from Patients)</option>
                                    </select>
                                </div>

                                <!-- Current Image & Replacement Upload -->
                                <div class="form-group">
                                    <label class="form-label" for="prodImg">Remedy Image</label>
                                    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 8px;">
                                        <?php 
                                            $currImg = !empty($product['image_url']) ? $product['image_url'] : 'a1.png';
                                        ?>
                                        <div style="width: 50px; height: 50px; background: #FAF9F6; border-radius: 6px; border: 1px solid var(--dash-border); display: flex; align-items: center; justify-content: center; padding: 2px; overflow: hidden; flex-shrink: 0;">
                                            <img src="<?php echo BASE_URL . 'assets/images/' . htmlspecialchars($currImg); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                                 onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                                        </div>
                                        <input type="file" 
                                               id="prodImg" 
                                               name="product_image" 
                                               class="form-control" 
                                               accept=".jpg,.jpeg,.png,.webp">
                                    </div>
                                    <small style="color: #718096; font-size: 11.5px; display: block;">
                                        Leave empty to retain current image (<code><?php echo htmlspecialchars($currImg); ?></code>).
                                    </small>
                                </div>

                            </div>

                            <!-- Form Actions -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--dash-border);">
                                <a href="<?php echo BASE_URL; ?>product_details.php?id=<?php echo $product['id']; ?>" target="_blank" style="font-size: 12.5px; color: var(--dash-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-external-link-alt"></i> View on Storefront
                                </a>
                                <div style="display: flex; gap: 12px;">
                                    <a href="<?php echo BASE_URL; ?>admin/products.php" style="padding: 10px 22px; border: 1px solid var(--dash-border); border-radius: 8px; font-size: 13px; font-weight: 600; color: #4A5568; text-decoration: none; display: inline-flex; align-items: center;">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; padding: 10px 24px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div>

                        </form>
                    </div>

                <?php else: ?>
                    
                    <!-- Product Not Found Card -->
                    <div class="dash-card" style="text-align: center; padding: 60px 20px; max-width: 550px; margin: 40px auto;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 18px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2 style="color: var(--dash-primary-dark); font-size: 20px; font-weight: 700; margin-bottom: 8px;">Product Not Found</h2>
                        <p style="color: #718096; font-size: 13.5px; margin-bottom: 22px;">
                            The requested homeopathic product reference does not exist in the database or has an invalid ID parameter.
                        </p>
                        <a href="<?php echo BASE_URL; ?>admin/products.php" class="btn-dash-primary" style="background: var(--dash-primary); color: #fff; padding: 10px 24px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-arrow-left"></i> Return to Products List
                        </a>
                    </div>

                <?php endif; ?>

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
