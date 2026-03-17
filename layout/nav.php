<?php

require_once __DIR__ . '/../config/config.php';

if(isset($_SESSION['user_id'])){
    $sql = "SELECT u.UserId,up.FirstName, up.ProfilePhotoUrl 
        FROM Users u 
        JOIN UserProfile up ON u.UserId = up.UserId 
        WHERE u.UserId = :user_id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userAvatar = $user ? $user['ProfilePhotoUrl'] : 'asset/image/default_avatar.png';
}


?>
<style>
    .nav-search-shell {
        width: 100%;
        max-width: 420px;
    }

    @media (min-width: 992px) {
        .nav-search-shell {
            margin: 0 auto;
        }
    }
</style>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">E-commerce</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <div class="nav-search-shell my-3 my-lg-0">
                <form class="d-flex" action="index.php" method="get" role="search">
                    <input
                        class="form-control me-2"
                        type="search"
                        name="q"
                        placeholder="Search"
                        aria-label="Search"
                    >
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
            </div>

            <ul class="navbar-nav ms-lg-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo $userAvatar; ?>" class="rounded-circle me-2" width="32" height="32" alt="User Avatar">
                            <span><?php echo $user['FirstName']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="userProfile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="member_login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="member_register.php">Register</a>
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