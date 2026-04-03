<?php

require_once 'config/config.php';

if (!isset($_SESSION['user_id'])) {
	header('Location: member_login.php');
	exit();
}

$userId = (string)$_SESSION['user_id'];
$allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

$statusFilter = trim((string)($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
	$statusFilter = '';
}

$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 6;
if (!in_array($perPage, [6, 12], true)) {
	$perPage = 6;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
	$page = 1;
}

function buildViewOrderUrl(array $params = []): string
{
	$filtered = [];
	foreach ($params as $key => $value) {
		if ($value === '' || $value === null) {
			continue;
		}
		$filtered[$key] = $value;
	}

	$query = http_build_query($filtered);
	return 'view_order.php' . ($query !== '' ? '?' . $query : '');
}

function orderStatusBadgeClass(string $status): string
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

$whereSql = ' WHERE o.UserId = :user_id';
$params = [':user_id' => $userId];

if ($statusFilter !== '') {
	$whereSql .= ' AND o.OrderStatus = :status_filter';
	$params[':status_filter'] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM Orders o" . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
	$countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalOrders = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalOrders / $perPage));
if ($page > $totalPages) {
	$page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$ordersSql = "SELECT o.OrderId,
					 o.TotalAmount,
					 o.OrderStatus,
					 o.OrderDate,
					 o.ShippingAddress
			  FROM Orders o"
			. $whereSql
			. " ORDER BY o.OrderDate DESC
				LIMIT :limit OFFSET :offset";
$ordersStmt = $pdo->prepare($ordersSql);
foreach ($params as $key => $value) {
	$ordersStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$ordersStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$ordersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

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

	$itemsSql = "SELECT oi.OrderId,
						oi.ProductId,
						oi.Quantity,
						oi.UnitPrice,
						p.ProductName,
						(
							SELECT pi.ImageUrl
							FROM ProductImages pi
							WHERE pi.ProductId = oi.ProductId
							ORDER BY pi.IsPrimary DESC, pi.ImageId ASC
							LIMIT 1
						) AS MainImage
				 FROM OrderItems oi
				 LEFT JOIN Products p ON p.ProductId = oi.ProductId
				 WHERE oi.OrderId IN (" . implode(', ', $placeholders) . ")
				 ORDER BY oi.OrderItemId ASC";

	$itemsStmt = $pdo->prepare($itemsSql);
	foreach ($itemParams as $key => $value) {
		$itemsStmt->bindValue($key, $value, PDO::PARAM_STR);
	}
	$itemsStmt->execute();

	foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
		$orderId = (string)$item['OrderId'];
		if (!isset($orderItemsByOrder[$orderId])) {
			$orderItemsByOrder[$orderId] = [];
		}
		$orderItemsByOrder[$orderId][] = $item;
	}
}

$stateParams = [
	'status' => $statusFilter,
	'per_page' => $perPage,
	'page' => $page,
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>E-commerce | My Orders</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<style>
		:root {
			--order-bg: #f6f9fb;
			--order-ink: #1f2937;
			--order-muted: #64748b;
			--order-accent: #0b7a5f;
			--order-accent-deep: #075a46;
			--order-line: #d9e6e1;
		}

		body {
			font-family: 'Outfit', sans-serif;
			color: var(--order-ink);
			background:
				radial-gradient(980px 420px at 10% -10%, rgba(11, 122, 95, 0.14), transparent 60%),
				radial-gradient(960px 420px at 100% 10%, rgba(59, 130, 246, 0.08), transparent 60%),
				var(--order-bg);
		}

		.order-wrap {
			max-width: 1160px;
		}

		.order-hero {
			border-radius: 18px;
			border: 1px solid rgba(11, 122, 95, 0.2);
			background: linear-gradient(140deg, #ffffff 0%, #f8fffc 100%);
			box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
		}

		.order-title {
			margin: 0;
			font-family: 'Space Grotesk', sans-serif;
			font-size: clamp(1.45rem, 2.4vw, 2rem);
			letter-spacing: -0.02em;
		}

		.order-sub {
			color: var(--order-muted);
			margin-top: 0.4rem;
			margin-bottom: 0;
		}

		.surface-card {
			background: #fff;
			border: 1px solid var(--order-line);
			border-radius: 16px;
			box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
		}

		.order-card {
			border: 1px solid #e4ece9;
			border-radius: 14px;
			background: #fff;
		}

		.item-image {
			width: 64px;
			height: 64px;
			border-radius: 10px;
			object-fit: cover;
			border: 1px solid #e5edf2;
			background: #f3f7f9;
		}

		.item-name {
			font-weight: 700;
			margin-bottom: 2px;
		}

		.item-meta {
			font-size: 0.88rem;
			color: #6b7280;
		}

		.btn-toggle-items {
			border: 1px solid #d5e4dd;
			background: #fff;
			color: #1f3d34;
			border-radius: 999px;
			font-weight: 600;
			padding: 0.35rem 0.75rem;
		}

		.btn-toggle-items:hover {
			background: #eef8f4;
		}

		.empty-orders {
			padding: 2.6rem 1.2rem;
			text-align: center;
		}

		.empty-orders i {
			font-size: 2rem;
			color: #9aa6b2;
			margin-bottom: 0.65rem;
		}

		@media (max-width: 768px) {
			.order-meta-stack {
				text-align: left;
			}
		}
	</style>
</head>
<body>
	<?php include 'layout/nav.php'; ?>

	<div class="container order-wrap py-4 py-md-5">
		<section class="order-hero p-3 p-md-4 mb-4">
			<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
				<div>
					<h1 class="order-title">My Orders</h1>
					<p class="order-sub">Track your purchases and review order item details.</p>
				</div>
				<div class="text-md-end">
					<div class="small text-muted">Total Orders</div>
					<div class="fw-bold fs-5"><?= number_format($totalOrders) ?></div>
				</div>
			</div>
		</section>

		<div class="surface-card p-3 p-md-4 mb-4">
			<form method="GET" class="row g-2 align-items-end">
				<div class="col-12 col-md-4">
					<label class="form-label mb-1">Filter by Status</label>
					<select name="status" class="form-select">
						<option value="">All statuses</option>
						<?php foreach ($allowedStatuses as $status): ?>
							<option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
								<?= htmlspecialchars($status) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-6 col-md-3">
					<label class="form-label mb-1">Per Page</label>
					<select name="per_page" class="form-select">
						<option value="6" <?= $perPage === 6 ? 'selected' : '' ?>>6</option>
						<option value="12" <?= $perPage === 12 ? 'selected' : '' ?>>12</option>
					</select>
				</div>
				<div class="col-6 col-md-5 d-flex gap-2">
					<button type="submit" class="btn btn-success w-100">Apply</button>
					<a href="view_order.php" class="btn btn-outline-secondary w-100">Reset</a>
				</div>
			</form>
		</div>

		<div class="surface-card p-3 p-md-4">
			<?php if (empty($orders)): ?>
				<div class="empty-orders">
					<i class="fa-solid fa-box-open"></i>
					<h5 class="fw-bold mb-2">No orders found</h5>
					<p class="text-muted mb-3">Once you place an order, it will appear here.</p>
					<a href="product.php" class="btn btn-success">Start Shopping</a>
				</div>
			<?php else: ?>
				<div class="d-grid gap-3">
					<?php foreach ($orders as $index => $order): ?>
						<?php
							$orderId = (string)$order['OrderId'];
							$items = $orderItemsByOrder[$orderId] ?? [];
							$collapseId = 'items_' . $index;
							$status = (string)($order['OrderStatus'] ?? 'Pending');
						?>
						<div class="order-card p-3">
							<div class="d-flex flex-column flex-md-row justify-content-between gap-3">
								<div>
									<div class="small text-muted">Order ID</div>
									<div class="fw-semibold"><?= htmlspecialchars($orderId) ?></div>
									<div class="small text-muted mt-1">
										<?= htmlspecialchars((string)date('Y-m-d H:i', strtotime((string)$order['OrderDate']))) ?>
									</div>
								</div>

								<div class="order-meta-stack text-md-end">
									<div class="fw-bold">RM <?= number_format((float)$order['TotalAmount'], 2) ?></div>
									<span class="badge bg-<?= orderStatusBadgeClass($status) ?> mt-1"><?= htmlspecialchars($status) ?></span>
								</div>
							</div>

							<?php if (!empty($order['ShippingAddress'])): ?>
								<div class="small text-muted mt-3">
									<i class="fa-solid fa-location-dot me-1"></i>
									<?= htmlspecialchars((string)$order['ShippingAddress']) ?>
								</div>
							<?php endif; ?>

							<div class="mt-3">
								<button
									type="button"
									class="btn-toggle-items"
									data-bs-toggle="collapse"
									data-bs-target="#<?= htmlspecialchars($collapseId) ?>"
									aria-expanded="false"
									aria-controls="<?= htmlspecialchars($collapseId) ?>"
								>
									View Items (<?= count($items) ?>)
								</button>
							</div>

							<div class="collapse mt-3" id="<?= htmlspecialchars($collapseId) ?>">
								<?php if (empty($items)): ?>
									<div class="text-muted small">No item details available for this order.</div>
								<?php else: ?>
									<div class="d-grid gap-2">
										<?php foreach ($items as $item): ?>
											<?php $lineTotal = ((int)$item['Quantity']) * ((float)$item['UnitPrice']); ?>
											<div class="border rounded-3 p-2">
												<div class="d-flex gap-3 align-items-center">
													<img
														src="<?= htmlspecialchars((string)($item['MainImage'] ?: 'asset/image/no-image.jpg')) ?>"
														alt="<?= htmlspecialchars((string)($item['ProductName'] ?? 'Product')) ?>"
														class="item-image"
													>
													<div class="flex-grow-1">
														<div class="item-name"><?= htmlspecialchars((string)($item['ProductName'] ?? $item['ProductId'])) ?></div>
														<div class="item-meta">Qty: <?= (int)$item['Quantity'] ?> | Unit Price: RM <?= number_format((float)$item['UnitPrice'], 2) ?></div>
													</div>
													<div class="fw-semibold">RM <?= number_format($lineTotal, 2) ?></div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ($totalPages > 1): ?>
					<nav class="mt-4" aria-label="Orders pagination">
						<ul class="pagination justify-content-end mb-0">
							<?php $prevPage = max(1, $page - 1); ?>
							<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
								<a class="page-link" href="<?= htmlspecialchars(buildViewOrderUrl(array_merge($stateParams, ['page' => $prevPage]))) ?>">Previous</a>
							</li>

							<?php for ($p = 1; $p <= $totalPages; $p++): ?>
								<li class="page-item <?= $p === $page ? 'active' : '' ?>">
									<a class="page-link" href="<?= htmlspecialchars(buildViewOrderUrl(array_merge($stateParams, ['page' => $p]))) ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>

							<?php $nextPage = min($totalPages, $page + 1); ?>
							<li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
								<a class="page-link" href="<?= htmlspecialchars(buildViewOrderUrl(array_merge($stateParams, ['page' => $nextPage]))) ?>">Next</a>
							</li>
						</ul>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
