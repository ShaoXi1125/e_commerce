<?php
require_once 'config/config.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$alerts = [];
$searchQuery = trim($_GET['q'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $alerts[] = ['type' => 'danger', 'text' => 'Invalid request. Please refresh and try again.'];
    } elseif ($action === 'add_to_cart') {
        if ($userId === null) {
            $alerts[] = ['type' => 'warning', 'text' => 'Please login to add items to cart.'];
        } else {
            $productId = $_POST['product_id'] ?? '';
            $quantityInput = (int)($_POST['quantity'] ?? 1);
            $quantity = max(1, $quantityInput);

            try {
                $productStmt = $pdo->prepare("SELECT ProductId, ProductName, StockQuantity FROM Products WHERE ProductId = :product_id LIMIT 1");
                $productStmt->execute([':product_id' => $productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    $alerts[] = ['type' => 'danger', 'text' => 'Product not found.'];
                } elseif ((int)$product['StockQuantity'] <= 0) {
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
                    $stockQty = (int)$product['StockQuantity'];

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

                        $alerts[] = ['type' => 'success', 'text' => $product['ProductName'] . ' added to cart.'];
                    }
                }
            } catch (Exception $e) {
                $alerts[] = ['type' => 'danger', 'text' => 'Failed to update cart: ' . $e->getMessage()];
            }
        }
    }
}

$productSQL = "SELECT ProductId, ProductName, Description, Price, StockQuantity FROM Products";
$productParams = [];

if ($searchQuery !== '') {
    $productSQL .= " WHERE ProductName LIKE :q_name OR Description LIKE :q_desc";
    $keyword = '%' . $searchQuery . '%';
    $productParams[':q_name'] = $keyword;
    $productParams[':q_desc'] = $keyword;
}

$productSQL .= " ORDER BY CreateDate DESC LIMIT 18";
$productStmt = $pdo->prepare($productSQL);
foreach ($productParams as $key => $value) {
    $productStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$productStmt->execute();
$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
try {
    $categoryLimit = random_int(4, 5);
    $categoryStmt = $pdo->prepare("SELECT c.CategoryId, c.CategoryName, c.CategoryIcon, COUNT(p.ProductId) AS ProductCount
                                   FROM category c
                                   LEFT JOIN Products p ON p.CategoryId = c.CategoryId
                                   GROUP BY c.CategoryId, c.CategoryName, c.CategoryIcon
                                   ORDER BY RAND()
                                   LIMIT :limit");
    $categoryStmt->bindValue(':limit', $categoryLimit, PDO::PARAM_INT);
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

$cartSummary = ['items' => 0, 'total' => 0.00];
if ($userId !== null) {
    $cartSummaryStmt = $pdo->prepare("SELECT COALESCE(SUM(c.Quantity), 0) AS total_items,
                                             COALESCE(SUM(c.Quantity * p.Price), 0) AS total_amount
                                      FROM Carts c
                                      JOIN Products p ON c.ProductId = p.ProductId
                                      WHERE c.UserId = :user_id");
    $cartSummaryStmt->execute([':user_id' => $userId]);
    $cartData = $cartSummaryStmt->fetch(PDO::FETCH_ASSOC);

    if ($cartData) {
        $cartSummary['items'] = (int)$cartData['total_items'];
        $cartSummary['total'] = (float)$cartData['total_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fbf9; color: #1b2530; }

        /* ── Hero ── */
        .hero {
            background:
                radial-gradient(circle at 8% 30%, rgba(15,143,111,.22), transparent 42%),
                radial-gradient(circle at 92% 70%, rgba(39,124,198,.18), transparent 36%),
                linear-gradient(135deg, #f0faf5, #e6f4ff);
            padding: 90px 0 80px;
        }
        .hero-pill {
            display: inline-block;
            background: rgba(15,143,111,.12);
            color: #0b6f56;
            border: 1px solid rgba(15,143,111,.25);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: .07em;
            text-transform: uppercase;
            padding: 6px 14px;
            margin-bottom: 18px;
        }
        .hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.4rem, 5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.06;
            letter-spacing: -.025em;
            max-width: 14ch;
        }
        .hero h1 span { color: #0f8f6f; }
        .hero p.lead { max-width: 42ch; opacity: .82; font-size: 1.05rem; line-height: 1.6; }
        .btn-primary-custom {
            background: linear-gradient(135deg,#0f8f6f,#0b6f56);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            padding: 13px 28px;
            font-size: 15px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(11,111,86,.30);
            color: #fff;
        }
        .btn-outline-custom {
            border: 1.5px solid rgba(27,37,48,.22);
            border-radius: 12px;
            color: #1b2530;
            font-weight: 600;
            padding: 12px 28px;
            font-size: 15px;
            background: rgba(255,255,255,.7);
            transition: border-color .15s ease, background .15s ease;
        }
        .btn-outline-custom:hover {
            border-color: #0f8f6f;
            color: #0b6f56;
            background: rgba(15,143,111,.06);
        }
        .hero-img-wrap {
            position: relative;
        }
        .hero-img-wrap::before {
            content: '';
            position: absolute;
            inset: -16px;
            border-radius: 28px;
            background: rgba(15,143,111,.10);
            transform: rotate(-2deg);
            z-index: 0;
        }
        .hero-img-wrap img { position: relative; z-index: 1; border-radius: 22px; }

        /* ── Stats strip ── */
        .stats-strip {
            background: #fff;
            border-top: 1px solid #e2ede9;
            border-bottom: 1px solid #e2ede9;
        }
        .stat-item { padding: 22px 0; }
        .stat-item .num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f8f6f;
            line-height: 1;
        }
        .stat-item .lbl { font-size: 13px; color: #6b7c8d; margin-top: 4px; }

        /* ── Section headings ── */
        .section-label {
            display: inline-block;
            background: rgba(15,143,111,.1);
            color: #0b6f56;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            padding: 5px 12px;
            margin-bottom: 10px;
        }
        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700;
            letter-spacing: -.018em;
            line-height: 1.1;
        }

        /* ── Feature cards ── */
        .feature-card {
            background: #fff;
            border: 1px solid #e2ede9;
            border-radius: 18px;
            padding: 28px 24px;
            height: 100%;
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .feature-card:hover { box-shadow: 0 12px 32px rgba(10,36,60,.09); transform: translateY(-3px); }
        .feature-icon {
            width: 50px; height: 50px;
            border-radius: 14px;
            background: rgba(15,143,111,.12);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: #0f8f6f;
            margin-bottom: 16px;
        }
        .feature-card h5 {
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 8px;
        }
        .feature-card p { font-size: 14px; color: #6b7c8d; margin: 0; line-height: 1.55; }

        /* ── Category cards ── */
        .category-card {
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            height: 180px;
            background: linear-gradient(135deg, #e3f5ef, #d4ede5);
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
            display: flex; align-items: flex-end;
        }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 14px 28px rgba(10,36,60,.12); }
        .category-card .cc-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
        }
        .category-card .cc-inner {
            padding: 18px 20px;
            background: linear-gradient(to top, rgba(11,111,86,.5), transparent);
            width: 100%;
            z-index: 2;
        }
        .category-card .cc-inner strong { color: #fff; font-size: 1rem; font-weight: 700; display: block; }
        .category-card .cc-inner span { color: rgba(255,255,255,.8); font-size: 13px; }
        .category-card .cc-emoji {
            position: absolute;
            top: 16px; right: 20px;
            font-size: 2.4rem;
            opacity: .75;
            z-index: 1;
        }

        /* ── CTA banner ── */
        .cta-banner {
            background: linear-gradient(135deg, #0f8f6f, #0b6f56);
            border-radius: 24px;
            padding: 52px 48px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::before {
            content: '';
            position: absolute;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            top: -80px; right: -60px;
        }
        .cta-banner::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            bottom: -60px; left: 30px;
        }
        .cta-banner h2 { font-family:'Space Grotesk',sans-serif; font-weight:800; font-size: clamp(1.6rem,3vw,2.2rem); letter-spacing:-.02em; margin-bottom:10px; }
        .cta-banner p { opacity:.88; font-size:15px; max-width:44ch; margin-bottom:0; }
        .btn-cta-white {
            background: #fff;
            color: #0b6f56;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            padding: 13px 28px;
            font-size: 15px;
            transition: transform .15s ease, box-shadow .15s ease;
            white-space: nowrap;
        }
        .btn-cta-white:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,.18); color: #0b6f56; }

        /* ── Footer ── */
        footer { background: #1b2530; color: rgba(255,255,255,.7); font-size: 14px; }
        footer a { color: rgba(255,255,255,.6); text-decoration: none; }
        footer a:hover { color: #fff; }
        .footer-brand { font-family: 'Space Grotesk',sans-serif; font-weight: 700; font-size: 1.2rem; color: #fff; }
        .footer-divider { border-color: rgba(255,255,255,.1); }

        /* -- Cart + products -- */
        .cart-summary-card {
            border: 1px solid #dceee6;
            border-radius: 18px;
            background: linear-gradient(140deg, #ffffff 0%, #f5fbf8 100%);
            box-shadow: 0 12px 28px rgba(10, 36, 60, 0.06);
        }
        .cart-summary-num {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f8f6f;
            line-height: 1;
        }
        .product-card {
            border: 1px solid #e2ede9;
            border-radius: 18px;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(10, 36, 60, 0.06);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .product-card-top {
            background: linear-gradient(135deg, #eef8f4, #e8f3ff);
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
        }
        .product-card .card-body {
            display: flex;
            flex-direction: column;
        }
        .product-name {
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 1.02rem;
        }
        .product-desc {
            color: #6b7c8d;
            font-size: 13px;
            line-height: 1.5;
            min-height: 40px;
            margin-bottom: 10px;
        }
        .price-tag {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            color: #0b6f56;
        }
    </style>
</head>
<body>
<?php include 'layout/nav.php'; ?>

<!-- ════════════════════ HERO ════════════════════ -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-pill">✦ New arrivals every week</span>
                <h1>Shop the things you <span>love,</span> delivered fast.</h1>
                <p class="lead mt-3 mb-4">Discover thousands of products at unbeatable prices. Free shipping on orders over RM 50.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#categories" class="btn btn-primary-custom">
                        <i class="fas fa-shopping-bag me-2"></i>Shop Now
                    </a>
                    <a href="#features" class="btn btn-outline-custom">Learn More</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-img-wrap text-center">
                    <div style="font-size:11rem;line-height:1;filter:drop-shadow(0 18px 32px rgba(11,111,86,.20));">🛍️</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ STATS ════════════════════ -->
<div class="stats-strip">
    <div class="container">
        <div class="row text-center g-0">
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">10K+</div>
                <div class="lbl">Products</div>
            </div>
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">50K+</div>
                <div class="lbl">Happy Customers</div>
            </div>
            <div class="col-6 col-md-3 stat-item border-end">
                <div class="num">99%</div>
                <div class="lbl">Satisfaction Rate</div>
            </div>
            <div class="col-6 col-md-3 stat-item">
                <div class="num">24/7</div>
                <div class="lbl">Customer Support</div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════ PRODUCTS + CART ════════════════════ -->
<section class="py-5" id="products">
    <div class="container py-2">
        <?php if (!empty($alerts)): ?>
            <div class="mb-4">
                <?php foreach ($alerts as $alert): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($alert['type']); ?> mb-2" role="alert">
                        <?php echo htmlspecialchars($alert['text']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="cart-summary-card p-4 mb-4" id="cart-summary">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <span class="section-label">Your cart</span>
                    <h3 class="section-title mt-1 mb-2" style="font-size:1.6rem;">Keep shopping, your cart updates instantly</h3>
                    <p class="mb-0 text-secondary" style="font-size:14px;">
                        <?php if ($userId !== null): ?>
                            Add products from this page and we will save them to your account cart.
                        <?php else: ?>
                            Login to start adding items to your cart.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-md-end gap-4 text-center text-md-start">
                        <div>
                            <div class="cart-summary-num"><?php echo $cartSummary['items']; ?></div>
                            <div class="text-muted" style="font-size:13px;">Items in cart</div>
                        </div>
                        <div>
                            <div class="cart-summary-num">RM <?php echo number_format($cartSummary['total'], 2); ?></div>
                            <div class="text-muted" style="font-size:13px;">Cart total</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-label">Products</span>
                <h2 class="section-title mt-1 mb-0">Find your next favorite item</h2>
            </div>
            <?php if ($searchQuery !== ''): ?>
                <span class="badge text-bg-light border">Search: <?php echo htmlspecialchars($searchQuery); ?></span>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (empty($products)): ?>
                <div class="col-12">
                    <div class="alert alert-warning mb-0">No products found<?php echo $searchQuery !== '' ? ' for "' . htmlspecialchars($searchQuery) . '"' : ''; ?>.</div>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="product-card">
                            <div class="product-card-top">📦</div>
                            <div class="card-body p-3 p-lg-4">
                                <div class="product-name"><?php echo htmlspecialchars($product['ProductName']); ?></div>
                                <div class="product-desc"><?php echo htmlspecialchars($product['Description'] ?? ''); ?></div>

                                <div class="d-flex justify-content-between align-items-center mt-auto mb-3">
                                    <span class="price-tag">RM <?php echo number_format((float)$product['Price'], 2); ?></span>
                                    <?php if ((int)$product['StockQuantity'] > 0): ?>
                                        <span class="badge text-bg-success">Stock: <?php echo (int)$product['StockQuantity']; ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-danger">Out of stock</span>
                                    <?php endif; ?>
                                </div>

                                <form method="post" action="index.php<?php echo $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : ''; ?>" class="d-flex gap-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductId']); ?>">

                                    <input
                                        type="number"
                                        name="quantity"
                                        class="form-control"
                                        min="1"
                                        max="<?php echo max(1, (int)$product['StockQuantity']); ?>"
                                        value="1"
                                        <?php echo (int)$product['StockQuantity'] <= 0 ? 'disabled' : ''; ?>
                                    >

                                    <button class="btn btn-primary-custom flex-shrink-0" type="submit" <?php echo (int)$product['StockQuantity'] <= 0 ? 'disabled' : ''; ?>>
                                        Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ════════════════════ FEATURES ════════════════════ -->
<section class="py-5 mt-2" id="features">
    <div class="container py-3">
        <div class="text-center mb-5">
            <span class="section-label">Why choose us</span>
            <h2 class="section-title mt-1">Shopping made <em>effortless</em></h2>
        </div>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
                    <h5>Free Shipping</h5>
                    <p>Orders above RM 50 ship completely free to your doorstep.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h5>Secure Payments</h5>
                    <p>Your data and transactions are protected end-to-end.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-undo-alt"></i></div>
                    <h5>Easy Returns</h5>
                    <p>Changed your mind? Return within 30 days, no questions asked.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h5>24/7 Support</h5>
                    <p>Our team is always here to help you with any query.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ CATEGORIES ════════════════════ -->
<section class="py-5 bg-white" id="categories">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="section-label">Browse</span>
                <h2 class="section-title mt-1 mb-0">Shop by category</h2>
            </div>
            <a href="#" class="text-decoration-none text-success fw-semibold" style="font-size:14px;">View all <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-3">
            <?php if (empty($categories)): ?>
                <div class="col-12">
                    <div class="alert alert-light border mb-0">No categories available yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryId = htmlspecialchars($category['CategoryId']);
                    $categoryName = trim((string)($category['CategoryName'] ?? 'Category'));
                    $productCount = (int)($category['ProductCount'] ?? 0);
                    $safeIconName = basename((string)($category['CategoryIcon'] ?? ''));
                    $iconPath = $safeIconName !== '' ? 'asset/category_icons/' . $safeIconName : '';
                    $iconUrl = ($iconPath !== '' && is_file(__DIR__ . '/' . $iconPath)) ? $iconPath : '';
                    $bgThemes = [
                        ['bg' => 'linear-gradient(135deg,#fef3e2,#fde8c8)', 'overlay' => 'linear-gradient(to top,rgba(180,90,10,.5),transparent)'],
                        ['bg' => 'linear-gradient(135deg,#eaf4ff,#dce9ff)', 'overlay' => 'linear-gradient(to top,rgba(36,84,160,.5),transparent)'],
                        ['bg' => 'linear-gradient(135deg,#e9f9f0,#d6f1e2)', 'overlay' => 'linear-gradient(to top,rgba(20,122,82,.5),transparent)'],
                        ['bg' => 'linear-gradient(135deg,#fff1f0,#ffe0dc)', 'overlay' => 'linear-gradient(to top,rgba(166,58,43,.5),transparent)'],
                        ['bg' => 'linear-gradient(135deg,#f6f0ff,#e9ddff)', 'overlay' => 'linear-gradient(to top,rgba(98,64,167,.5),transparent)'],
                    ];
                    $theme = $bgThemes[array_rand($bgThemes)];
                    $itemsText = number_format($productCount) . '+ items';
                    ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="product.php?category=<?php echo urlencode($categoryId); ?>" class="category-card text-decoration-none" style="background:<?php echo htmlspecialchars($theme['bg']); ?>;">
                            <div class="cc-overlay" style="background:<?php echo htmlspecialchars($theme['overlay']); ?>;"></div>
                            <span class="cc-emoji" aria-hidden="true">
                                <?php if ($iconUrl !== ''): ?>
                                    <img
                                        src="<?php echo htmlspecialchars($iconUrl); ?>"
                                        alt=""
                                        style="width:38px;height:38px;object-fit:cover;box-shadow:0 4px 10px rgba(0,0,0,.15);"
                                    >
                                <?php else: ?>
                                    <i class="fas fa-tag"></i>
                                <?php endif; ?>
                            </span>
                            <div class="cc-inner" style="background:<?php echo htmlspecialchars($theme['overlay']); ?>;">
                                <strong><?php echo htmlspecialchars($categoryName); ?></strong>
                                <span><?php echo htmlspecialchars($itemsText); ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ════════════════════ CTA BANNER ════════════════════ -->
<section class="py-5">
    <div class="container py-2">
        <div class="cta-banner d-flex flex-column flex-md-row align-items-center justify-content-between gap-4">
            <div style="position:relative;z-index:1;">
                <h2 class="mb-2">Ready to start shopping?</h2>
                <p>Create a free account today and unlock exclusive member deals.</p>
            </div>
            <div class="d-flex gap-3 flex-shrink-0" style="position:relative;z-index:1;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="member_register.php" class="btn btn-cta-white">
                        Create Account <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php else: ?>
                    <a href="#categories" class="btn btn-cta-white">
                        Browse Products <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ FOOTER ════════════════════ -->
<footer class="py-5 mt-3">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="footer-brand mb-2">🛍 E-commerce</div>
                <p style="font-size:13.5px;max-width:32ch;line-height:1.6;">Your one-stop shop for quality products at great prices, delivered right to your door.</p>
            </div>
            <div class="col-6 col-md-2">
                <div class="fw-semibold text-white mb-3" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;">Shop</div>
                <ul class="list-unstyled" style="font-size:13.5px;line-height:2;">
                    <li><a href="#">New Arrivals</a></li>
                    <li><a href="#">Best Sellers</a></li>
                    <li><a href="#">On Sale</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-2">
                <div class="fw-semibold text-white mb-3" style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;">
                    Account
                </div>
                <ul class="list-unstyled" style="font-size:13.5px;line-height:2;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="userProfile.php">My Profile</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="member_login.php">Login</a></li>
                        <li><a href="member_register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size:12.5px;">
            <span>&copy; <?php echo date('Y'); ?> E-commerce. All rights reserved.</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>