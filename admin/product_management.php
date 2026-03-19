<?php

include_once '../config/config.php';
include_once '../config/auth.php';

function buildProductManagementUrl(array $params = []): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $filtered[$key] = $value;
    }

    $query = http_build_query($filtered);
    return 'product_management.php' . ($query !== '' ? '?' . $query : '');
}

$errors = [];
$successMessage = '';
$editProduct = null;

$search = trim($_GET['search'] ?? '');
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 5;
if (!in_array($perPage, [5, 10], true)) {
    $perPage = 5;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$stateParams = [
    'search' => $search,
    'per_page' => $perPage,
    'page' => $page,
];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'update') {
            $productName = trim($_POST['product_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priceInput = trim($_POST['price'] ?? '');
            $stockInput = trim($_POST['stock_quantity'] ?? '');

            if ($productName === '') {
                $errors[] = 'Product name is required.';
            }

            if ($priceInput === '' || !is_numeric($priceInput) || (float)$priceInput < 0) {
                $errors[] = 'Price must be a valid number greater than or equal to 0.';
            }

            if ($stockInput === '' || filter_var($stockInput, FILTER_VALIDATE_INT) === false || (int)$stockInput < 0) {
                $errors[] = 'Stock quantity must be a whole number greater than or equal to 0.';
            }

            if (empty($errors)) {
                $price = number_format((float)$priceInput, 2, '.', '');
                $stockQuantity = (int)$stockInput;

                try {
                    if ($action === 'add') {
                        $insertSQL = "INSERT INTO Products (ProductId, ProductName, Description, Price, StockQuantity) VALUES (UUID(), :name, :description, :price, :stock)";
                        $insertStmt = $pdo->prepare($insertSQL);
                        $insertStmt->execute([
                            ':name' => $productName,
                            ':description' => $description,
                            ':price' => $price,
                            ':stock' => $stockQuantity,
                        ]);
                        $successMessage = 'Product added successfully.';
                    }

                    if ($action === 'update') {
                        $productId = $_POST['product_id'] ?? '';
                        if ($productId === '') {
                            $errors[] = 'Missing product ID for update.';
                        } else {
                            $updateSQL = "UPDATE Products SET ProductName = :name, Description = :description, Price = :price, StockQuantity = :stock WHERE ProductId = :product_id";
                            $updateStmt = $pdo->prepare($updateSQL);
                            $updateStmt->execute([
                                ':name' => $productName,
                                ':description' => $description,
                                ':price' => $price,
                                ':stock' => $stockQuantity,
                                ':product_id' => $productId,
                            ]);
                            $successMessage = 'Product updated successfully.';
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'delete') {
            $productId = $_POST['product_id'] ?? '';
            if ($productId === '') {
                $errors[] = 'Missing product ID for delete.';
            } else {
                try {
                    $deleteSQL = "DELETE FROM Products WHERE ProductId = :product_id";
                    $deleteStmt = $pdo->prepare($deleteSQL);
                    $deleteStmt->execute([':product_id' => $productId]);
                    $successMessage = 'Product deleted successfully.';
                } catch (Exception $e) {
                    $errors[] = 'Unable to delete product: ' . $e->getMessage();
                }
            }
        }
    }
}

$editId = $_GET['edit'] ?? '';
if ($editId !== '') {
    $editSQL = "SELECT ProductId, ProductName, Description, Price, StockQuantity FROM Products WHERE ProductId = :product_id LIMIT 1";
    $editStmt = $pdo->prepare($editSQL);
    $editStmt->execute([':product_id' => $editId]);
    $editProduct = $editStmt->fetch(PDO::FETCH_ASSOC);
}

$whereClause = '';
$searchParams = [];

if ($search !== '') {
    $whereClause = " WHERE ProductName LIKE :search_name OR Description LIKE :search_desc";
    $searchParams[':search_name'] = '%' . $search . '%';
    $searchParams[':search_desc'] = '%' . $search . '%';
}

$countSQL = "SELECT COUNT(*) FROM Products" . $whereClause;
$countStmt = $pdo->prepare($countSQL);
foreach ($searchParams as $key => $value) {
    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $stateParams['page'] = $page;
}

$offset = ($page - 1) * $perPage;
$productSQL = "SELECT ProductId, ProductName, Description, Price, StockQuantity, CreateDate FROM Products"
    . $whereClause
    . " ORDER BY CreateDate DESC LIMIT :limit OFFSET :offset";
$productStmt = $pdo->prepare($productSQL);
foreach ($searchParams as $key => $value) {
    $productStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$productStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$productStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$productStmt->execute();
$products = $productStmt->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include_once '../layout/admin_nav.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="heading1">Product Management</h1>
                <p>Manage your products here.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($successMessage !== ''): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, $editProduct ? ['edit' => $editProduct['ProductId']] : []))); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'add'; ?>">
                            <?php if ($editProduct): ?>
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($editProduct['ProductId']); ?>">
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="product_name">Product Name</label>
                                    <input class="form-control" type="text" id="product_name" name="product_name" required value="<?php echo htmlspecialchars($editProduct['ProductName'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="price">Price (RM)</label>
                                    <input class="form-control" type="number" id="price" name="price" min="0" step="0.01" required value="<?php echo htmlspecialchars($editProduct['Price'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="stock_quantity">Stock Quantity</label>
                                    <input class="form-control" type="number" id="stock_quantity" name="stock_quantity" min="0" step="1" required value="<?php echo htmlspecialchars($editProduct['StockQuantity'] ?? 0); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional product description"><?php echo htmlspecialchars($editProduct['Description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">
                                    <?php echo $editProduct ? 'Update Product' : 'Add Product'; ?>
                                </button>
                                <?php if ($editProduct): ?>
                                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(buildProductManagementUrl($stateParams)); ?>">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Product List</h5>
                        <span class="badge bg-primary-subtle text-primary border">Total: <?php echo $totalItems; ?> item(s)</span>
                    </div>

                    <div class="card-body border-bottom">
                        <form id="productSearchForm" method="get" action="product_management.php" class="row g-2 align-items-end">
                            <input type="hidden" name="page" value="1">
                            <div class="col-md-6">
                                <label class="form-label" for="search">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by product name or description">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="per_page">Per Page</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <option value="5" <?php echo $perPage === 5 ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Apply</button>
                                <a href="product_management.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No products found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = $offset + 1; ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($product['ProductName']); ?></td>
                                            <td><?php echo htmlspecialchars($product['Description'] ?? ''); ?></td>
                                            <td>RM <?php echo number_format((float)$product['Price'], 2); ?></td>
                                            <td>
                                                <?php if ((int)$product['StockQuantity'] <= 0): ?>
                                                    <span class="badge text-bg-danger">Out of stock</span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars((string)$product['StockQuantity']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['CreateDate'] ?? ''); ?></td>
                                            <td>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, ['edit' => $product['ProductId']]))); ?>">Edit</a>
                                                    <form method="post" action="<?php echo htmlspecialchars(buildProductManagementUrl($stateParams)); ?>" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['ProductId']); ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalItems > 0): ?>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($products), $totalItems); ?> of <?php echo $totalItems; ?> entries
                            </small>
                            <nav aria-label="Product list pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, ['page' => $page - 1]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, ['page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, ['page' => $page + 1]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>

                <br>
            </main>
        </div>
    </div>
    <script>
        (function () {
            const form = document.getElementById('productSearchForm');
            const searchInput = document.getElementById('search');
            const perPageSelect = document.getElementById('per_page');

            if (!form || !searchInput || !perPageSelect) {
                return;
            }

            let searchTimer = null;

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    form.submit();
                }, 350);
            });

            perPageSelect.addEventListener('change', function () {
                form.submit();
            });
        })();
    </script>
</body>
</html>