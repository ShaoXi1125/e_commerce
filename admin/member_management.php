<?php

include_once '../config/config.php';
include_once '../config/auth.php';

function buildMemberManagementUrl(array $params = []): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $filtered[$key] = $value;
    }

    $query = http_build_query($filtered);
    return 'member_management.php' . ($query !== '' ? '?' . $query : '');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = '';

$search = trim($_GET['search'] ?? '');
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, [5, 10], true)) {
    $perPage = 10;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = $_POST['user_id'] ?? '';

        if ($userId === '') {
            $errors[] = 'Missing user ID.';
        } else {
            try {
                if ($action === 'promote_member') {
                    $promoteSQL = "UPDATE Users
                                   SET RoleId = (SELECT RoleId FROM Roles WHERE RoleName = 'Member' LIMIT 1)
                                   WHERE UserId = :user_id
                                   AND RoleId = (SELECT RoleId FROM Roles WHERE RoleName = 'Non-member' LIMIT 1)";
                    $promoteStmt = $pdo->prepare($promoteSQL);
                    $promoteStmt->execute([':user_id' => $userId]);

                    if ($promoteStmt->rowCount() > 0) {
                        $successMessage = 'User promoted to Member successfully.';
                    } else {
                        $errors[] = 'Only existing Non-member users can be promoted.';
                    }
                }

                if ($action === 'suspend_user') {
                    $suspendSQL = "UPDATE Users
                                   SET IsActive = 0
                                   WHERE UserId = :user_id";
                    $suspendStmt = $pdo->prepare($suspendSQL);
                    $suspendStmt->execute([':user_id' => $userId]);
                    $successMessage = 'User suspended successfully.';
                }

                if ($action === 'reactivate_user') {
                    $reactivateSQL = "UPDATE Users
                                      SET IsActive = 1
                                      WHERE UserId = :user_id";
                    $reactivateStmt = $pdo->prepare($reactivateSQL);
                    $reactivateStmt->execute([':user_id' => $userId]);
                    $successMessage = 'User reactivated successfully.';
                }
            } catch (Exception $e) {
                $errors[] = 'Action failed: ' . $e->getMessage();
            }
        }
    }
}

$whereClause = " WHERE r.RoleName IN ('Member', 'Non-member')";
$searchParams = [];

if ($search !== '') {
    $whereClause .= " AND (u.Email LIKE :search_email OR r.RoleName LIKE :search_role)";
    $searchParams[':search_email'] = '%' . $search . '%';
    $searchParams[':search_role'] = '%' . $search . '%';
}

$countSQL = "SELECT COUNT(*)
             FROM Users u
             JOIN Roles r ON u.RoleId = r.RoleId"
             . $whereClause;
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

$sql = "SELECT u.UserId, u.Email, r.RoleName, u.IsActive, u.CreatedDate, u.LastLogin,
           up.FirstName, up.LastName, up.PhoneNumber,
           ad.RecipientName AS DefaultRecipientName,
           ad.PhoneNumber AS DefaultAddressPhone,
              ad.AddressLine1 AS DefaultAddressLine1,
              ad.AddressLine2 AS DefaultAddressLine2,
              ad.States AS DefaultAddressState,
              ad.City AS DefaultAddressCity,
              ad.Postcode AS DefaultAddressPostcode
        FROM Users u
    JOIN Roles r ON u.RoleId = r.RoleId
    LEFT JOIN UserProfile up ON u.UserId = up.UserId
    LEFT JOIN Addresses ad ON ad.UserId = u.UserId AND ad.IsDefault = 1"
        . $whereClause
        . " ORDER BY u.CreatedDate DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($searchParams as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management</title>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include '../layout/admin_nav.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2">Member Management</h1>
                <p>Manage your members here.</p>

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

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">User List</h5>
                        <span class="badge bg-primary-subtle text-primary border">Total: <?php echo $totalItems; ?> result(s)</span>
                    </div>
                    <div class="card-body border-bottom">
                        <form id="memberSearchForm" method="get" action="member_management.php" class="row g-2 align-items-end">
                            <input type="hidden" name="page" value="1">
                            <div class="col-md-6">
                                <label class="form-label" for="search">Search User</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email or role">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="per_page">Per Page</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <option value="5" <?php echo $perPage === 5 ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <a class="btn btn-outline-secondary" href="member_management.php">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = $offset + 1; ?>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($member['Email']); ?></td>
                                            <td>
                                                <?php if ($member['RoleName'] === 'Non-member'): ?>
                                                    <span class="badge text-bg-secondary">Non-member</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-primary">Member</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$member['IsActive'] === 1): ?>
                                                    <span class="badge text-bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-danger">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($member['CreatedDate']); ?></td>
                                            <td>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button class="btn btn-sm btn-outline-secondary view-user-btn" type="button" data-target="detail-<?php echo htmlspecialchars($member['UserId']); ?>">View Details</button>

                                                    <?php if ($member['RoleName'] === 'Non-member'): ?>
                                                        <form method="post" action="<?php echo htmlspecialchars(buildMemberManagementUrl($stateParams)); ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="action" value="promote_member">
                                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['UserId']); ?>">
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">Register as Member</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ((int)$member['IsActive'] === 1): ?>
                                                        <form method="post" action="<?php echo htmlspecialchars(buildMemberManagementUrl($stateParams)); ?>" onsubmit="return confirm('Suspend this user?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="action" value="suspend_user">
                                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['UserId']); ?>">
                                                            <button class="btn btn-sm btn-outline-danger" type="submit">Suspend</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" action="<?php echo htmlspecialchars(buildMemberManagementUrl($stateParams)); ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="action" value="reactivate_user">
                                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($member['UserId']); ?>">
                                                            <button class="btn btn-sm btn-outline-success" type="submit">Reactivate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="detail-<?php echo htmlspecialchars($member['UserId']); ?>" class="d-none bg-light">
                                            <td colspan="6">
                                                <div class="p-3">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <strong>Full Name:</strong>
                                                            <div>
                                                                <?php
                                                                $fullName = trim(($member['FirstName'] ?? '') . ' ' . ($member['LastName'] ?? ''));
                                                                echo htmlspecialchars($fullName !== '' ? $fullName : 'Not set');
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Phone:</strong>
                                                            <div><?php echo htmlspecialchars($member['PhoneNumber'] ?? 'Not set'); ?></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Last Login:</strong>
                                                            <div><?php echo htmlspecialchars($member['LastLogin'] ?? 'Never'); ?></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Default Recipient:</strong>
                                                            <div><?php echo htmlspecialchars($member['DefaultRecipientName'] ?? 'Not set'); ?></div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <strong>Default Address Phone:</strong>
                                                            <div><?php echo htmlspecialchars($member['DefaultAddressPhone'] ?? 'Not set'); ?></div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <strong>Default Address:</strong>
                                                            <div>
                                                                <?php
                                                                $addressParts = array_filter([
                                                                    trim((string)($member['DefaultAddressLine1'] ?? '')),
                                                                    trim((string)($member['DefaultAddressLine2'] ?? '')),
                                                                    trim((string)($member['DefaultAddressCity'] ?? '')),
                                                                    trim((string)($member['DefaultAddressState'] ?? '')),
                                                                    trim((string)($member['DefaultAddressPostcode'] ?? '')),
                                                                ], static fn($value) => $value !== '');
                                                                echo htmlspecialchars(!empty($addressParts) ? implode(', ', $addressParts) : 'Not set');
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
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
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + count($members), $totalItems); ?> of <?php echo $totalItems; ?> entries
                            </small>
                            <nav aria-label="Member list pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars(buildMemberManagementUrl(array_merge($stateParams, ['page' => $page - 1]))); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo htmlspecialchars(buildMemberManagementUrl(array_merge($stateParams, ['page' => $p]))); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars(buildMemberManagementUrl(array_merge($stateParams, ['page' => $page + 1]))); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    <script>
        (function () {
            const form = document.getElementById('memberSearchForm');
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

            document.querySelectorAll('.view-user-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const targetId = button.getAttribute('data-target');
                    const detailRow = document.getElementById(targetId);

                    if (!detailRow) {
                        return;
                    }

                    const isHidden = detailRow.classList.contains('d-none');

                    document.querySelectorAll('tr[id^="detail-"]').forEach(function (row) {
                        row.classList.add('d-none');
                    });

                    if (isHidden) {
                        detailRow.classList.remove('d-none');
                    }
                });
            });
        })();
    </script>
</body>
</html>