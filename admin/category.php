<?php

include_once '../config/config.php';
include_once '../config/auth.php';

function generateUuidV4(): string
{
	$data = random_bytes(16);
	$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
	$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
	$hex = bin2hex($data);

	return sprintf(
		'%s-%s-%s-%s-%s',
		substr($hex, 0, 8),
		substr($hex, 8, 4),
		substr($hex, 12, 4),
		substr($hex, 16, 4),
		substr($hex, 20, 12)
	);
}

function buildCategoryUrl(array $params = []): string
{
	$filtered = [];
	foreach ($params as $key => $value) {
		if ($value === '' || $value === null) {
			continue;
		}
		$filtered[$key] = $value;
	}

	$query = http_build_query($filtered);
	return 'category.php' . ($query !== '' ? '?' . $query : '');
}

$errors = [];
$successMessage = '';
$editCategory = null;

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
			$categoryName = trim($_POST['category_name'] ?? '');

			if ($categoryName === '') {
				$errors[] = 'Category name is required.';
			} elseif (mb_strlen($categoryName) > 100) {
				$errors[] = 'Category name must be 100 characters or fewer.';
			}

			if (empty($errors)) {
				if ($action === 'add') {
					$duplicateSql = 'SELECT COUNT(*) FROM category WHERE LOWER(CategoryName) = LOWER(:category_name)';
					$duplicateStmt = $pdo->prepare($duplicateSql);
					$duplicateStmt->execute([':category_name' => $categoryName]);

					if ((int)$duplicateStmt->fetchColumn() > 0) {
						$errors[] = 'Category name already exists.';
					} else {
						$insertSql = 'INSERT INTO category (CategoryId, CategoryName, CreateDate) VALUES (:category_id, :category_name, NOW())';
						$insertStmt = $pdo->prepare($insertSql);
						$insertStmt->execute([
							':category_id' => generateUuidV4(),
							':category_name' => $categoryName,
						]);
						$successMessage = 'Category added successfully.';
					}
				}

				if ($action === 'update') {
					$categoryId = $_POST['category_id'] ?? '';
					if ($categoryId === '') {
						$errors[] = 'Missing category ID for update.';
					} else {
						$duplicateSql = 'SELECT COUNT(*) FROM category WHERE LOWER(CategoryName) = LOWER(:category_name) AND CategoryId <> :category_id';
						$duplicateStmt = $pdo->prepare($duplicateSql);
						$duplicateStmt->execute([
							':category_name' => $categoryName,
							':category_id' => $categoryId,
						]);

						if ((int)$duplicateStmt->fetchColumn() > 0) {
							$errors[] = 'Category name already exists.';
						} else {
							$updateSql = 'UPDATE category SET CategoryName = :category_name WHERE CategoryId = :category_id';
							$updateStmt = $pdo->prepare($updateSql);
							$updateStmt->execute([
								':category_name' => $categoryName,
								':category_id' => $categoryId,
							]);
							$successMessage = 'Category updated successfully.';
						}
					}
				}
			}
		}

		if ($action === 'delete') {
			$categoryId = $_POST['category_id'] ?? '';
			if ($categoryId === '') {
				$errors[] = 'Missing category ID for delete.';
			} else {
				try {
					$deleteSql = 'DELETE FROM category WHERE CategoryId = :category_id';
					$deleteStmt = $pdo->prepare($deleteSql);
					$deleteStmt->execute([':category_id' => $categoryId]);
					$successMessage = 'Category deleted successfully.';
				} catch (Exception $e) {
					$errors[] = 'Unable to delete category: ' . $e->getMessage();
				}
			}
		}
	}
}

$editId = $_GET['edit'] ?? '';
if ($editId !== '') {
	$editSql = 'SELECT CategoryId, CategoryName, CreateDate FROM category WHERE CategoryId = :category_id LIMIT 1';
	$editStmt = $pdo->prepare($editSql);
	$editStmt->execute([':category_id' => $editId]);
	$editCategory = $editStmt->fetch(PDO::FETCH_ASSOC);
}

$whereClause = '';
$searchParams = [];

if ($search !== '') {
	$whereClause = ' WHERE c.CategoryName LIKE :search_name';
	$searchParams[':search_name'] = '%' . $search . '%';
}

$countSql = 'SELECT COUNT(*) FROM category c' . $whereClause;
$countStmt = $pdo->prepare($countSql);
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
$categorySql = 'SELECT c.CategoryId, c.CategoryName, c.CreateDate, COUNT(p.ProductId) AS ProductCount
	FROM category c
	LEFT JOIN Products p ON p.CategoryId = c.CategoryId'
	. $whereClause
	. ' GROUP BY c.CategoryId, c.CategoryName, c.CreateDate
	ORDER BY c.CreateDate DESC
	LIMIT :limit OFFSET :offset';
$categoryStmt = $pdo->prepare($categorySql);
foreach ($searchParams as $key => $value) {
	$categoryStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$categoryStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$categoryStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Category Management</title>
</head>
<body>
	<div class="container-fluid">
		<div class="row">
			<?php include_once '../layout/admin_nav.php'; ?>

			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
				<h1 class="heading1">Category Management</h1>
				<p>Manage product categories here.</p>

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
						<h5 class="mb-0"><?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?></h5>
					</div>
					<div class="card-body">
						<form method="post" action="<?php echo htmlspecialchars(buildCategoryUrl(array_merge($stateParams, $editCategory ? ['edit' => $editCategory['CategoryId']] : []))); ?>">
							<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
							<input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'add'; ?>">
							<?php if ($editCategory): ?>
								<input type="hidden" name="category_id" value="<?php echo htmlspecialchars($editCategory['CategoryId']); ?>">
							<?php endif; ?>

							<div class="row g-3">
								<div class="col-md-8">
									<label class="form-label" for="category_name">Category Name</label>
									<input
										class="form-control"
										type="text"
										id="category_name"
										name="category_name"
										maxlength="100"
										required
										value="<?php echo htmlspecialchars($editCategory['CategoryName'] ?? ''); ?>"
									>
								</div>
								<?php if ($editCategory): ?>
									<div class="col-md-4">
										<label class="form-label">Created Date</label>
										<input
											class="form-control"
											type="text"
											readonly
											value="<?php echo htmlspecialchars((string)($editCategory['CreateDate'] ?? '')); ?>"
										>
									</div>
								<?php endif; ?>
							</div>

							<div class="mt-3 d-flex gap-2">
								<button class="btn btn-primary" type="submit">
									<?php echo $editCategory ? 'Update Category' : 'Add Category'; ?>
								</button>
								<?php if ($editCategory): ?>
									<a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(buildCategoryUrl($stateParams)); ?>">Cancel Edit</a>
								<?php endif; ?>
							</div>
						</form>
					</div>
				</div>

				<div class="card shadow-sm">
					<div class="card-header bg-white d-flex justify-content-between align-items-center">
						<h5 class="mb-0">Category List</h5>
						<span class="badge bg-primary-subtle text-primary border">Total: <?php echo $totalItems; ?> item(s)</span>
					</div>

					<div class="card-body border-bottom">
						<form id="categorySearchForm" method="get" action="category.php" class="row g-2 align-items-end">
							<input type="hidden" name="page" value="1">
							<div class="col-md-6">
								<label class="form-label" for="search">Search</label>
								<input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by category name">
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
								<a href="category.php" class="btn btn-outline-secondary">Reset</a>
							</div>
						</form>
					</div>

					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0">
							<thead>
								<tr>
									<th>No.</th>
									<th>Name</th>
									<th>Products</th>
									<th>Created</th>
									<th class="text-end">Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($categories)): ?>
									<tr>
										<td colspan="5" class="text-center py-4 text-muted">No categories found.</td>
									</tr>
								<?php else: ?>
									<?php $i = $offset + 1; ?>
									<?php foreach ($categories as $category): ?>
										<tr>
											<td><?php echo $i++; ?></td>
											<td class="fw-semibold"><?php echo htmlspecialchars((string)$category['CategoryName']); ?></td>
											<td><?php echo htmlspecialchars((string)$category['ProductCount']); ?></td>
											<td><?php echo htmlspecialchars((string)($category['CreateDate'] ?? '')); ?></td>
											<td>
												<div class="d-flex justify-content-end gap-2">
													<a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(buildCategoryUrl(array_merge($stateParams, ['edit' => $category['CategoryId']]))); ?>">Edit</a>
													<form method="post" action="<?php echo htmlspecialchars(buildCategoryUrl($stateParams)); ?>" onsubmit="return confirm('Delete this category? Products under it will have empty category.');">
														<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
														<input type="hidden" name="action" value="delete">
														<input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['CategoryId']); ?>">
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
								Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($categories), $totalItems); ?> of <?php echo $totalItems; ?> entries
							</small>
							<nav aria-label="Category list pagination">
								<ul class="pagination pagination-sm mb-0">
									<li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
										<a class="page-link" href="<?php echo htmlspecialchars(buildCategoryUrl(array_merge($stateParams, ['page' => $page - 1]))); ?>">Previous</a>
									</li>
									<?php for ($p = 1; $p <= $totalPages; $p++): ?>
										<li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
											<a class="page-link" href="<?php echo htmlspecialchars(buildCategoryUrl(array_merge($stateParams, ['page' => $p]))); ?>"><?php echo $p; ?></a>
										</li>
									<?php endfor; ?>
									<li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
										<a class="page-link" href="<?php echo htmlspecialchars(buildCategoryUrl(array_merge($stateParams, ['page' => $page + 1]))); ?>">Next</a>
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
			const form = document.getElementById('categorySearchForm');
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
