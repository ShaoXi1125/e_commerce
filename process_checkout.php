<?php

include_once 'config/config.php';

function generateUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$userID = $_SESSION['user_id'] ?? null;

if ($userID === null) {
    header('Location: member_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$csrfToken)) {
    die('Invalid request token. Please refresh and try again.');
}

$action = trim((string)($_POST['action'] ?? ''));

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
$cartItemsStmt->execute([':user_id' => $userID]);
$cartItems = $cartItemsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

$addressStmt = $pdo->prepare("SELECT AddressId,
                                     RecipientName,
                                     PhoneNumber,
                                     AddressLine1,
                                     AddressLine2,
                                     States,
                                     City,
                                     Postcode,
                                     IsDefault
                              FROM Addresses
                              WHERE UserId = :user_id
                              ORDER BY IsDefault DESC, AddressId DESC");
$addressStmt->execute([':user_id' => $userID]);
$addresses = $addressStmt->fetchAll(PDO::FETCH_ASSOC);
$address = $addresses[0] ?? null;

$subtotal = 0.0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $qty = (int)$item['Quantity'];
    $subtotal += ((float)$item['Price']) * $qty;
    $totalItems += $qty;
}

$shippingFee = $subtotal >= 50 ? 0.00 : 8.00;
$serviceFee = 0.00;
$grandTotal = $subtotal + $shippingFee + $serviceFee;

if ($action === 'place_order') {
    $selectedAddressId = trim((string)($_POST['selected_address_id'] ?? ''));

    if ($selectedAddressId === '') {
        $_SESSION['checkout_error'] = 'Please select a delivery address before placing your order.';
        header('Location: cart.php');
        exit();
    }

    $selectedAddress = null;
    foreach ($addresses as $addr) {
        if ((string)$addr['AddressId'] === $selectedAddressId) {
            $selectedAddress = $addr;
            break;
        }
    }

    if (!$selectedAddress) {
        $_SESSION['checkout_error'] = 'Selected address is invalid. Please try again.';
        header('Location: cart.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        $lockedCartStmt = $pdo->prepare("SELECT c.CartId,
                                               c.Quantity,
                                               p.ProductId,
                                               p.ProductName,
                                               p.Price,
                                               p.StockQuantity
                                        FROM Carts c
                                        JOIN Products p ON p.ProductId = c.ProductId
                                        WHERE c.UserId = :user_id
                                        FOR UPDATE");
        $lockedCartStmt->execute([':user_id' => $userID]);
        $lockedItems = $lockedCartStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lockedItems)) {
            $pdo->rollBack();
            $_SESSION['checkout_error'] = 'Your cart is empty. Please add items before checkout.';
            header('Location: cart.php');
            exit();
        }

        $freshSubtotal = 0.0;
        foreach ($lockedItems as $item) {
            $qty = (int)$item['Quantity'];
            $stock = (int)$item['StockQuantity'];

            if ($qty > $stock) {
                $pdo->rollBack();
                $_SESSION['checkout_error'] = 'Not enough stock for ' . $item['ProductName'] . '. Please update your cart quantity.';
                header('Location: cart.php');
                exit();
            }

            $freshSubtotal += ((float)$item['Price']) * $qty;
        }

        $freshShippingFee = $freshSubtotal >= 50 ? 0.00 : 8.00;
        $freshGrandTotal = $freshSubtotal + $freshShippingFee;

        $shippingAddress = implode(', ', array_filter([
            trim((string)($selectedAddress['RecipientName'] ?? '')),
            trim((string)($selectedAddress['PhoneNumber'] ?? '')),
            trim((string)($selectedAddress['AddressLine1'] ?? '')),
            trim((string)($selectedAddress['AddressLine2'] ?? '')),
            trim((string)($selectedAddress['City'] ?? '')),
            trim((string)($selectedAddress['States'] ?? '')),
            trim((string)($selectedAddress['Postcode'] ?? '')),
        ]));

        $orderId = generateUuidV4();
        $insertOrderStmt = $pdo->prepare("INSERT INTO Orders (OrderId, UserId, AddressId, TotalAmount, OrderStatus, ShippingAddress)
                                          VALUES (:order_id, :user_id, :address_id, :total_amount, :order_status, :shipping_address)");
        $insertOrderStmt->execute([
            ':order_id' => $orderId,
            ':user_id' => $userID,
            ':address_id' => $selectedAddressId,
            ':total_amount' => $freshGrandTotal,
            ':order_status' => 'Pending',
            ':shipping_address' => $shippingAddress,
        ]);

        $insertOrderItemStmt = $pdo->prepare("INSERT INTO OrderItems (OrderItemId, OrderId, ProductId, Quantity, UnitPrice)
                                              VALUES (:order_item_id, :order_id, :product_id, :quantity, :unit_price)");
        $updateStockStmt = $pdo->prepare("UPDATE Products
                                          SET StockQuantity = StockQuantity - :quantity
                                          WHERE ProductId = :product_id");

        foreach ($lockedItems as $item) {
            $insertOrderItemStmt->execute([
                ':order_item_id' => generateUuidV4(),
                ':order_id' => $orderId,
                ':product_id' => $item['ProductId'],
                ':quantity' => (int)$item['Quantity'],
                ':unit_price' => (float)$item['Price'],
            ]);

            $updateStockStmt->execute([
                ':quantity' => (int)$item['Quantity'],
                ':product_id' => $item['ProductId'],
            ]);
        }

        $clearCartStmt = $pdo->prepare("DELETE FROM Carts WHERE UserId = :user_id");
        $clearCartStmt->execute([':user_id' => $userID]);

        $pdo->commit();

        $_SESSION['checkout_success'] = 'Order placed successfully. Your order number is ' . $orderId . '.';
        header('Location: cart.php');
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['checkout_error'] = 'Unable to place order right now. Please try again.';
        header('Location: cart.php');
        exit();
    }
}

$addressParts = [];
if ($address) {
    $addressParts = array_filter([
        trim((string)($address['AddressLine1'] ?? '')),
        trim((string)($address['AddressLine2'] ?? '')),
        trim((string)($address['City'] ?? '')),
        trim((string)($address['States'] ?? '')),
        trim((string)($address['Postcode'] ?? '')),
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce | Checkout</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --checkout-bg: #f6f8fc;
            --checkout-ink: #1f2937;
            --checkout-muted: #6b7280;
            --checkout-line: #e4e8f0;
            --checkout-brand: #ee4d2d;
            --checkout-brand-deep: #d93f21;
            --checkout-soft: #fff1ec;
            --checkout-green: #0f8f6f;
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: var(--checkout-ink);
            background:
                radial-gradient(1050px 420px at 0% 0%, rgba(238, 77, 45, 0.1), transparent 52%),
                radial-gradient(900px 380px at 100% 18%, rgba(15, 143, 111, 0.08), transparent 52%),
                var(--checkout-bg);
        }

        .checkout-wrap {
            max-width: 1240px;
        }

        .checkout-hero {
            border: 1px solid rgba(238, 77, 45, 0.2);
            border-radius: 22px;
            background: linear-gradient(135deg, #ffffff 0%, #fffaf8 100%);
            box-shadow: 0 18px 34px rgba(16, 24, 40, 0.08);
        }

        .checkout-kicker {
            color: var(--checkout-brand);
            font-weight: 800;
            letter-spacing: 0.06em;
            font-size: 0.78rem;
            text-transform: uppercase;
        }

        .checkout-title {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
            font-size: clamp(1.55rem, 2.9vw, 2.3rem);
        }

        .checkout-sub {
            color: var(--checkout-muted);
            margin: 0;
        }

        .checkout-card,
        .summary-card {
            border: 1px solid var(--checkout-line);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 14px 28px rgba(16, 24, 40, 0.07);
        }

        .section-tag {
            color: var(--checkout-brand);
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .address-box,
        .item-box,
        .method-box {
            border: 1px solid var(--checkout-line);
            border-radius: 14px;
            background: #fff;
        }

        .address-list {
            display: grid;
            gap: 0.8rem;
        }

        .address-option {
            border: 1px solid var(--checkout-line);
            border-radius: 12px;
            padding: 0.75rem;
            background: #fff;
            cursor: pointer;
        }

        .address-option.active {
            border-color: rgba(238, 77, 45, 0.5);
            box-shadow: 0 8px 18px rgba(238, 77, 45, 0.12);
            background: #fffaf8;
        }

        .address-option .form-check-input {
            margin-top: 0.2rem;
        }

        .address-icon {
            width: 44px;
            height: 44px;
            border-radius: 999px;
            background: var(--checkout-soft);
            color: var(--checkout-brand);
            display: grid;
            place-items: center;
            flex: 0 0 auto;
        }

        .item-image {
            width: 84px;
            height: 84px;
            border-radius: 12px;
            border: 1px solid #edf0f5;
            object-fit: cover;
            background: #f2f4f8;
        }

        .item-name {
            font-weight: 700;
            margin-bottom: 3px;
        }

        .item-desc {
            color: var(--checkout-muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .price {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            color: var(--checkout-brand-deep);
        }

        .summary-sticky {
            position: sticky;
            top: 94px;
        }

        .summary-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            color: #374151;
            font-size: 0.96rem;
        }

        .summary-row.total {
            border-top: 1px dashed #cfd7e5;
            padding-top: 0.95rem;
            margin-top: 0.95rem;
            font-size: 1.1rem;
            font-weight: 800;
            color: #111827;
        }

        .btn-place {
            width: 100%;
            border: 0;
            border-radius: 12px;
            padding: 0.82rem 1rem;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--checkout-brand), var(--checkout-brand-deep));
            box-shadow: 0 12px 24px rgba(238, 77, 45, 0.24);
        }

        .btn-place:disabled {
            opacity: 0.75;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-back {
            width: 100%;
            margin-top: 0.6rem;
            border: 1px solid #d4d9e3;
            border-radius: 12px;
            padding: 0.76rem 1rem;
            background: #fff;
            color: #374151;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .badge-green {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--checkout-green);
            background: #e8faf3;
            border-radius: 999px;
            padding: 0.42rem 0.72rem;
        }

        @media (max-width: 991px) {
            .summary-sticky {
                position: static;
            }
        }
    </style>
</head>
<body>
    <?php include 'layout/nav.php'; ?>

    <div class="container checkout-wrap py-4 py-md-5">
        <?php if (!empty($_SESSION['checkout_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string)$_SESSION['checkout_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['checkout_error']); ?>
        <?php endif; ?>

        <section class="checkout-hero p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-end">
                <div>
                    <div class="checkout-kicker">Checkout</div>
                    <h1 class="checkout-title mt-1">Confirm your order details</h1>
                </div>
                <div class="text-md-end">
                    <div class="small text-muted">Items Selected</div>
                    <div class="fw-bold fs-5"><?= $totalItems ?></div>
                </div>
            </div>
        </section>

        <form method="POST" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="place_order">

            <div class="col-lg-8">
                <div class="checkout-card p-3 p-md-4 mb-4">
                    <div class="section-tag">Delivery Address</div>
                    <div class="address-box p-3">
                        <?php if (!empty($addresses)): ?>
                            <div class="address-list">
                                <?php foreach ($addresses as $index => $addr): ?>
                                    <?php
                                        $parts = array_filter([
                                            trim((string)($addr['AddressLine1'] ?? '')),
                                            trim((string)($addr['AddressLine2'] ?? '')),
                                            trim((string)($addr['City'] ?? '')),
                                            trim((string)($addr['States'] ?? '')),
                                            trim((string)($addr['Postcode'] ?? '')),
                                        ]);
                                        $isSelected = $index === 0;
                                    ?>
                                    <label class="address-option <?= $isSelected ? 'active' : '' ?>">
                                        <div class="d-flex gap-3 align-items-start">
                                            <input
                                                type="radio"
                                                name="selected_address_id"
                                                class="form-check-input address-radio"
                                                value="<?= htmlspecialchars((string)$addr['AddressId']) ?>"
                                                <?= $isSelected ? 'checked' : '' ?>
                                            >
                                            <div class="flex-grow-1">
                                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                    <span class="fw-bold"><?= htmlspecialchars((string)$addr['RecipientName']) ?></span>
                                                    <span class="text-muted">· <?= htmlspecialchars((string)$addr['PhoneNumber']) ?></span>
                                                    <?php if ((int)($addr['IsDefault'] ?? 0) === 1): ?>
                                                        <span class="badge text-bg-success">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small"><?= htmlspecialchars(implode(', ', $parts)) ?></div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="d-flex gap-3 align-items-start">
                                <div class="address-icon"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <div class="fw-bold mb-1">No address available</div>
                                    <div class="text-muted">Please add your address in profile before placing order.</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="checkout-card p-3 p-md-4 mb-4">
                    <div class="section-tag">Payment Method</div>
                    <div class="method-box p-3">
                        <div class="d-flex justify-content-between gap-3 align-items-start">
                            <div>
                                <div class="fw-bold">Online Banking / Cash on Delivery</div>
                                <div class="text-muted small">Your order will be created immediately after you click Place Order.</div>
                            </div>
                            <span class="badge text-bg-success">Enabled</span>
                        </div>
                    </div>
                </div>

                <div class="checkout-card p-3 p-md-4">
                    <div class="section-tag">Order Items</div>
                    <?php foreach ($cartItems as $item): ?>
                        <?php
                            $qty = (int)$item['Quantity'];
                            $lineTotal = ((float)$item['Price']) * $qty;
                        ?>
                        <div class="item-box p-3 mb-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto">
                                    <img
                                        src="<?= htmlspecialchars($item['MainImage'] ?: 'asset/image/no-image.jpg') ?>"
                                        alt="<?= htmlspecialchars($item['ProductName']) ?>"
                                        class="item-image"
                                    >
                                </div>
                                <div class="col">
                                    <div class="item-name"><?= htmlspecialchars($item['ProductName']) ?></div>
                                    <div class="item-desc"><?= htmlspecialchars((string)($item['Description'] ?? '')) ?></div>
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        <span class="badge text-bg-light border">Qty: <?= $qty ?></span>
                                        <span class="badge text-bg-light border">Stock: <?= (int)$item['StockQuantity'] ?></span>
                                    </div>
                                </div>
                                <div class="col-12 col-md-auto text-md-end">
                                    <div class="price fs-5">RM <?= number_format($lineTotal, 2) ?></div>
                                    <div class="text-muted small">RM <?= number_format((float)$item['Price'], 2) ?> each</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="summary-card p-3 p-md-4 summary-sticky">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="summary-title mb-0">Order Summary</h3>
                        <span class="badge-green"><i class="fas fa-shield-halved"></i>Secure</span>
                    </div>

                    <div class="summary-row">
                        <span>Items (<?= $totalItems ?>)</span>
                        <strong>RM <?= number_format($subtotal, 2) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <strong><?= $shippingFee <= 0 ? 'Free' : 'RM ' . number_format($shippingFee, 2) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Service Fee</span>
                        <strong>RM <?= number_format($serviceFee, 2) ?></strong>
                    </div>
                    <div class="summary-row total">
                        <span>Total Payment</span>
                        <span>RM <?= number_format($grandTotal, 2) ?></span>
                    </div>

                    <button type="submit" class="btn-place mt-3" <?= empty($addresses) ? 'disabled' : '' ?>>Place Order</button>
                    <a href="cart.php" class="btn-back">Back to Cart</a>
                </div>
            </div>
        </form>
    </div>
    <script>
        (function () {
            const radios = document.querySelectorAll('.address-radio');
            const options = document.querySelectorAll('.address-option');

            if (!radios.length) {
                return;
            }

            function syncActiveState() {
                options.forEach((option) => option.classList.remove('active'));

                radios.forEach((radio) => {
                    if (radio.checked) {
                        const parent = radio.closest('.address-option');
                        if (parent) {
                            parent.classList.add('active');
                        }
                    }
                });
            }

            radios.forEach((radio) => {
                radio.addEventListener('change', syncActiveState);
            });

            syncActiveState();
        })();
    </script>
</body>
</html>