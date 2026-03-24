<?php

include_once '../config/config.php';
include_once '../config/auth.php';

function generateUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);

    return sprintf('%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function normalizeUploadedFiles(?array $fileInput): array
{
    if (!$fileInput || !isset($fileInput['name'])) {
        return [];
    }

    if (is_array($fileInput['name'])) {
        $files = [];
        $total = count($fileInput['name']);
        for ($i = 0; $i < $total; $i++) {
            $files[] = [
                'name' => $fileInput['name'][$i] ?? '',
                'type' => $fileInput['type'][$i] ?? '',
                'tmp_name' => $fileInput['tmp_name'][$i] ?? '',
                'error' => $fileInput['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $fileInput['size'][$i] ?? 0,
            ];
        }

        return $files;
    }

    return [$fileInput];
}

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

$uploadDirRelative = 'uploads/products_images/';
$uploadDirAbsolute = dirname(__DIR__) . '/uploads/products_images/';
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$maxUploadSize = 5 * 1024 * 1024; // 5MB

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
            $selectedPrimaryImageId = trim($_POST['primary_image_id'] ?? '');

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
                $uploadedFiles = normalizeUploadedFiles($_FILES['ProductImage'] ?? null);
                $hasNewImage = false;
                foreach ($uploadedFiles as $uploadedFile) {
                    if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $hasNewImage = true;
                        break;
                    }
                }

                try {

                    $pdo->beginTransaction(); //Start transaction to ensure data integrity
                    $currentProductId = '';

                    if ($action === 'add') {
                        $currentProductId = generateUuidV4();
                        $insertSQL = "INSERT INTO Products (ProductId, ProductName, Description, Price, StockQuantity) VALUES (:product_id, :name, :description, :price, :stock)";
                        $insertStmt = $pdo->prepare($insertSQL);
                        $insertStmt->execute([
                            ':product_id' => $currentProductId,
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
                            $currentProductId = $productId;
                            $updateSQL = "UPDATE Products SET ProductName = :name, Description = :description, Price = :price, StockQuantity = :stock WHERE ProductId = :product_id";
                            $updateStmt = $pdo->prepare($updateSQL);
                            $updateStmt->execute([
                                ':name' => $productName,
                                ':description' => $description,
                                ':price' => $price,
                                ':stock' => $stockQuantity,
                                ':product_id' => $productId,
                            ]);

                            if ($selectedPrimaryImageId !== '') {
                                $clearPrimarySql = "UPDATE ProductImages SET IsPrimary = 0 WHERE ProductId = :product_id";
                                $clearPrimaryStmt = $pdo->prepare($clearPrimarySql);
                                $clearPrimaryStmt->execute([':product_id' => $productId]);

                                $setPrimarySql = "UPDATE ProductImages SET IsPrimary = 1 WHERE ImageId = :image_id AND ProductId = :product_id";
                                $setPrimaryStmt = $pdo->prepare($setPrimarySql);
                                $setPrimaryStmt->execute([
                                    ':image_id' => $selectedPrimaryImageId,
                                    ':product_id' => $productId,
                                ]);
                            }

                            $successMessage = 'Product updated successfully.';
                        }
                    }

                    if (empty($errors) && $hasNewImage) {
                        if (!is_dir($uploadDirAbsolute)) {
                            $created = @mkdir($uploadDirAbsolute, 0755, true);
                            if (!$created && !is_dir($uploadDirAbsolute)) {
                                $errors[] = 'Unable to create image upload directory.';
                            }
                        }

                        if (empty($errors)) {
                            foreach ($uploadedFiles as $uploadedFile) {
                                $errorCode = $uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE;
                                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                                    continue;
                                }

                                if ($errorCode !== UPLOAD_ERR_OK) {
                                    $errors[] = 'One of the selected images failed to upload.';
                                    continue;
                                }

                                $originalName = (string)($uploadedFile['name'] ?? '');
                                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                                if (!in_array($extension, $allowedExtensions, true)) {
                                    $errors[] = 'Only JPG, PNG, GIF, or WEBP images are allowed.';
                                    continue;
                                }

                                $fileSize = (int)($uploadedFile['size'] ?? 0);
                                if ($fileSize <= 0 || $fileSize > $maxUploadSize) {
                                    $errors[] = 'Each image must be smaller than 5MB.';
                                    continue;
                                }

                                $tmpName = (string)($uploadedFile['tmp_name'] ?? '');
                                $imageInfo = @getimagesize($tmpName);
                                if ($imageInfo === false) {
                                    $errors[] = 'Invalid image file detected.';
                                    continue;
                                }

                                $newFileName = generateUuidV4() . '.' . $extension;
                                $targetPath = $uploadDirAbsolute . $newFileName;

                                if (!move_uploaded_file($tmpName, $targetPath)) {
                                    $errors[] = 'Error uploading one of the selected images.';
                                    continue;
                                }

                                $imgSQL = "INSERT INTO ProductImages (ImageId, ProductId, ImageUrl) VALUES (:image_id, :product_id, :image_url)";
                                $imgStmt = $pdo->prepare($imgSQL);
                                $newImageId = generateUuidV4();
                                $imgStmt->execute([
                                    ':image_id' => $newImageId,
                                    ':product_id' => $currentProductId,
                                    ':image_url' => $uploadDirRelative . $newFileName,
                                ]);

                                // If user did not select a main image, keep one deterministic main image.
                                if ($selectedPrimaryImageId === '') {
                                    $setAnyPrimarySql = "UPDATE ProductImages SET IsPrimary = CASE WHEN ImageId = :image_id THEN 1 ELSE 0 END WHERE ProductId = :product_id";
                                    $setAnyPrimaryStmt = $pdo->prepare($setAnyPrimarySql);
                                    $setAnyPrimaryStmt->execute([
                                        ':image_id' => $newImageId,
                                        ':product_id' => $currentProductId,
                                    ]);
                                    $selectedPrimaryImageId = $newImageId;
                                }
                            }
                        }
                    }

                    if (!empty($errors)) {
                        $pdo->rollBack();
                    } else {
                        $pdo->commit();
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
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
$editProductImages = [];

if ($editProduct) {
    $imageSQL = "SELECT ImageId, ImageUrl, IsPrimary FROM ProductImages WHERE ProductId = :product_id ORDER BY IsPrimary DESC, ImageId ASC";
    $imageStmt = $pdo->prepare($imageSQL);
    $imageStmt->execute([':product_id' => $editProduct['ProductId']]);
    $editProductImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
}


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
                        <form method="post" action="<?php echo htmlspecialchars(buildProductManagementUrl(array_merge($stateParams, $editProduct ? ['edit' => $editProduct['ProductId']] : []))); ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="<?php echo $editProduct ? 'update' : 'add'; ?>">
                            <?php if ($editProduct): ?>
                                <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($editProduct['ProductId']); ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label" for="image">Upload Images</label>
                                <div class="d-flex flex-wrap gap-3 align-items-start">
                                    <div id="imagePreview" class="d-flex flex-wrap gap-2"></div>

                                    <label
                                        for="image"
                                        class="d-flex align-items-center justify-content-center border border-2 border-secondary-subtle rounded"
                                        style="width: 96px; height: 96px; cursor: pointer; border-style: dashed !important;"
                                        title="Add images"
                                    >
                                        <span style="font-size: 2rem; line-height: 1;">+</span>
                                    </label>
                                    <input type="file" class="d-none" id="image" name="ProductImage[]" accept="image/*" multiple>
                                </div>
                                <small class="text-muted d-block mt-2">You can select multiple images. Max 5MB each.</small>
                            </div>

                            <?php if (!empty($editProductImages)): ?>
                                <div class="mb-3">
                                    <label class="form-label">Current Images (select Main)</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($editProductImages as $existingImage): ?>
                                            <?php $normalizedImagePath = preg_replace('#^(\.\./)+#', '', (string)$existingImage['ImageUrl']); ?>
                                            <label class="position-relative" style="width: 96px; cursor: pointer;">
                                                <img
                                                    src="<?php echo htmlspecialchars('../' . ltrim((string)$normalizedImagePath, '/')); ?>"
                                                    alt="Current product image"
                                                    style="width: 96px; height: 96px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6;"
                                                >
                                                <span class="d-flex align-items-center gap-1 mt-1" style="font-size: 12px;">
                                                    <input
                                                        type="radio"
                                                        name="primary_image_id"
                                                        value="<?php echo htmlspecialchars($existingImage['ImageId']); ?>"
                                                        <?php echo (int)$existingImage['IsPrimary'] === 1 ? 'checked' : ''; ?>
                                                    >
                                                    Main
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
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
            const imageInput = document.getElementById('image');
            const imagePreview = document.getElementById('imagePreview');

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

            if (imageInput && imagePreview) {
                let selectedFiles = [];

                function syncInputFiles() {
                    const dataTransfer = new DataTransfer();
                    selectedFiles.forEach(function (file) {
                        dataTransfer.items.add(file);
                    });
                    imageInput.files = dataTransfer.files;
                }

                function renderImagePreview() {
                    imagePreview.innerHTML = '';

                    if (selectedFiles.length === 0) {
                        const emptyState = document.createElement('small');
                        emptyState.className = 'text-muted';
                        emptyState.textContent = 'No new image selected.';
                        imagePreview.appendChild(emptyState);
                        return;
                    }

                    selectedFiles.forEach(function (file, index) {
                        const imageCard = document.createElement('div');
                        imageCard.className = 'position-relative';
                        imageCard.style.width = '96px';
                        imageCard.style.height = '96px';

                        const image = document.createElement('img');
                        image.alt = 'preview';
                        image.style.width = '100%';
                        image.style.height = '100%';
                        image.style.objectFit = 'cover';
                        image.style.borderRadius = '8px';
                        image.style.border = '1px solid #dee2e6';

                        const objectUrl = URL.createObjectURL(file);
                        image.src = objectUrl;
                        image.onload = function () {
                            URL.revokeObjectURL(objectUrl);
                        };

                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'btn btn-sm btn-danger position-absolute';
                        removeButton.style.top = '-8px';
                        removeButton.style.right = '-8px';
                        removeButton.style.borderRadius = '999px';
                        removeButton.style.width = '24px';
                        removeButton.style.height = '24px';
                        removeButton.style.display = 'flex';
                        removeButton.style.alignItems = 'center';
                        removeButton.style.justifyContent = 'center';
                        removeButton.style.padding = '0';
                        removeButton.textContent = 'x';
                        removeButton.addEventListener('click', function () {
                            selectedFiles.splice(index, 1);
                            syncInputFiles();
                            renderImagePreview();
                        });

                        imageCard.appendChild(image);
                        imageCard.appendChild(removeButton);
                        imagePreview.appendChild(imageCard);
                    });
                }

                imageInput.addEventListener('change', function () {
                    const pickedFiles = Array.from(imageInput.files || []);
                    imageInput.value = '';

                    pickedFiles.forEach(function (file) {
                        if (!file.type.startsWith('image/')) {
                            return;
                        }

                        const alreadyAdded = selectedFiles.some(function (existingFile) {
                            return existingFile.name === file.name
                                && existingFile.size === file.size
                                && existingFile.lastModified === file.lastModified;
                        });

                        if (!alreadyAdded) {
                            selectedFiles.push(file);
                        }
                    });

                    syncInputFiles();
                    renderImagePreview();
                });

                renderImagePreview();
            }
        })();
    </script>
</body>
</html>