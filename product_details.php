<?php

include_once 'config/config.php';

$productId = trim((string)($_GET['id'] ?? ''));
$alerts = [];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($productId === '') {
    header('Location: product.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $alerts[] = ['type' => 'danger', 'text' => 'Invalid request token. Please refresh and try again.'];
    } elseif ($action === 'add_to_cart') {
        if (!isset($_SESSION['user_id'])) {
            $returnTo = 'product_details.php?id=' . urlencode($productId);
            header('Location: member_login.php?return=' . urlencode($returnTo));
            exit;
        }

        $userId = $_SESSION['user_id'];
        $quantityInput = (int)($_POST['quantity'] ?? 1);
        $quantity = max(1, $quantityInput);

        try {
            $productStmt = $pdo->prepare("SELECT ProductId, ProductName, StockQuantity FROM Products WHERE ProductId = :product_id LIMIT 1");
            $productStmt->execute([':product_id' => $productId]);
            $cartProduct = $productStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cartProduct) {
                $alerts[] = ['type' => 'danger', 'text' => 'Product not found.'];
            } elseif ((int)$cartProduct['StockQuantity'] <= 0) {
                $alerts[] = ['type' => 'warning', 'text' => 'This product is out of stock.'];
            } else {
                $cartCheckStmt = $pdo->prepare("SELECT CartId, Quantity FROM Carts WHERE UserId = :user_id AND ProductId = :product_id LIMIT 1");
                $cartCheckStmt->execute([
                    ':user_id' => $userId,
                    ':product_id' => $productId,
                ]);
                $existing = $cartCheckStmt->fetch(PDO::FETCH_ASSOC);

                $currentQty = $existing ? (int)$existing['Quantity'] : 0;
                $newQty = $currentQty + $quantity;
                $stockQty = (int)$cartProduct['StockQuantity'];

                if ($newQty > $stockQty) {
                    $alerts[] = ['type' => 'warning', 'text' => 'Not enough stock. Available quantity: ' . $stockQty . '.'];
                } else {
                    if ($existing) {
                        $updateCartStmt = $pdo->prepare("UPDATE Carts SET Quantity = :quantity WHERE CartId = :cart_id");
                        $updateCartStmt->execute([
                            ':quantity' => $newQty,
                            ':cart_id' => $existing['CartId'],
                        ]);
                    } else {
                        $insertCartStmt = $pdo->prepare("INSERT INTO Carts (CartId, UserId, ProductId, Quantity) VALUES (UUID(), :user_id, :product_id, :quantity)");
                        $insertCartStmt->execute([
                            ':user_id' => $userId,
                            ':product_id' => $productId,
                            ':quantity' => $quantity,
                        ]);
                    }

                    $alerts[] = ['type' => 'success', 'text' => $cartProduct['ProductName'] . ' added to cart.'];
                }
            }
        } catch (Exception $e) {
            $alerts[] = ['type' => 'danger', 'text' => 'Failed to update cart: ' . $e->getMessage()];
        }
    }
}

$sql = "SELECT p.*,
        (SELECT ImageUrl
         FROM productimages
         WHERE ProductId = p.ProductId
         ORDER BY IsPrimary DESC, ImageId
         LIMIT 1) as MainImage
        FROM Products p
        WHERE ProductId = :product_id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':product_id' => $productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: product.php');
    exit;
}

$imagesStmt = $pdo->prepare("SELECT ImageUrl, IsPrimary FROM productimages WHERE ProductId = :product_id ORDER BY IsPrimary DESC, ImageId ASC");
$imagesStmt->execute([':product_id' => $productId]);
$galleryImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

$mainImageUrl = $product['MainImage'] ?: 'asset/image/no-image.jpg';
if (empty($galleryImages)) {
    $galleryImages = [
        ['ImageUrl' => $mainImageUrl, 'IsPrimary' => 1]
    ];
}

$stockQuantity = (int)($product['StockQuantity'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['ProductName'] ?? 'Product Details') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --pd-accent: #0f8f6f;
            --pd-accent-dark: #0b6f56;
            --pd-ink: #1d2b26;
            --pd-muted: #5f6f69;
            --pd-card-border: #dcebe6;
            --pd-soft-bg: #f3fbf8;
        }

        body {
            background: radial-gradient(1200px 450px at 5% 0%, #effbf6 0%, transparent 55%),
                        radial-gradient(800px 380px at 100% 20%, #edf5ff 0%, transparent 52%),
                        #f8fbfa;
        }

        .pd-shell {
            max-width: 1180px;
        }

        .pd-breadcrumb a {
            color: #2b6f5c;
            text-decoration: none;
        }

        .pd-breadcrumb a:hover {
            color: var(--pd-accent-dark);
        }

        .pd-panel {
            background: #fff;
            border: 1px solid var(--pd-card-border);
            border-radius: 20px;
            box-shadow: 0 16px 36px rgba(15, 48, 38, 0.08);
        }

        .pd-image-stage {
            background: linear-gradient(155deg, #f7fffc 0%, #f2f7ff 100%);
            border: 1px solid #d9ebe5;
            border-radius: 18px;
            overflow: hidden;
            min-height: 430px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 14px;
        }

        .pd-main-image {
            max-height: 400px;
            width: 100%;
            object-fit: contain;
            transition: transform 0.25s ease;
        }

        .pd-image-stage:hover .pd-main-image {
            transform: scale(1.02);
        }

        .pd-thumbs {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .pd-thumb-btn {
            border: 2px solid transparent;
            background: #fff;
            border-radius: 12px;
            width: 72px;
            height: 72px;
            flex: 0 0 auto;
            padding: 4px;
            transition: all 0.2s ease;
        }

        .pd-thumb-btn.active,
        .pd-thumb-btn:hover {
            border-color: var(--pd-accent);
            transform: translateY(-1px);
        }

        .pd-thumb-btn img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .pd-title {
            color: var(--pd-ink);
            font-weight: 800;
            line-height: 1.2;
        }

        .pd-price {
            font-size: 2rem;
            font-weight: 800;
            color: #0a6f57;
            margin: 0;
            letter-spacing: 0.01em;
        }

        .pd-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.36rem 0.7rem;
            border-radius: 999px;
        }

        .pd-chip.in-stock {
            background: #eaf8f1;
            color: #0e7a5f;
        }

        .pd-chip.out-stock {
            background: #fceaea;
            color: #b63838;
        }

        .pd-meta {
            color: var(--pd-muted);
            font-size: 0.95rem;
            margin-top: 0.75rem;
        }

        .pd-desc {
            background: var(--pd-soft-bg);
            border: 1px solid #dbece7;
            border-radius: 14px;
            padding: 14px;
            color: #2c3e37;
            line-height: 1.65;
            white-space: pre-line;
            margin-top: 1rem;
            min-height: 128px;
        }

        .pd-qty {
            max-width: 170px;
        }

        .pd-add-btn {
            background: linear-gradient(135deg, var(--pd-accent), var(--pd-accent-dark));
            border: none;
            border-radius: 12px;
            font-weight: 700;
            padding: 0.72rem 1.15rem;
            box-shadow: 0 10px 18px rgba(13, 115, 88, 0.26);
        }

        .pd-add-btn:disabled {
            opacity: 0.65;
            box-shadow: none;
        }

        @media (max-width: 767.98px) {
            .pd-price {
                font-size: 1.7rem;
            }

            .pd-image-stage {
                min-height: 300px;
            }
        }
    </style>
</head>
<body>
<?php include_once 'layout/nav.php'; ?>

<div class="container py-4 py-md-5 pd-shell">
    <?php if (!empty($alerts)): ?>
        <div class="mb-3">
            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= htmlspecialchars($alert['type']) ?> mb-2" role="alert">
                    <?= htmlspecialchars($alert['text']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    

    <div class="pd-panel p-3 p-md-4">
        <div class="pd-breadcrumb mb-3 small text-muted">
            <a href="index.php">Home</a>
            <span class="mx-2">/</span>
            <a href="product.php">Products</a>
            <span class="mx-2">/</span>
            <span><?= htmlspecialchars($product['ProductName'] ?? 'Product Details') ?></span>
        </div>
        <div class="row g-4 g-lg-5 align-items-start">
            <div class="col-lg-6">
                <div class="pd-image-stage">
                    <img
                        id="mainProductImage"
                        src="<?= htmlspecialchars($mainImageUrl) ?>"
                        class="pd-main-image"
                        alt="<?= htmlspecialchars($product['ProductName'] ?? 'Product image') ?>"
                    >
                </div>

                <div class="pd-thumbs" aria-label="Product image thumbnails">
                    <?php foreach ($galleryImages as $index => $image): ?>
                        <?php $thumbUrl = $image['ImageUrl'] ?: 'asset/image/no-image.jpg'; ?>
                        <button
                            type="button"
                            class="pd-thumb-btn <?= $index === 0 ? 'active' : '' ?>"
                            data-image="<?= htmlspecialchars($thumbUrl) ?>"
                            aria-label="View product image <?= $index + 1 ?>"
                        >
                            <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="Product thumbnail <?= $index + 1 ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <?php if ($stockQuantity > 0): ?>
                        <span class="pd-chip in-stock"><i class="fas fa-circle-check"></i> In Stock</span>
                    <?php else: ?>
                        <span class="pd-chip out-stock"><i class="fas fa-circle-xmark"></i> Out of Stock</span>
                    <?php endif; ?>
                </div>

                <h1 class="pd-title mb-3"><?= htmlspecialchars($product['ProductName'] ?? '') ?></h1>
                <p class="pd-price mb-1">RM <?= number_format((float)($product['Price'] ?? 0), 2) ?></p>
                <div class="pd-meta">Available Quantity: <strong><?= $stockQuantity ?></strong></div>

                <div class="pd-desc mt-3">
                    <?= htmlspecialchars($product['Description'] ?? 'No description available for this product yet.') ?>
                </div>

                <form method="post" class="d-flex flex-wrap align-items-end gap-3 mt-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="add_to_cart">

                    <div class="pd-qty">
                        <label for="qtyInput" class="form-label mb-1 fw-semibold">Quantity</label>
                        <input
                            id="qtyInput"
                            name="quantity"
                            type="number"
                            class="form-control"
                            min="1"
                            max="<?= max(1, $stockQuantity) ?>"
                            value="1"
                            <?= $stockQuantity < 1 ? 'disabled' : '' ?>
                        >
                    </div>

                    <button class="btn pd-add-btn text-white" type="submit" <?= $stockQuantity < 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-cart-plus me-1"></i> Add to Cart
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mainImage = document.getElementById('mainProductImage');
        const thumbButtons = document.querySelectorAll('.pd-thumb-btn');

        thumbButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const imageUrl = button.getAttribute('data-image');
                if (!mainImage || !imageUrl) {
                    return;
                }

                mainImage.src = imageUrl;
                thumbButtons.forEach(function (btn) {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
            });
        });
    });
</script>
</body>
</html>