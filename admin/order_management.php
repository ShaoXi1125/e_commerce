<?php

include_once '../config/config.php';
include_once '../config/auth.php';

function buildOrderManagementUrl(array $params = []): string
{
	$filtered = [];
	foreach ($params as $key => $value) {
		if ($value === '' || $value === null) {
			continue;
		}
		$filtered[$key] = $value;
	}

	$query = http_build_query($filtered);
	return 'order_management.php' . ($query !== '' ? '?' . $query : '');
}

$errors = [];
$successMessage = '';

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, [10, 20, 50], true)) {
	$perPage = 10;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
	$page = 1;
}

$allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
	$statusFilter = '';
}

$stateParams = [
	'search' => $search,
	'status' => $statusFilter,
	'per_page' => $perPage,
	'page' => $page,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$csrfToken = $_POST['csrf_token'] ?? '';
	if (!hash_equals((string)$_SESSION['csrf_token'], (string)$csrfToken)) {
		$errors[] = 'Invalid request token. Please refresh and try again.';
	} else {
		$action = trim((string)($_POST['action'] ?? ''));

		if ($action === 'update_status') {
			$orderId = trim((string)($_POST['order_id'] ?? ''));
			$nextStatus = trim((string)($_POST['order_status'] ?? ''));

			if ($orderId === '') {
				$errors[] = 'Missing order ID.';
			}

			if (!in_array($nextStatus, $allowedStatuses, true)) {
				$errors[] = 'Invalid order status selected.';
			}

			if (empty($errors)) {
				try {
					$updateStmt = $pdo->prepare("UPDATE Orders SET OrderStatus = :status WHERE OrderId = :order_id");
					$updateStmt->execute([
						':status' => $nextStatus,
						':order_id' => $orderId,
					]);

					if ($updateStmt->rowCount() > 0) {
						$successMessage = 'Order status updated successfully.';
					} else {
						$successMessage = 'No changes made. The order may already have this status.';
					}
				} catch (Exception $e) {
					$errors[] = 'Unable to update order status: ' . $e->getMessage();
				}
			}
		}
	}
}

try {
	$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn();
	$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM Orders WHERE OrderStatus = 'Pending'")->fetchColumn();
	$deliveredOrders = (int)$pdo->query("SELECT COUNT(*) FROM Orders WHERE OrderStatus = 'Delivered'")->fetchColumn();
	$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(TotalAmount), 0) FROM Orders WHERE OrderStatus <> 'Cancelled'")->fetchColumn();
} catch (Exception $e) {
	$errors[] = 'Unable to load order summary: ' . $e->getMessage();
	$totalOrders = 0;
	$pendingOrders = 0;
	$deliveredOrders = 0;
	$totalRevenue = 0.0;
}

$whereClauses = [];
$queryParams = [];

if ($search !== '') {
	$whereClauses[] = "(o.OrderId LIKE :search OR u.Email LIKE :search)";
	$queryParams[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
	$whereClauses[] = "o.OrderStatus = :status_filter";
	$queryParams[':status_filter'] = $statusFilter;
}

$whereSql = '';
if (!empty($whereClauses)) {
	$whereSql = ' WHERE ' . implode(' AND ', $whereClauses);
}

$countSql = "SELECT COUNT(*)
			 FROM Orders o
			 LEFT JOIN Users u ON u.UserId = o.UserId" . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($queryParams as $key => $value) {
	$countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();

$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
	$stateParams['page'] = $page;
}

$offset = ($page - 1) * $perPage;
$listSql = "SELECT o.OrderId,
				   o.UserId,
				   o.AddressId,
				   o.TotalAmount,
				   o.OrderStatus,
				   o.OrderDate,
				   o.ShippingAddress,
				   u.Email AS UserEmail
			FROM Orders o
			LEFT JOIN Users u ON u.UserId = o.UserId"
			. $whereSql
			. " ORDER BY o.OrderDate DESC
				LIMIT :limit OFFSET :offset";

$listStmt = $pdo->prepare($listSql);
foreach ($queryParams as $key => $value) {
	$listStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$orders = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$orderItemsByOrder = [];
if (!empty($orders)) {
	$orderIds = array_column($orders, 'OrderId');
	$placeholders = [];
	$itemParams = [];

	foreach ($orderIds as $index => $orderId) {
		$key = ':order_id_' . $index;
		$placeholders[] = $key;
		$itemParams[$key] = $orderId;
	}

	$itemSql = "SELECT oi.OrderItemId,
					   oi.OrderId,
					   oi.ProductId,
					   oi.Quantity,
					   oi.UnitPrice,
					   p.ProductName
				FROM OrderItems oi
				LEFT JOIN Products p ON p.ProductId = oi.ProductId
				WHERE oi.OrderId IN (" . implode(', ', $placeholders) . ")
				ORDER BY oi.OrderId, oi.OrderItemId";

	$itemStmt = $pdo->prepare($itemSql);
	foreach ($itemParams as $key => $value) {
		$itemStmt->bindValue($key, $value, PDO::PARAM_STR);
	}
	$itemStmt->execute();

	foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
		$orderId = (string)$item['OrderId'];
		if (!isset($orderItemsByOrder[$orderId])) {
			$orderItemsByOrder[$orderId] = [];
		}
		$orderItemsByOrder[$orderId][] = $item;
	}
}

function statusBadgeClass(string $status): string
{
	$map = [
		'Pending' => 'warning text-dark',
		'Processing' => 'info text-dark',
		'Shipped' => 'primary',
		'Delivered' => 'success',
		'Cancelled' => 'danger',
	];

	return $map[$status] ?? 'secondary';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Zeng Store - Order Management</title>
</head>
<body>

<div class="container-fluid">
	<div class="row">
		<?php include '../layout/admin_nav.php'; ?>

		<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
			<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
				<h1 class="h2">Order Management</h1>
				<div class="btn-toolbar mb-2 mb-md-0">
					<span class="badge bg-primary p-2">Admin: <?= htmlspecialchars((string)($_SESSION['email'] ?? 'Admin')) ?></span>
				</div>
			</div>

			<?php if (!empty($successMessage)): ?>
				<div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
			<?php endif; ?>

			<?php if (!empty($errors)): ?>
				<div class="alert alert-danger mb-3">
					<?php foreach ($errors as $error): ?>
						<div><?= htmlspecialchars($error) ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="row g-4 mb-4">
				<div class="col-12 col-sm-6 col-xl-3">
					<div class="card stat-card shadow-sm bg-white p-3">
						<div class="d-flex align-items-center">
							<div class="rounded-circle bg-dark bg-opacity-10 p-3 me-3">
								<i class="bi bi-receipt text-dark fs-3"></i>
							</div>
							<div>
								<p class="text-muted mb-0 small">Total Orders</p>
								<h3 class="fw-bold mb-0"><?= number_format($totalOrders) ?></h3>
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<div class="card stat-card shadow-sm bg-white p-3">
						<div class="d-flex align-items-center">
							<div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
								<i class="bi bi-hourglass-split text-warning fs-3"></i>
							</div>
							<div>
								<p class="text-muted mb-0 small">Pending Orders</p>
								<h3 class="fw-bold mb-0"><?= number_format($pendingOrders) ?></h3>
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<div class="card stat-card shadow-sm bg-white p-3">
						<div class="d-flex align-items-center">
							<div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
								<i class="bi bi-check2-circle text-success fs-3"></i>
							</div>
							<div>
								<p class="text-muted mb-0 small">Delivered Orders</p>
								<h3 class="fw-bold mb-0"><?= number_format($deliveredOrders) ?></h3>
							</div>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-xl-3">
					<div class="card stat-card shadow-sm bg-white p-3">
						<div class="d-flex align-items-center">
							<div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
								<i class="bi bi-cash-coin text-primary fs-3"></i>
							</div>
							<div>
								<p class="text-muted mb-0 small">Total Revenue</p>
								<h3 class="fw-bold mb-0">RM <?= number_format($totalRevenue, 2) ?></h3>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="card shadow-sm border-0 mb-4">
				<div class="card-body">
					<form method="GET" class="row g-2 align-items-end">
						<div class="col-12 col-md-4">
							<label class="form-label mb-1">Search Order / Email</label>
							<input
								type="text"
								name="search"
								class="form-control"
								value="<?= htmlspecialchars($search) ?>"
								placeholder="Order ID or customer email"
							>
						</div>
						<div class="col-6 col-md-3">
							<label class="form-label mb-1">Status</label>
							<select name="status" class="form-select">
								<option value="">All Statuses</option>
								<?php foreach ($allowedStatuses as $status): ?>
									<option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
										<?= htmlspecialchars($status) ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-6 col-md-2">
							<label class="form-label mb-1">Per Page</label>
							<select name="per_page" class="form-select">
								<option value="10" <?= $perPage === 10 ? 'selected' : '' ?>>10</option>
								<option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20</option>
								<option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
							</select>
						</div>
						<div class="col-12 col-md-3 d-flex gap-2">
							<button type="submit" class="btn btn-primary w-100">Apply</button>
							<a href="order_management.php" class="btn btn-outline-secondary w-100">Reset</a>
						</div>
					</form>
				</div>
			</div>

			<div class="card shadow-sm border-0">
				<div class="card-header bg-white d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Orders</h5>
					<small class="text-muted">Showing <?= number_format($totalFiltered) ?> order(s)</small>
				</div>
				<div class="table-responsive">
					<table class="table table-hover align-middle mb-0">
						<thead class="table-light">
							<tr>
								<th>Order ID</th>
								<th>Customer</th>
								<th>Order Date</th>
								<th>Total</th>
								<th>Status</th>
								<th style="min-width: 270px;">Actions</th>
							</tr>
						</thead>
						<tbody>
						<?php if (empty($orders)): ?>
							<tr>
								<td colspan="6" class="text-center py-4 text-muted">No orders found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($orders as $index => $order): ?>
								<?php
									$orderId = (string)$order['OrderId'];
									$collapseId = 'orderItems' . $index;
									$items = $orderItemsByOrder[$orderId] ?? [];
									$status = (string)($order['OrderStatus'] ?? 'Pending');
								?>
								<tr>
									<td>
										<div class="fw-semibold"><?= htmlspecialchars($orderId) ?></div>
									</td>
									<td>
										<div><?= htmlspecialchars((string)($order['UserEmail'] ?? 'Unknown')) ?></div>
										<?php if (!empty($order['ShippingAddress'])): ?>
											<small class="text-muted d-block text-truncate" style="max-width: 380px;" title="<?= htmlspecialchars((string)$order['ShippingAddress']) ?>">
												<?= htmlspecialchars((string)$order['ShippingAddress']) ?>
											</small>
										<?php endif; ?>
									</td>
									<td>
										<?= htmlspecialchars((string)date('Y-m-d H:i', strtotime((string)$order['OrderDate']))) ?>
									</td>
									<td class="fw-semibold">RM <?= number_format((float)$order['TotalAmount'], 2) ?></td>
									<td>
										<span class="badge bg-<?= statusBadgeClass($status) ?>"><?= htmlspecialchars($status) ?></span>
									</td>
									<td>
										<div class="d-flex flex-wrap gap-2">
											<form method="POST" class="d-flex gap-2">
												<input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$_SESSION['csrf_token']) ?>">
												<input type="hidden" name="action" value="update_status">
												<input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">

												<select name="order_status" class="form-select form-select-sm" style="width: 140px;">
													<?php foreach ($allowedStatuses as $statusOption): ?>
														<option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusOption === $status ? 'selected' : '' ?>>
															<?= htmlspecialchars($statusOption) ?>
														</option>
													<?php endforeach; ?>
												</select>
												<button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
											</form>

											<button
												class="btn btn-sm btn-outline-secondary"
												type="button"
												data-bs-toggle="collapse"
												data-bs-target="#<?= htmlspecialchars($collapseId) ?>"
												aria-expanded="false"
												aria-controls="<?= htmlspecialchars($collapseId) ?>"
											>
												View Items (<?= count($items) ?>)
											</button>
										</div>
									</td>
								</tr>
								<tr class="collapse" id="<?= htmlspecialchars($collapseId) ?>">
									<td colspan="6" class="bg-light">
										<?php if (empty($items)): ?>
											<div class="text-muted py-2">No order items found for this order.</div>
										<?php else: ?>
											<div class="table-responsive">
												<table class="table table-sm mb-0">
													<thead>
														<tr>
															<th>Product</th>
															<th>Quantity</th>
															<th>Unit Price</th>
															<th>Line Total</th>
														</tr>
													</thead>
													<tbody>
														<?php foreach ($items as $item): ?>
															<?php
																$lineTotal = ((int)$item['Quantity']) * ((float)$item['UnitPrice']);
															?>
															<tr>
																<td><?= htmlspecialchars((string)($item['ProductName'] ?? $item['ProductId'])) ?></td>
																<td><?= (int)$item['Quantity'] ?></td>
																<td>RM <?= number_format((float)$item['UnitPrice'], 2) ?></td>
																<td>RM <?= number_format($lineTotal, 2) ?></td>
															</tr>
														<?php endforeach; ?>
													</tbody>
												</table>
											</div>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ($totalPages > 1): ?>
				<nav class="mt-4" aria-label="Order pagination">
					<ul class="pagination justify-content-end">
						<?php $prevPage = max(1, $page - 1); ?>
						<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= htmlspecialchars(buildOrderManagementUrl(array_merge($stateParams, ['page' => $prevPage]))) ?>">Previous</a>
						</li>

						<?php for ($p = 1; $p <= $totalPages; $p++): ?>
							<li class="page-item <?= $p === $page ? 'active' : '' ?>">
								<a class="page-link" href="<?= htmlspecialchars(buildOrderManagementUrl(array_merge($stateParams, ['page' => $p]))) ?>">
									<?= $p ?>
								</a>
							</li>
						<?php endfor; ?>

						<?php $nextPage = min($totalPages, $page + 1); ?>
						<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
							<a class="page-link" href="<?= htmlspecialchars(buildOrderManagementUrl(array_merge($stateParams, ['page' => $nextPage]))) ?>">Next</a>
						</li>
					</ul>
				</nav>
			<?php endif; ?>
		</main>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
