<?php
require_once __DIR__ . '/../includes/db.php';

$pageTitle = "Homeopathic Products & Pharmacy | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';

// Handle product search query
$search = trim($_GET['search'] ?? '');
$products = [];

try {
    if (!empty($search)) {
        $searchTerm = '%' . $search . '%';
        $stmt = $pdo->prepare("
            SELECT * FROM `products` 
            WHERE `status` = 'Active' 
              AND (`name` LIKE ? OR `description` LIKE ?) 
            ORDER BY `id` ASC
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `products` WHERE `status` = 'Active' ORDER BY `id` ASC");
        $stmt->execute();
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}
?>

<!-- Hero Banner for Products Catalog -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Homeopathic Pharmacy</h1>
            <p>Pure Classical Formulations & Trusted Natural Remedies</p>
        </div>
    </div>
</section>

<!-- Products Catalog Section -->
<section class="container" style="margin-top: 45px; margin-bottom: 80px;">

    <!-- Flash Alerts -->
    <?php 
        $flashSuccess = $_SESSION['cart_success'] ?? '';
        $flashError = $_SESSION['cart_error'] ?? '';
        unset($_SESSION['cart_success'], $_SESSION['cart_error']);
    ?>
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" style="background: #DEF7EC; color: #03543F; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #BCF0DA;">
            <i class="fas fa-check-circle" style="font-size: 16px;"></i> <?php echo $flashSuccess; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; border: 1px solid #F8B4B4;">
            <i class="fas fa-exclamation-circle" style="font-size: 16px;"></i> <?php echo $flashError; ?>
        </div>
    <?php endif; ?>

    <div style="text-align: center; max-width: 700px; margin: 0 auto 30px;">
        <h2 style="color: var(--primary-dark); font-size: 28px; font-weight: 700; margin-bottom: 8px;">Classical Homeopathic Catalog</h2>
        <div style="width: 60px; height: 3px; background: var(--secondary); margin: 0 auto 15px;"></div>
        <p style="color: #666; font-size: 14px; line-height: 1.6; margin: 0;">
            All medicines at Bodhi Homeopathy are prepared under strict quality standards and customized to patient constitutional diagnosis under expert physician guidance.
        </p>
    </div>

    <!-- Search Bar -->
    <div style="max-width: 580px; margin: 0 auto 40px;">
        <form action="<?php echo BASE_URL; ?>products.php" method="GET" style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative; flex-grow: 1;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #A0AEC0; font-size: 14px;"></i>
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search remedies (e.g., Arnica, Cough, Joint...)" 
                       style="width: 100%; padding: 11px 15px 11px 38px; border: 1px solid #CBD5E0; border-radius: 30px; font-family: inherit; font-size: 13.5px; outline: none; transition: border 0.2s;"
                       onfocus="this.style.borderColor='var(--primary)';"
                       onblur="this.style.borderColor='#CBD5E0';">
            </div>
            <button type="submit" class="btn-book" style="padding: 11px 22px; border-radius: 30px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?php echo BASE_URL; ?>products.php" style="font-size: 13px; color: #718096; text-decoration: none; padding: 10px 14px; background: #EDF2F7; border-radius: 20px; font-weight: 500;">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($search)): ?>
        <div style="margin-bottom: 25px; font-size: 14px; color: #4A5568;">
            Showing results for <strong>"<?php echo htmlspecialchars($search); ?>"</strong> (<?php echo count($products); ?> item<?php echo count($products) !== 1 ? 's' : ''; ?> found):
        </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <?php if (!empty($products)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px;">
            <?php foreach ($products as $p): ?>
                <?php 
                    $stockCount = (int)($p['stock_quantity'] ?? 0);
                    $inStock = $stockCount > 0;
                    $imgFilename = !empty($p['image_url']) ? $p['image_url'] : 'a1.png';
                    $imgPath = BASE_URL . 'assets/images/' . htmlspecialchars($imgFilename);
                    $categoryName = (strpos($p['name'], 'Q') !== false) ? 'Mother Tincture' : 'Homeopathic Dilution';
                ?>
                <div class="dash-card" style="padding: 22px; border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(53, 94, 59, 0.05); border: 1px solid rgba(79, 111, 82, 0.12); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.25s ease, box-shadow 0.25s ease;">
                    <div>
                        <!-- Product Image Box -->
                        <div style="width: 100%; height: 160px; background: #FAF9F6; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; padding: 12px; border: 1px solid #F0F0F0; overflow: hidden; position: relative;">
                            <img src="<?php echo $imgPath; ?>" 
                                 alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                 style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                 onerror="this.onerror=null; this.src='<?php echo BASE_URL; ?>assets/images/a1.png';">
                        </div>

                        <!-- Top Badges -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="display: inline-block; padding: 3px 9px; border-radius: 15px; font-size: 10.5px; font-weight: 600; background: var(--primary-light); color: var(--primary);">
                                <?php echo htmlspecialchars($categoryName); ?>
                            </span>
                            <?php if ($inStock): ?>
                                <span style="font-size: 11px; color: #03543F; font-weight: 600; background: #DEF7EC; padding: 3px 8px; border-radius: 12px;">
                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $stockCount; ?>)
                                </span>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #9B1C1C; font-weight: 600; background: #FDE8E8; padding: 3px 8px; border-radius: 12px;">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Title & Description -->
                        <h3 style="color: var(--primary-dark); font-size: 16.5px; font-weight: 700; margin-bottom: 8px; line-height: 1.35;">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </h3>
                        <p style="color: #666; font-size: 12.8px; line-height: 1.5; margin-bottom: 16px;">
                            <?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 110, '...')); ?>
                        </p>
                    </div>

                    <div>
                        <!-- Price Box -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #F0F0F0; margin-bottom: 14px;">
                            <div>
                                <span style="font-size: 10.5px; color: #888; display: block; text-transform: uppercase; font-weight: 600;">MRP Price</span>
                                <span style="font-size: 18px; font-weight: 700; color: var(--secondary);">
                                    ₹<?php echo number_format($p['price'], 2); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <?php if ($inStock): ?>
                            <form action="<?php echo BASE_URL; ?>cart_action.php" method="POST" style="margin-bottom: 8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn-add-cart" style="width: 100%; background: #EEF6EF; color: var(--primary-dark); border: 1px solid rgba(79, 111, 82, 0.35); padding: 9px 14px; border-radius: 50px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.25s ease; display: flex; align-items: center; justify-content: center; gap: 6px;" onmouseover="this.style.background='var(--primary)'; this.style.color='#fff';" onmouseout="this.style.background='#EEF6EF'; this.style.color='var(--primary-dark)';">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" disabled style="width: 100%; background: #F7FAFC; color: #A0AEC0; border: 1px solid #E2E8F0; padding: 9px 14px; border-radius: 50px; font-size: 12.5px; font-weight: 600; cursor: not-allowed; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fas fa-ban"></i> Out of Stock
                            </button>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>product_details.php?id=<?php echo $p['id']; ?>" class="btn-book" style="display: block; text-align: center; font-size: 12px; padding: 9px 16px; width: 100%; background: var(--secondary);">
                            View Details &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- No Products Found State -->
        <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 12px; max-width: 550px; margin: 40px auto; border: 1px solid #E2E8F0; box-shadow: 0 10px 25px rgba(0,0,0,0.03);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #F7FAFC; color: #A0AEC0; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 18px;">
                <i class="fas fa-prescription-bottle-alt"></i>
            </div>
            <h3 style="color: var(--primary-dark); font-size: 20px; font-weight: 700; margin-bottom: 8px;">No Products Found</h3>
            <p style="color: #666; font-size: 13.5px; margin-bottom: 20px;">
                <?php echo !empty($search) ? 'No homeopathic remedies matched your search query "' . htmlspecialchars($search) . '".' : 'No products are currently available in the pharmacy catalog.'; ?>
            </p>
            <?php if (!empty($search)): ?>
                <a href="<?php echo BASE_URL; ?>products.php" class="btn-book" style="padding: 9px 20px; font-size: 13px;">View All Products</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
