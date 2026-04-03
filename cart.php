<?php

include_once 'config/config.php';

$userId = $_SESSION['user_id'] ?? null;
$alerts = [];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($userId === null) {
    header('Location: member_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $alerts[] = ['type' => 'danger', 'text' => 'Invalid request token. Please refresh and try again.'];
    } else {
        try {
            if ($action === 'update_quantity') {
                $cartId = trim((string)($_POST['cart_id'] ?? ''));
                $quantity = max(1, (int)($_POST['quantity'] ?? 1));

                $itemStmt = $pdo->prepare("SELECT c.CartId, p.ProductName, p.StockQuantity
                                           FROM Carts c
                                           JOIN Products p ON p.ProductId = c.ProductId
                                           WHERE c.CartId = :cart_id AND c.UserId = :user_id
                                           LIMIT 1");
                $itemStmt->execute([':cart_id' => $cartId, ':user_id' => $userId]);
                $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    $alerts[] = ['type' => 'warning', 'text' => 'Cart item not found.'];
                } else {
                    $stockQty = (int)$item['StockQuantity'];
                    if ($quantity > $stockQty) {
                        $alerts[] = ['type' => 'warning', 'text' => 'Only ' . $stockQty . ' units available for ' . $item['ProductName'] . '.'];
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE Carts SET Quantity = :quantity WHERE CartId = :cart_id AND UserId = :user_id");
                        $updateStmt->execute([':quantity' => $quantity, ':cart_id' => $cartId, ':user_id' => $userId]);
                        $alerts[] = ['type' => 'success', 'text' => 'Quantity updated successfully.'];
                    }
                }
            } elseif ($action === 'remove_item') {
                $cartId = trim((string)($_POST['cart_id'] ?? ''));
                $deleteStmt = $pdo->prepare("DELETE FROM Carts WHERE CartId = :cart_id AND UserId = :user_id");
                $deleteStmt->execute([':cart_id' => $cartId, ':user_id' => $userId]);

                if ($deleteStmt->rowCount() > 0) {
                    $alerts[] = ['type' => 'success', 'text' => 'Item removed from cart.'];
                } else {
                    $alerts[] = ['type' => 'warning', 'text' => 'Item not found or already removed.'];
                }
            } elseif ($action === 'clear_cart') {
                $clearStmt = $pdo->prepare("DELETE FROM Carts WHERE UserId = :user_id");
                $clearStmt->execute([':user_id' => $userId]);
                $alerts[] = ['type' => 'success', 'text' => 'Cart cleared.'];
            }
        } catch (Exception $e) {
            $alerts[] = ['type' => 'danger', 'text' => 'Unable to update cart: ' . $e->getMessage()];
        }
    }
}

$cartItemsStmt = $pdo->prepare("SELECT c.CartId,
                                       c.Quantity,
                                       p.ProductId,
                                       p.ProductName,
                                       p.Description,
                                       p.Price,
                                       p.StockQuantity,
                                       (SELECT pi.ImageUrl
                                        FROM ProductImages pi
                                        WHERE pi.ProductId = p.ProductId
                                        ORDER BY pi.IsPrimary DESC, pi.ImageId ASC
                                        LIMIT 1) AS MainImage
                                FROM Carts c
                                JOIN Products p ON p.ProductId = c.ProductId
                                WHERE c.UserId = :user_id
                                ORDER BY c.AddedDate DESC");
$cartItemsStmt->execute([':user_id' => $userId]);
$cartItems = $cartItemsStmt->fetchAll(PDO::FETCH_ASSOC);

$cartTotal = 0.0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $lineQty = (int)$item['Quantity'];
    $lineTotal = ((float)$item['Price']) * $lineQty;
    $cartTotal += $lineTotal;
    $totalItems += $lineQty;
}

$shippingFee = $cartTotal >= 50 ? 0.00 : (empty($cartItems) ? 0.00 : 8.00);
$grandTotal = $cartTotal + $shippingFee;


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce | Cart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --cart-ink: #1b2730;
            --cart-muted: #6c7b88;
            --cart-accent: #0f8f6f;
            --cart-accent-dark: #0a6f56;
            --cart-line: #dceae5;
            --cart-soft: #f2fbf7;
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--cart-ink);
            background: radial-gradient(1100px 420px at 0% 0%, #eefbf5 0%, transparent 52%),
                        radial-gradient(900px 420px at 100% 24%, #edf4ff 0%, transparent 52%),
                        #f8fbfa;
        }

        .cart-wrap {
            max-width: 1180px;
        }

        .cart-hero {
            background: linear-gradient(145deg, #ffffff 0%, #f6fcfa 62%, #f1f7ff 100%);
            border: 1px solid var(--cart-line);
            border-radius: 20px;
            box-shadow: 0 12px 28px rgba(11, 59, 45, 0.08);
        }

        .cart-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .cart-sub {
            color: var(--cart-muted);
            margin: 0;
        }

        .cart-panel,
        .summary-panel {
            border: 1px solid var(--cart-line);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 12px 24px rgba(11, 59, 45, 0.07);
        }

        .cart-item {
            border: 1px solid #e2ede8;
            border-radius: 16px;
            padding: 12px;
            background: #fff;
        }

        .cart-item + .cart-item {
            margin-top: 14px;
        }

        .cart-image {
            width: 100%;
            max-width: 120px;
            height: 110px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid #e0ece6;
            background: #f3f8f6;
        }

        .cart-name {
            font-size: 1.03rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .cart-desc {
            color: var(--cart-muted);
            font-size: 0.9rem;
            line-height: 1.45;
            margin-bottom: 6px;
        }

        .cart-price {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--cart-accent-dark);
        }

        .qty-control {
            max-width: 92px;
        }

        .btn-cart-update {
            background: linear-gradient(135deg, var(--cart-accent), var(--cart-accent-dark));
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.45rem 0.8rem;
        }

        .btn-cart-update:hover {
            color: #fff;
            filter: brightness(0.98);
        }

        .btn-cart-remove {
            border: 1px solid #e7c1c1;
            color: #b44646;
            background: #fff;
            border-radius: 10px;
            padding: 0.45rem 0.72rem;
            font-weight: 600;
        }

        .btn-cart-remove:hover {
            background: #fff3f3;
            color: #a23737;
        }

        .summary-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.7rem;
            color: #345047;
            font-size: 0.96rem;
        }

        .summary-row.total {
            border-top: 1px dashed #cfe2db;
            padding-top: 0.9rem;
            margin-top: 0.9rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #17382f;
        }

        .btn-checkout {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--cart-accent), var(--cart-accent-dark));
            color: #fff;
            font-weight: 700;
            padding: 0.74rem 1rem;
            margin-top: 0.5rem;
            box-shadow: 0 10px 18px rgba(11, 111, 86, 0.25);
        }

        .btn-checkout:disabled {
            opacity: 0.65;
            box-shadow: none;
        }

        .btn-clear {
            width: 100%;
            margin-top: 0.65rem;
            border: 1px solid #e4b8b8;
            border-radius: 12px;
            background: #fff;
            color: #b44646;
            font-weight: 700;
            padding: 0.68rem 1rem;
        }

        .empty-cart {
            min-height: 300px;
            border: 1px dashed #c8e2d8;
            border-radius: 16px;
            background: var(--cart-soft);
        }
    </style>
</head>
<body>
    <?php include 'layout/nav.php'; ?>

    <div class="container cart-wrap py-4 py-md-5">
        <section class="cart-hero p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3">
                <div>
                    <h1 class="cart-title">Your Shopping Cart</h1>
                    <p class="cart-sub mt-2">Review your items, adjust quantity, and continue to checkout.</p>
                </div>
                <div class="text-md-end">
                    <div class="small text-muted">Total Items</div>
                    <div class="fw-bold fs-5"><?= $totalItems ?></div>
                </div>
            </div>
        </section>
        <?php if (!empty($_SESSION['checkout_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string)$_SESSION['checkout_success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['checkout_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['checkout_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string)$_SESSION['checkout_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['checkout_error']); ?>
        <?php endif; ?>

        <?php if (!empty($alerts)): ?>
            <div class="mb-4">
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert alert-<?= htmlspecialchars($alert['type']) ?> mb-2" role="alert">
                        <?= htmlspecialchars($alert['text']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="cart-panel p-3 p-md-4">
                    <?php if (empty($cartItems)): ?>
                        <div class="empty-cart d-flex flex-column align-items-center justify-content-center text-center p-4">
                            <i class="fas fa-cart-shopping fs-1 text-muted mb-2"></i>
                            <h5 class="fw-bold mb-1">Your cart is empty</h5>
                            <p class="text-muted mb-3">Looks like you have not added anything yet.</p>
                            <a href="product.php" class="btn btn-cart-update">Start Shopping</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                                $stockQty = (int)$item['StockQuantity'];
                                $qty = (int)$item['Quantity'];
                                $lineTotal = ((float)$item['Price']) * $qty;
                            ?>
                            <div class="cart-item">
                                <div class="row g-3 align-items-center">
                                    <div class="col-auto">
                                        <a href="product_details.php?id=<?= urlencode($item['ProductId']) ?>">
                                            <img
                                                src="<?= htmlspecialchars($item['MainImage'] ?: 'asset/image/no-image.jpg') ?>"
                                                alt="<?= htmlspecialchars($item['ProductName']) ?>"
                                                class="cart-image"
                                            >
                                        </a>
                                    </div>

                                    <div class="col">
                                        <div class="cart-name"><?= htmlspecialchars($item['ProductName']) ?></div>
                                        <div class="cart-desc"><?= htmlspecialchars((string)($item['Description'] ?? '')) ?></div>
                                        <div class="d-flex flex-wrap align-items-center gap-3">
                                            <span class="cart-price">RM <?= number_format((float)$item['Price'], 2) ?></span>
                                            <?php if ($stockQty > 0): ?>
                                                <span class="badge text-bg-success">Stock: <?= $stockQty ?></span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">Out of stock</span>
                                            <?php endif; ?>
                                            <span class="text-muted small">Line total: RM <?= number_format($lineTotal, 2) ?></span>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-auto">
                                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-md-end">
                                            <form method="post" class="d-flex gap-2 align-items-center">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="cart_id" value="<?= htmlspecialchars($item['CartId']) ?>">

                                                <input
                                                    type="number"
                                                    name="quantity"
                                                    class="form-control qty-control"
                                                    min="1"
                                                    max="<?= max(1, $stockQty) ?>"
                                                    value="<?= $qty ?>"
                                                    <?= $stockQty < 1 ? 'disabled' : '' ?>
                                                >
                                                <button type="submit" class="btn btn-cart-update" <?= $stockQty < 1 ? 'disabled' : '' ?>>Update</button>
                                            </form>

                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="cart_id" value="<?= htmlspecialchars($item['CartId']) ?>">
                                                <button type="submit" class="btn btn-cart-remove">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="summary-panel p-3 p-md-4 position-sticky" style="top: 94px;">
                    <h3 class="summary-title">Order Summary</h3>
                    <div class="summary-row">
                        <span>Items (<?= $totalItems ?>)</span>
                        <strong>RM <?= number_format($cartTotal, 2) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <strong><?= $shippingFee <= 0 ? 'Free' : 'RM ' . number_format($shippingFee, 2) ?></strong>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>RM <?= number_format($grandTotal, 2) ?></span>
                    </div>

                    <form method="POST" action="process_checkout.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" class="btn-checkout" <?= empty($cartItems) ? 'disabled' : '' ?>>Proceed to Checkout</button>
                    </form>

                    <?php if (!empty($cartItems)): ?>
                        <form method="post" onsubmit="return confirm('Clear all items from your cart?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn-clear">Clear Cart</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>

