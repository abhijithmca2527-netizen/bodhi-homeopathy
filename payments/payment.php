<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cashfree.php';

// -------------------------------------------------------------
// 1. Authentication & Security Check (BEFORE ANY OUTPUT)
// -------------------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'Patient') {
    header("Location: " . BASE_URL . "products.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orderId = filter_var($_GET['order_id'] ?? 0, FILTER_VALIDATE_INT);
$order = null;
$orderItems = [];
$paymentSessionId = '';
$cfError = '';
$paymentFailed = false;
$paymentFailedReason = '';

// -------------------------------------------------------------
// 2. Fetch Order with Strict Ownership Validation
// -------------------------------------------------------------
if ($orderId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, u.`full_name`, u.`email`, u.`phone`
            FROM `orders` o
            JOIN `users` u ON u.`id` = o.`user_id`
            WHERE o.`id` = ? AND o.`user_id` = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Fetch order items for summary
            $itemStmt = $pdo->prepare("
                SELECT oi.*, p.`name` AS `product_name`, p.`image_url`
                FROM `order_items` oi
                JOIN `products` p ON p.`id` = oi.`product_id`
                WHERE oi.`order_id` = ?
            ");
            $itemStmt->execute([$orderId]);
            $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            // -------------------------------------------------------------
            // 3. Server-Side Payment Verification (When returning from Cashfree)
            // -------------------------------------------------------------
            $cfOrderId = $_GET['cf_id'] ?? $order['cashfree_order_id'] ?? '';

            if (is_cashfree_configured() && !empty($cfOrderId) && $order['payment_status'] !== 'Paid' && (isset($_GET['cf_id']) || isset($_GET['cashfree_return']) || isset($_GET['return']))) {
                $paymentsRes = get_cashfree_order_payments($cfOrderId);
                $isPaid = false;
                $isFailed = false;

                if ($paymentsRes['success'] && is_array($paymentsRes['data'])) {
                    foreach ($paymentsRes['data'] as $payment) {
                        $pStatus = strtoupper($payment['payment_status'] ?? '');
                        if ($pStatus === 'SUCCESS') {
                            $isPaid = true;
                            break;
                        } elseif (in_array($pStatus, ['FAILED', 'CANCELLED', 'USER_DROPPED'], true)) {
                            $isFailed = true;
                            $paymentFailedReason = $payment['payment_message'] ?? 'Payment transaction was not completed.';
                        }
                    }
                } else {
                    // Fallback to top-level order status check
                    $orderRes = get_cashfree_order($cfOrderId);
                    if ($orderRes['success'] && strtoupper($orderRes['data']['order_status'] ?? '') === 'PAID') {
                        $isPaid = true;
                    }
                }

                if ($isPaid) {
                    $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Paid', `payment_method` = 'Online Payment' WHERE `id` = ? AND `user_id` = ?");
                    $updateStmt->execute([$orderId, $userId]);
                    $order['payment_status'] = 'Paid';
                    $order['payment_method'] = 'Online Payment';
                    
                    // Redirect to order success confirmation
                    header("Location: " . BASE_URL . "order_success.php?order_id=" . $orderId);
                    exit;
                } elseif ($isFailed) {
                    $updateStmt = $pdo->prepare("UPDATE `orders` SET `payment_status` = 'Failed' WHERE `id` = ? AND `user_id` = ? AND `payment_status` != 'Paid'");
                    $updateStmt->execute([$orderId, $userId]);
                    $order['payment_status'] = 'Failed';
                    $paymentFailed = true;
                }
            }

            // -------------------------------------------------------------
            // 4. If Already Paid, Redirect to Order Success Page
            // -------------------------------------------------------------
            if ($order['payment_status'] === 'Paid') {
                header("Location: " . BASE_URL . "order_success.php?order_id=" . $orderId);
                exit;
            }

            // -------------------------------------------------------------
            // 5. Generate Cashfree Payment Session ID
            // -------------------------------------------------------------
            if (is_cashfree_configured() && !$paymentFailed) {
                $cfRes = create_cashfree_order($order, [
                    'full_name' => $order['full_name'],
                    'email' => $order['email'],
                    'phone' => $order['phone']
                ]);

                if ($cfRes['success']) {
                    $paymentSessionId = $cfRes['payment_session_id'];
                    $createdCfOrderId = $cfRes['cf_order_id'];

                    // Record Cashfree Order ID in local database
                    $updateCfStmt = $pdo->prepare("UPDATE `orders` SET `cashfree_order_id` = ? WHERE `id` = ?");
                    $updateCfStmt->execute([$createdCfOrderId, $orderId]);
                } else {
                    $cfError = $cfRes['error'] ?? 'Unable to initialize Cashfree payment session.';
                    error_log("Cashfree create order error for order {$orderId}: " . $cfError);
                }
            }
        }
    } catch (Exception $e) {
        $order = null;
        $cfError = "Database error: " . $e->getMessage();
    }
}

// -------------------------------------------------------------
// 6. Output Begins (HTML Header Included Strictly After Processing)
// -------------------------------------------------------------
$pageTitle = "Secure Online Payment | Bodhi Homeopathy";
include __DIR__ . '/../includes/header.php';
?>

<style>
@keyframes cfSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.cf-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #E2E8F0;
    border-top: 4px solid var(--primary, #355E3B);
    border-radius: 50%;
    animation: cfSpin 0.85s linear infinite;
    margin: 0 auto;
}
</style>

<!-- Hero Banner -->
<section class="pro-contact-hero">
    <div class="hero-overlay-dark">
        <div class="container">
            <h1>Secure Online Payment</h1>
            <p>Complete your pharmacy order payment securely</p>
        </div>
    </div>
</section>

<!-- Main Payment Section -->
<section class="container" style="margin-top: 40px; margin-bottom: 80px;">

    <?php if ($order): ?>
        <div style="max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 40px 35px; border: 1px solid rgba(79, 111, 82, 0.15); box-shadow: 0 12px 40px rgba(53, 94, 59, 0.07);">
            
            <!-- Intermediate Redirect Screen (Shows on Button Click) -->
            <div id="redirectLoadingState" style="display: none; text-align: center; padding: 40px 20px;">
                <div class="cf-spinner"></div>
                <h3 style="color: var(--primary-dark); font-size: 21px; font-weight: 700; margin-top: 24px; margin-bottom: 8px;">
                    Redirecting you to secure payment...
                </h3>
                <p style="color: #718096; font-size: 14px; margin-bottom: 0;">
                    Please do not refresh or close this page.
                </p>
            </div>

            <!-- Standard Bodhi Payment Details Card -->
            <div id="paymentDetailsCard">
                <!-- Bodhi Branding Header -->
                <div style="text-align: center; margin-bottom: 30px; padding-bottom: 22px; border-bottom: 1px solid #EDF2F7;">
                    <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 50%; background: #EEF6EF; color: var(--primary); font-size: 28px; margin-bottom: 14px; box-shadow: 0 4px 12px rgba(53, 94, 59, 0.08);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h2 style="color: var(--primary-dark); font-size: 23px; font-weight: 700; margin: 0 0 6px 0; letter-spacing: -0.3px;">Bodhi Homeopathy Pharmacy</h2>
                    <span style="font-size: 13.5px; color: #718096; font-weight: 500;">Secure Online Payment</span>
                </div>

                <!-- Order Financial Summary Card -->
                <div style="background: #FAF9F6; border: 1px solid #E2E8F0; border-radius: 12px; padding: 22px 24px; margin-bottom: 28px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; font-size: 14px;">
                        <span style="color: #718096; font-weight: 500;">Order Reference:</span>
                        <strong class="payment-order-id" style="color: var(--primary-dark); font-size: 14.5px;">#ORD-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; font-size: 14px;">
                        <span style="color: #718096; font-weight: 500;">Patient:</span>
                        <strong class="payment-patient-name" style="color: #2D3748;"><?php echo htmlspecialchars($order['full_name']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; font-size: 14px;">
                        <span style="color: #718096; font-weight: 500;">Payment Method:</span>
                        <strong style="color: #2D3748;">Online Payment</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 14px; border-top: 1px dashed #CBD5E0; font-size: 15.5px;">
                        <strong style="color: var(--primary-dark);">Payable Amount:</strong>
                        <strong class="payment-order-amount" style="color: var(--secondary); font-size: 24px; font-weight: 800;">₹<?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                </div>

                <!-- Payment State Handling -->
                <?php if ($paymentFailed): ?>
                    <!-- Payment Not Completed Notice -->
                    <div class="alert alert-danger" style="background: #FDE8E8; color: #9B1C1C; padding: 18px 20px; border-radius: 10px; font-size: 13.5px; margin-bottom: 24px; border: 1px solid #F8B4B4;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                            <i class="fas fa-times-circle" style="font-size: 20px;"></i>
                            <strong style="font-size: 15px;">Payment Not Completed</strong>
                        </div>
                        <p style="margin: 0 0 14px 0; font-size: 13px;">
                            Your payment could not be completed.
                            <?php if (!empty($paymentFailedReason)): ?>
                                <br><span style="font-size: 12px; opacity: 0.85;"><?php echo htmlspecialchars($paymentFailedReason); ?></span>
                            <?php endif; ?>
                        </p>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <a href="<?php echo BASE_URL; ?>payment.php?order_id=<?php echo $orderId; ?>" class="btn-book" style="padding: 9px 18px; font-size: 12.5px; border-radius: 20px; text-decoration: none;">
                                <i class="fas fa-sync-alt"></i> TRY PAYMENT AGAIN
                            </a>
                            <a href="<?php echo BASE_URL; ?>my_orders.php" style="padding: 9px 18px; font-size: 12.5px; border-radius: 20px; background: #EDF2F7; color: #4A5568; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                                BACK TO MY ORDERS
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Primary Payment Action Section -->
                    <div style="text-align: center;">
                        <button type="button" 
                                id="payBtn" 
                                onclick="initiateCashfreePayment()"
                                class="btn-book" 
                                style="width: 100%; padding: 16px; border-radius: 30px; font-size: 15px; font-weight: 700; background: var(--primary); color: #ffffff; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 6px 20px rgba(53, 94, 59, 0.22); transition: all 0.25s ease; letter-spacing: 0.5px;">
                            CONTINUE TO PAYMENT &rarr;
                        </button>

                        <p style="font-size: 13px; color: #718096; margin-top: 16px; margin-bottom: 0;">
                            Secure online payment powered by Cashfree
                        </p>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 25px; text-align: center; border-top: 1px solid #EDF2F7; padding-top: 16px;">
                    <a href="<?php echo BASE_URL; ?>my_orders.php" style="color: #718096; font-size: 13px; text-decoration: none; font-weight: 600;">
                        &larr; Back to My Orders
                    </a>
                </div>
            </div>

        </div>

        <!-- Official Cashfree JavaScript SDK (v3) Integration -->
        <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
        <script>
            const cashfree = Cashfree({
                mode: "sandbox"
            });

            function initiateCashfreePayment() {
                const payDetailsCard = document.getElementById('paymentDetailsCard');
                const redirectState = document.getElementById('redirectLoadingState');
                const paymentSessionId = "<?php echo htmlspecialchars($paymentSessionId, ENT_QUOTES, 'UTF-8'); ?>";
                const cfInitError = "<?php echo htmlspecialchars(addslashes($cfError), ENT_QUOTES, 'UTF-8'); ?>";

                if (!paymentSessionId) {
                    const errorMsg = cfInitError ? ("Cashfree Sandbox Error: " + cfInitError) : "Cashfree Sandbox Session Error: Unable to initialize payment session. Please verify that your Cashfree Sandbox Client ID and Secret are configured in includes/config.php.";
                    alert(errorMsg);
                    return;
                }

                // Transition to intermediate redirect loading screen
                if (payDetailsCard && redirectState) {
                    payDetailsCard.style.display = 'none';
                    redirectState.style.display = 'block';
                }

                // Automatically trigger Cashfree official Hosted Checkout
                cashfree.checkout({
                    paymentSessionId: paymentSessionId,
                    redirectTarget: "_self"
                }).then(function(result) {
                    if (result && result.error) {
                        console.error("Cashfree Checkout Error:", result.error);
                        alert(result.error.message || "Cashfree payment checkout error.");
                        if (payDetailsCard && redirectState) {
                            payDetailsCard.style.display = 'block';
                            redirectState.style.display = 'none';
                        }
                    }
                }).catch(function(err) {
                    console.error("Cashfree Checkout Exception:", err);
                    alert("Cashfree checkout error: " + (err.message || err));
                    if (payDetailsCard && redirectState) {
                        payDetailsCard.style.display = 'block';
                        redirectState.style.display = 'none';
                    }
                });
            }
        </script>

    <?php else: ?>
        <!-- Order Not Found or Access Denied Card -->
        <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: 14px; max-width: 550px; margin: 30px auto; border: 1px solid rgba(79, 111, 82, 0.12); box-shadow: 0 10px 35px rgba(53, 94, 59, 0.05);">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center; font-size: 30px; margin: 0 auto 18px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="color: var(--primary-dark); font-size: 22px; font-weight: 700; margin-bottom: 8px;">Order Details Not Found</h2>
            <p style="color: #718096; font-size: 14px; line-height: 1.5; margin-bottom: 22px;">
                The requested order reference could not be located or does not belong to your authenticated patient account.
            </p>
            <a href="<?php echo BASE_URL; ?>my_orders.php" class="btn-book" style="padding: 11px 24px; font-size: 13px; border-radius: 30px; text-decoration: none;">
                Go to My Orders
            </a>
        </div>
    <?php endif; ?>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
