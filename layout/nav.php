<?php

require_once __DIR__ . '/../config/config.php';

$searchQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$cartItemCount = 0;

if(isset($_SESSION['user_id'])){
    $sql = "SELECT u.UserId,up.FirstName, up.ProfilePhotoUrl 
        FROM Users u 
        JOIN UserProfile up ON u.UserId = up.UserId 
        WHERE u.UserId = :user_id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userAvatar = ($user && !empty($user['ProfilePhotoUrl'])) ? $user['ProfilePhotoUrl'] : 'asset/image/default_avatar.png';
    $userFirstName = $user ? $user['FirstName'] : 'Member';

    $cartCountSql = "SELECT COALESCE(SUM(Quantity), 0) AS total_items FROM Carts WHERE UserId = :user_id";
    $cartCountStmt = $pdo->prepare($cartCountSql);
    $cartCountStmt->execute([':user_id' => $_SESSION['user_id']]);
    $cartCountData = $cartCountStmt->fetch(PDO::FETCH_ASSOC);
    $cartItemCount = $cartCountData ? (int)$cartCountData['total_items'] : 0;
}


?>
<style>
    :root {
        --nav-accent: #0f8f6f;
        --nav-accent-strong: #0b6f56;
        --nav-ink: #133128;
        --nav-surface: #ffffff;
        --nav-border: rgba(15, 143, 111, 0.12);
        --nav-shadow: 0 12px 30px rgba(8, 63, 49, 0.08);
    }

    .nav-shell {
        background: linear-gradient(135deg, #f8fffc 0%, #f3fbf8 46%, #edf7ff 100%);
        border-bottom: 1px solid var(--nav-border);
        box-shadow: var(--nav-shadow);
        position: sticky;
        top: 0;
        z-index: 1030;
    }

    .nav-inner {
        min-height: 74px;
    }

    .navbar-brand.nav-brand {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        letter-spacing: 0.02em;
        color: var(--nav-ink);
    }

    .nav-brand-badge {
        width: 36px;
        height: 36px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--nav-accent), var(--nav-accent-strong));
        color: #fff;
        box-shadow: 0 8px 16px rgba(15, 143, 111, 0.25);
    }

    .nav-search-shell {
        width: 100%;
        max-width: 460px;
    }

    .nav-search-wrap {
        position: relative;
    }

    .nav-search-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        transform: translateY(-50%);
        color: #5f7f76;
        pointer-events: none;
        font-size: 0.94rem;
    }

    .nav-search-input {
        border-radius: 999px;
        border: 1px solid #dbeee6;
        background: #fff;
        padding: 0.62rem 0.95rem 0.62rem 2.2rem;
        color: #22453c;
        box-shadow: 0 4px 14px rgba(9, 62, 48, 0.05);
    }

    .nav-search-input:focus {
        border-color: rgba(15, 143, 111, 0.45);
        box-shadow: 0 0 0 0.2rem rgba(15, 143, 111, 0.13);
    }

    .nav-search-btn {
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, var(--nav-accent), var(--nav-accent-strong));
        color: #fff;
        font-weight: 600;
        padding: 0.58rem 1rem;
        box-shadow: 0 8px 16px rgba(15, 143, 111, 0.24);
    }

    .nav-search-btn:hover {
        color: #fff;
        filter: brightness(0.98);
    }

    .nav-account-trigger {
        border: 1px solid #d6ebe3;
        background: #fff;
        border-radius: 999px;
        padding: 0.32rem 0.58rem 0.32rem 0.32rem;
        box-shadow: 0 6px 14px rgba(9, 62, 48, 0.07);
    }

    .nav-account-trigger::after {
        margin-left: 0.5rem;
    }

    .nav-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 2px solid #d7f0e8;
        object-fit: cover;
    }

    .nav-user-name {
        color: #1f3f35;
        font-weight: 600;
        max-width: 130px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .nav-auth-link {
        color: #275045;
        font-weight: 600;
        border-radius: 999px;
        padding: 0.42rem 0.85rem;
    }

    .nav-auth-link:hover {
        background: #ebf8f3;
        color: var(--nav-accent-strong);
    }

    .nav-auth-link.primary {
        background: linear-gradient(135deg, var(--nav-accent), var(--nav-accent-strong));
        color: #fff;
        box-shadow: 0 8px 16px rgba(15, 143, 111, 0.22);
    }

    .nav-auth-link.primary:hover {
        color: #fff;
        filter: brightness(0.98);
    }

    .nav-cart-link {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1px solid #d6ebe3;
        background: #fff;
        color: #1f3f35;
        box-shadow: 0 6px 14px rgba(9, 62, 48, 0.07);
        margin-right: 0.55rem;
        transition: transform 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }

    .nav-cart-link:hover {
        color: var(--nav-accent-strong);
        border-color: #b8dfd1;
        transform: translateY(-1px);
    }

    .nav-cart-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 20px;
        height: 20px;
        border-radius: 999px;
        padding: 0 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: #dc3545;
        border: 2px solid #fff;
        line-height: 1;
    }

    .dropdown-menu {
        border: 1px solid #e5f1ed;
        border-radius: 14px;
        box-shadow: 0 14px 28px rgba(11, 60, 48, 0.12);
        padding: 0.45rem;
    }

    .dropdown-item {
        border-radius: 10px;
        padding: 0.5rem 0.7rem;
    }

    .dropdown-item:hover {
        background: #ebf8f3;
        color: var(--nav-accent-strong);
    }

    .nav-search-shell {
        width: 100%;
        max-width: 460px;
    }

    @media (min-width: 992px) {
        .nav-search-shell {
            margin: 0 auto;
        }
    }

    @media (max-width: 991.98px) {
        .nav-inner {
            min-height: 68px;
        }

        .nav-account-trigger {
            width: 100%;
            justify-content: center;
            margin-top: 0.35rem;
        }

        .nav-auth-link,
        .nav-auth-link.primary {
            display: inline-flex;
            justify-content: center;
            margin-top: 0.35rem;
        }
    }
</style>
<nav class="navbar navbar-expand-lg nav-shell">
    <div class="container-fluid nav-inner">
        <a class="navbar-brand nav-brand" href="index.php" aria-label="E-commerce Home">
            <span class="nav-brand-badge"><i class="fa-solid fa-bag-shopping"></i></span>
            <span>E-commerce</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <div class="nav-search-shell my-3 my-lg-0">
                <form class="d-flex" action="product.php" method="get" role="search">
                    <div class="nav-search-wrap flex-grow-1 me-2">
                        <i class="fa-solid fa-magnifying-glass nav-search-icon"></i>
                        <input
                            class="form-control nav-search-input"
                            type="search"
                            name="productName"
                            placeholder="Search products"
                            aria-label="Search"
                            value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                    <button class="btn nav-search-btn" type="submit">Search</button>
                </form>
            </div>

            <ul class="navbar-nav ms-lg-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-cart-link" href="cart.php" aria-label="View cart">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <?php if ($cartItemCount > 0): ?>
                                <span class="nav-cart-badge"><?php echo $cartItemCount > 99 ? '99+' : $cartItemCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center nav-account-trigger" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo htmlspecialchars($userAvatar, ENT_QUOTES, 'UTF-8'); ?>" class="nav-avatar me-2" alt="User Avatar">
                            <span class="nav-user-name"><?php echo htmlspecialchars($userFirstName, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="userProfile.php"><i class="fa-regular fa-user me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link nav-auth-link" href="member_login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-auth-link primary" href="member_register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trigger = document.getElementById('userDropdown');

        if (!trigger) {
            return;
        }

        if (window.bootstrap && window.bootstrap.Dropdown) {
            window.bootstrap.Dropdown.getOrCreateInstance(trigger);
            return;
        }

        const menu = trigger.nextElementSibling;

        if (!menu) {
            return;
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            const isOpen = menu.classList.toggle('show');
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (trigger.contains(event.target) || menu.contains(event.target)) {
                return;
            }

            menu.classList.remove('show');
            trigger.setAttribute('aria-expanded', 'false');
        });
    });
</script>