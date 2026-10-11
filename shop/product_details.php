<?php
require_once __DIR__ . '/../includes/db.php';

$productId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$product = null;

if ($productId && $productId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `products` WHERE `id` = ? AND `status` = 'Active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $product = null;
    }
}

$pageTitle = $product ? (htmlspecialchars($product['name']) . " | Bodhi Homeopathy Pharmacy") : "Product Not Found | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';
?>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <div style="font-size: 28px; font-weight: 700; color: #fff; margin-bottom: 5px;">Homeopathic Pharmacy</div>
            <p><?php echo $product ? htmlspecialchars($product['name']) : 'Homeopathic Remedy Information'; ?></p>
        </div>
    </div>
</section>

<section class="container" style="margin-top: 40px; margin-bottom: 80px;">
    
    <!-- Breadcrumb & Back Button -->
    <div style="margin-bottom: 30px;">
        <a href="<?php echo BASE_URL; ?>products.php" style="color: var(--primary); text-decoration: none; font-size: 13.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-arrow-left"></i> Back to Products Catalog
        </a>
    </div>

    <?php if ($product): ?>
        <?php 
            $stockCount = (int)($product['stock_quantity'] ?? 0);
            $inStock = $stockCount > 0;
            $imgFilename = !empty($product['image_url']) ? $product['image_url'] : 'a1.png';
            $imgPath = BASE_URL . 'assets/images/' . htmlspecialchars($imgFilename);
        ?>

        <div style="background: #ffffff; padding: 40px; border-radius: 14px; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.06);">
            <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 40px; align-items: start;">
                
                <!-- Product Image Box -->
                <div style="background: #FAF9F6; border-radius: 10px; padding: 30px; border: 1px solid #E2E8F0; text-align: center; display: flex; align-items: center; justify-content: center; min-height: 320px;">
                    <img src="<?php echo $imgPath; ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         style="max-width: 100%; max-height: 280px; object-fit: contain;"
                         onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                </div>

                <!-- Product Content Box -->
                <div>
                    <!-- Badges -->
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                        <?php $categoryName = (strpos($product['name'], 'Q') !== false) ? 'Mother Tincture' : 'Homeopathic Dilution'; ?>
                        <span style="padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 600; background: var(--primary-light); color: var(--primary);">
                            <?php echo htmlspecialchars($categoryName); ?>
                        </span>
                        <?php if ($inStock): ?>
                            <span style="font-size: 12px; color: #03543F; font-weight: 600; background: #DEF7EC; padding: 4px 12px; border-radius: 20px;">
                                <i class="fas fa-check-circle"></i> In Stock (<?php echo $stockCount; ?> units available)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #9B1C1C; font-weight: 600; background: #FDE8E8; padding: 4px 12px; border-radius: 20px;">
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Title -->
                    <h1 style="color: var(--primary-dark); font-size: 28px; font-weight: 700; margin-bottom: 12px; line-height: 1.3;">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>

                    <!-- Price Box -->
                    <div style="margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid #EDF2F7;">
                        <span style="font-size: 12px; color: #718096; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">MRP Price (Incl. Taxes)</span>
                        <span style="font-size: 28px; font-weight: 800; color: var(--secondary);">
                            ₹<?php echo number_format($product['price'], 2); ?>
                        </span>
                    </div>

                    <!-- Description -->
                    <div style="margin-bottom: 25px;">
                        <h4 style="font-size: 13.5px; color: var(--primary-dark); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 8px;">Remedy Description</h4>
                        <p style="color: #4A5568; font-size: 14.5px; line-height: 1.7; margin: 0;">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </p>
                    </div>

                    <!-- Product Guidance Box -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 16px 20px; margin-bottom: 30px;">
                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <i class="fas fa-info-circle" style="font-size: 18px; color: var(--primary); margin-top: 2px;"></i>
                            <div>
                                <h5 style="color: var(--primary-dark); font-size: 13.5px; font-weight: 700; margin: 0 0 4px 0;">Product Guidance</h5>
                                <p style="color: #4A5568; font-size: 12.8px; line-height: 1.5; margin: 0;">
                                    Please follow the product label and usage instructions. If you are unsure whether a product is suitable for you, consult a qualified healthcare practitioner.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                        <?php if ($inStock): ?>
                            <form action="<?php echo BASE_URL; ?>cart_action.php" method="POST" style="margin: 0; display: inline-block;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn-book" style="padding: 13px 28px; font-size: 14px; border-radius: 30px; background: var(--primary); cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" disabled style="padding: 13px 28px; font-size: 14px; border-radius: 30px; background: #F7FAFC; color: #A0AEC0; border: 1px solid #E2E8F0; cursor: not-allowed; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-ban"></i> Out of Stock
                            </button>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>products.php" style="padding: 13px 24px; font-size: 14px; border-radius: 30px; background: #EDF2F7; color: #4A5568; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-boxes"></i> Browse More Remedies
                        </a>
                    </div>

                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Product Not Found Card -->
        <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 14px; max-width: 600px; margin: 30px auto; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.06);">
            <div style="width: 75px; height: 75px; border-radius: 50%; background: #FFF5F5; color: #E53E3E; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h2 style="color: var(--primary-dark); font-size: 24px; font-weight: 700; margin-bottom: 10px;">Product Not Found</h2>
            <p style="color: #718096; font-size: 14px; line-height: 1.6; margin-bottom: 25px;">
                The homeopathic product you are looking for does not exist or is currently inactive in our pharmacy catalog.
            </p>
            <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="padding: 12px 26px; font-size: 13.5px; border-radius: 30px; text-decoration: none;">
                &larr; Return to Products Catalog
            </a>
        </div>
    <?php endif; ?>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
