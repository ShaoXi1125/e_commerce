<?php

include_once 'config/config.php';

$search = trim((string)($_GET['productName'] ?? $_GET['search'] ?? ''));
$filter = trim((string)($_GET['category'] ?? ''));

$params = [];
$whereClauses = [];

if($search !==''){
    $whereClauses[]="(ProductName LIKE :search_name OR Description LIKE :search_desc)";
    $params[':search_name'] = "%$search%";
    $params[':search_desc'] = "%$search%";
}
//SELECT * FROM products WHERE name LIKE '%search%'

if($filter !==""){
    $whereClauses[]="CategoryId = :cat_id";
    $params[':cat_id'] = $filter;
}
//SELECT * FROM products WHERE CategoryID = :cat_id;

$products = [];

if (!empty($whereClauses)) {
    $whereSQL = implode(' AND ', $whereClauses);

    $sql = "SELECT p.*,
            (SELECT ImageUrl FROM productimages WHERE ProductId = p.ProductId ORDER BY IsPrimary DESC, ProductId LIMIT 1) 
            as MainImage FROM Products p WHERE $whereSQL ORDER BY p.CreateDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

$categories = $pdo->query("SELECT * FROM category")->fetchAll();

$selectedCategoryName = 'All Categories';
foreach ($categories as $categoryItem) {
    if ((string)$categoryItem['CategoryId'] === $filter) {
        $selectedCategoryName = $categoryItem['CategoryName'];
        break;
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce || Product Search</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        :root {
            --page-ink: #18221f;
            --page-muted: #667a73;
            --surface: #ffffff;
            --surface-soft: #f3fbf7;
            --line: #dbece5;
            --accent: #0f8f6f;
            --accent-dark: #0a6f56;
            --accent-soft: #e8f7f1;
            --warm: #f5f8ff;
            --danger-soft: #fbecec;
            --shadow: 0 16px 32px rgba(11, 51, 39, 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--page-ink);
            background: radial-gradient(1300px 500px at 0% 0%, #eefbf5 0%, transparent 55%),
                        radial-gradient(950px 450px at 100% 20%, #edf4ff 0%, transparent 54%),
                        #f8fcfa;
        }

        .product-shell {
            max-width: 1240px;
        }

        .result-hero {
            border: 1px solid var(--line);
            background: linear-gradient(145deg, #ffffff 0%, #f7fcfa 65%, #f2f7ff 100%);
            border-radius: 20px;
            box-shadow: var(--shadow);
        }

        .result-title {
            font-family: 'DM Serif Display', serif;
            font-size: 2.05rem;
            line-height: 1.1;
            margin: 0;
        }

        .result-sub {
            color: var(--page-muted);
            margin: 0;
            font-size: 0.95rem;
        }

        .active-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            border: 1px solid transparent;
        }

        .active-pill.search {
            background: var(--accent-soft);
            color: var(--accent-dark);
            border-color: #cae8dc;
        }

        .active-pill.category {
            background: var(--warm);
            color: #3254a0;
            border-color: #d7e3fd;
        }

        .sidebar-card,
        .results-card {
            border: 1px solid var(--line);
            background: var(--surface);
            border-radius: 18px;
            box-shadow: 0 12px 24px rgba(14, 56, 43, 0.08);
        }

        .filter-title {
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .category-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            text-decoration: none;
            color: #204036;
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 0.6rem 0.72rem;
            transition: all 0.18s ease;
        }

        .category-link:hover {
            border-color: #cae8dc;
            background: #f1faf6;
            color: var(--accent-dark);
        }

        .category-link.active {
            border-color: #b4dfce;
            background: linear-gradient(130deg, #effaf5 0%, #e8f4ff 100%);
            color: #0f6e57;
            font-weight: 700;
        }

        .result-meta {
            color: var(--page-muted);
            font-size: 0.9rem;
        }

        .product-link {
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }

        .product-card {
            border: 1px solid #ddede7;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 10px 20px rgba(12, 59, 46, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 28px rgba(8, 52, 39, 0.14);
        }

        .product-media {
            height: 220px;
            width: 100%;
            object-fit: cover;
            background: #f3f6f5;
        }

        .product-name {
            font-weight: 700;
            color: #213932;
            margin-bottom: 0.4rem;
            line-height: 1.35;
            min-height: 2.7em;
        }

        .product-price {
            color: #0b6f57;
            font-weight: 800;
            margin: 0;
            font-size: 1.03rem;
        }

        .view-detail {
            font-size: 0.85rem;
            color: var(--accent-dark);
            font-weight: 700;
        }

        .empty-state {
            min-height: 300px;
            border: 1px dashed #cfe6dc;
            border-radius: 16px;
            background: #f8fdfb;
            color: #5d6f69;
        }

        .empty-state.warn {
            border-color: #efd3d3;
            background: #fffbfb;
        }

        @media (max-width: 991.98px) {
            .result-title {
                font-size: 1.75rem;
            }

            .product-media {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'layout/nav.php'; ?>
    <div class="container product-shell py-4 py-md-5">
        <section class="result-hero p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3">
                <div>
                    <h1 class="result-title">Explore Curated Products</h1>
                    <p class="result-sub mt-2">Search by name, refine by category, and jump straight into product details.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($search !== ''): ?>
                        <span class="active-pill search"><i class="fas fa-magnifying-glass"></i> <?= htmlspecialchars($search) ?></span>
                    <?php endif; ?>
                    <?php if ($filter !== ''): ?>
                        <span class="active-pill category"><i class="fas fa-filter"></i> <?= htmlspecialchars($selectedCategoryName) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="sidebar-card p-3 p-md-4 sticky-top" style="top: 96px;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="filter-title mb-0">Categories</h5>
                        <span class="result-meta"><?= count($categories) ?></span>
                    </div>

                    <div class="d-grid gap-2">
                        <?php $allCategoryParams = []; ?>
                        <?php if ($search !== '') { $allCategoryParams['productName'] = $search; } ?>
                        <a href="product.php<?= !empty($allCategoryParams) ? '?' . htmlspecialchars(http_build_query($allCategoryParams), ENT_QUOTES, 'UTF-8') : '' ?>" class="category-link <?= empty($filter) ? 'active' : '' ?>">
                            <span>All Categories</span>
                            <i class="fas fa-chevron-right small"></i>
                        </a>

                        <?php foreach ($categories as $category): ?>
                            <?php
                                $categoryParams = ['category' => $category['CategoryId']];
                                if ($search !== '') {
                                    $categoryParams['productName'] = $search;
                                }
                            ?>
                            <a href="product.php?<?= htmlspecialchars(http_build_query($categoryParams), ENT_QUOTES, 'UTF-8') ?>" class="category-link <?= $filter === (string)$category['CategoryId'] ? 'active' : '' ?>">
                                <span><?= htmlspecialchars($category['CategoryName']) ?></span>
                                <i class="fas fa-chevron-right small"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <main class="col-lg-9">
                <div class="results-card p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 border-bottom pb-3 mb-4">
                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($filter ? $selectedCategoryName : 'Search Results') ?></h5>
                        <span class="result-meta"><?= count($products) ?> products</span>
                    </div>

                    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4">
                        <?php if ($search === '' && $filter === ''): ?>
                            <div class="col-12">
                                <div class="empty-state d-flex flex-column align-items-center justify-content-center text-center p-4">
                                    <i class="fas fa-search fs-1 mb-2"></i>
                                    <h6 class="fw-bold mb-1">Start with a search</h6>
                                    <p class="mb-0">Use the top search bar or pick a category to view products.</p>
                                </div>
                            </div>
                        <?php elseif (empty($products)): ?>
                            <div class="col-12">
                                <div class="empty-state warn d-flex flex-column align-items-center justify-content-center text-center p-4">
                                    <i class="fas fa-box-open fs-1 mb-2"></i>
                                    <h6 class="fw-bold mb-1">No matching products</h6>
                                    <p class="mb-0">Try another keyword or switch to a different category.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach($products as $product): ?>
                                <div class="col">
                                    <a class="product-link" href="product_details.php?id=<?= urlencode($product['ProductId']) ?>">
                                        <article class="product-card">
                                            <img src="<?= htmlspecialchars($product['MainImage'] ?: 'asset/image/no-image.jpg') ?>" class="product-media" alt="<?= htmlspecialchars($product['ProductName']) ?>">
                                            <div class="p-3">
                                                <h6 class="product-name"><?= htmlspecialchars($product['ProductName']) ?></h6>
                                                <div class="d-flex align-items-center justify-content-between mt-2">
                                                    <p class="product-price">RM <?= number_format((float)$product['Price'], 2) ?></p>
                                                    <span class="view-detail">View Details <i class="fas fa-arrow-right ms-1"></i></span>
                                                </div>
                                            </div>
                                        </article>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>