<?php 

include_once '../config/config.php';

if (isset($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'Admin') {
    header('Location: admin_dashboard.php');
    exit;
}

function sendJsonResponse(int $statusCode, string $message, array $extra = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        sendJsonResponse(422, 'Please enter both email and password.');
    }

    // 1. 查询用户及其关联的角色名称
    $sql = "SELECT u.UserId, u.PasswordHash, r.RoleName 
            FROM Users u 
            JOIN Roles r ON u.RoleId = r.RoleId 
            WHERE u.Email = :email LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. 验证密码哈希
        if (password_verify($password, $user['PasswordHash'])) {
            
            // 3. 核心权限校验：如果是 Member 尝试登录 Admin 页面
            if ($user['RoleName'] !== 'Admin') {
                sendJsonResponse(403, 'You do not have administrator privileges to access this page.');
            }

            // 4. 登录成功，设置管理员 Session
            $_SESSION['admin_id'] = $user['UserId'];
            $_SESSION['role'] = $user['RoleName'];

            sendJsonResponse(200, 'Login successful. Redirecting...', ['redirect' => 'admin_dashboard.php']);
        } else {
            sendJsonResponse(401, 'Invalid email or password.');
        }
    } else {
        sendJsonResponse(401, 'User does not exist.');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f6f2e8;
            --ink: #1f1a15;
            --paper: rgba(255, 255, 255, 0.75);
            --accent: #da5a1b;
            --accent-strong: #b74009;
            --ok: #1f7a46;
            --error: #a31515;
            --line: rgba(31, 26, 21, 0.16);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'IBM Plex Sans', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% 20%, rgba(218, 90, 27, 0.2), transparent 42%),
                radial-gradient(circle at 85% 82%, rgba(184, 64, 9, 0.22), transparent 35%),
                linear-gradient(145deg, #f7f0df 0%, #f4ede4 48%, #efe5d2 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .shell {
            width: min(980px, 100%);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            border: 1px solid var(--line);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(49, 36, 20, 0.15);
            background: var(--paper);
            backdrop-filter: blur(8px);
            animation: settle 480ms ease-out;
        }

        @keyframes settle {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .intro {
            padding: 54px 48px;
            border-right: 1px solid var(--line);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0.12)),
                repeating-linear-gradient(135deg, transparent, transparent 12px, rgba(31, 26, 21, 0.03) 12px, rgba(31, 26, 21, 0.03) 24px);
        }

        .tag {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.6);
            margin-bottom: 20px;
        }

        .intro h1 {
            margin: 0;
            font-family: 'Syne', sans-serif;
            font-size: clamp(2rem, 5vw, 3.2rem);
            line-height: 1.02;
            letter-spacing: -0.02em;
            max-width: 11ch;
        }

        .intro p {
            margin: 18px 0 0;
            font-size: 15px;
            line-height: 1.55;
            max-width: 36ch;
            opacity: 0.88;
        }

        .intro ul {
            list-style: none;
            padding: 0;
            margin: 28px 0 0;
            display: grid;
            gap: 10px;
        }

        .intro li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .intro li::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
            flex: 0 0 auto;
            box-shadow: 0 0 0 4px rgba(218, 90, 27, 0.18);
        }

        .panel {
            padding: 54px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel h2 {
            margin: 0 0 6px;
            font-family: 'Syne', sans-serif;
            font-size: 1.65rem;
            letter-spacing: -0.01em;
        }

        .panel .subtitle {
            margin: 0 0 24px;
            opacity: 0.8;
            font-size: 14px;
        }

        .field {
            margin-bottom: 14px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        input {
            width: 100%;
            border: 1px solid rgba(31, 26, 21, 0.22);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: border-color 180ms ease, box-shadow 180ms ease;
            background: rgba(255, 255, 255, 0.9);
        }

        input:focus {
            border-color: rgba(218, 90, 27, 0.7);
            box-shadow: 0 0 0 4px rgba(218, 90, 27, 0.15);
        }

        button {
            margin-top: 8px;
            width: 100%;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: #fff;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: transform 150ms ease, box-shadow 150ms ease, filter 150ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(183, 64, 9, 0.35);
            filter: saturate(1.06);
        }

        button:disabled {
            cursor: wait;
            opacity: 0.7;
            transform: none;
            box-shadow: none;
        }

        .message {
            margin-top: 14px;
            min-height: 22px;
            font-size: 14px;
            font-weight: 500;
        }

        .message.success {
            color: var(--ok);
        }

        .message.error {
            color: var(--error);
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .intro {
                border-right: none;
                border-bottom: 1px solid var(--line);
                padding: 32px 28px;
            }

            .panel {
                padding: 32px 28px;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="intro">
            <span class="tag">Admin Portal</span>
            <h1>Control your store with confidence.</h1>
            <p>Only administrator accounts can access this area. Sign in to manage orders, products, and account activity.</p>
            <ul>
                <li>Protected access for admin role only</li>
                <li>Encrypted password verification</li>
                <li>Session-based authentication</li>
            </ul>
        </section>

        <section class="panel">
            <h2>Welcome back</h2>
            <p class="subtitle">Use your admin credentials to continue.</p>

            <form id="loginForm" action="login.php" method="post" novalidate>
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>

                <button id="submitBtn" type="submit">Sign In</button>
                <p id="message" class="message" aria-live="polite"></p>
            </form>
        </section>
    </main>

    <script>
        const loginForm = document.getElementById('loginForm');
        const messageBox = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');

        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            messageBox.textContent = '';
            messageBox.className = 'message';
            submitBtn.disabled = true;

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: new FormData(loginForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();
                const isSuccess = response.ok;

                messageBox.textContent = result.message || 'Unexpected response from server.';
                messageBox.classList.add(isSuccess ? 'success' : 'error');

                if (isSuccess && result.redirect) {
                    window.setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 650);
                }
            } catch (error) {
                messageBox.textContent = 'Network error. Please try again.';
                messageBox.classList.add('error');
            } finally {
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>