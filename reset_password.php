<?php
require_once 'config/config.php';

$user       = null;
$tokenError = '';

if (!isset($_GET['token']) || trim($_GET['token']) === '') {
    $tokenError = 'No reset token provided.';
} else {
    $token = trim($_GET['token']);

    $stmt = $pdo->prepare("SELECT UserId, ResetTokenExpiry FROM Users WHERE ResetToken = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user || $user['ResetTokenExpiry'] < date('Y-m-d H:i:s')) {
        $tokenError = 'This reset link is invalid or has expired.';
        $user = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    header('Content-Type: application/json; charset=utf-8');

    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        http_response_code(422);
        echo json_encode(['message' => 'Password must be at least 8 characters.']);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(422);
        echo json_encode(['message' => 'Passwords do not match.']);
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE Users SET PasswordHash = :password, ResetToken = NULL, ResetTokenExpiry = NULL WHERE UserId = :user_id")
        ->execute([':password' => $passwordHash, ':user_id' => $user['UserId']]);

    echo json_encode(['message' => 'Password reset successfully. Redirecting to login...', 'redirect' => 'member_login.php']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-commerce | Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-start: #f4fbf8;
            --bg-end:   #e8f4ff;
            --ink:      #1b2530;
            --panel:    rgba(255, 255, 255, 0.85);
            --line:     rgba(27, 37, 48, 0.14);
            --accent:        #0f8f6f;
            --accent-strong: #0b6f56;
            --success:  #176d43;
            --danger:   #b32020;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 18%, rgba(15, 143, 111, 0.22), transparent 40%),
                radial-gradient(circle at 88% 80%, rgba(39, 124, 198, 0.18), transparent 35%),
                linear-gradient(135deg, var(--bg-start), var(--bg-end));
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(460px, 100%);
            border: 1px solid var(--line);
            border-radius: 22px;
            background: var(--panel);
            backdrop-filter: blur(10px);
            box-shadow: 0 22px 48px rgba(10, 36, 60, 0.13);
            padding: 44px 40px;
            animation: rise 420ms ease-out;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: rgba(15, 143, 111, 0.12);
            display: grid;
            place-items: center;
            margin-bottom: 20px;
        }

        .icon-wrap svg { width: 28px; height: 28px; stroke: var(--accent-strong); }

        h1 {
            margin: 0 0 8px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.85rem;
            letter-spacing: -0.02em;
        }

        .sub {
            margin: 0 0 26px;
            font-size: 14.5px;
            line-height: 1.55;
            opacity: 0.84;
        }

        .error-banner {
            background: rgba(179, 32, 32, 0.08);
            border: 1px solid rgba(179, 32, 32, 0.25);
            color: var(--danger);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .field { margin-bottom: 14px; }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        input[type="password"] {
            width: 100%;
            border: 1px solid rgba(27, 37, 48, 0.2);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.92);
            transition: border-color 160ms ease, box-shadow 160ms ease;
            outline: none;
        }

        input[type="password"]:focus {
            border-color: rgba(15, 143, 111, 0.75);
            box-shadow: 0 0 0 4px rgba(15, 143, 111, 0.15);
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
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: transform 160ms ease, box-shadow 160ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 16px rgba(11, 111, 86, 0.35);
        }

        button:disabled {
            opacity: 0.7;
            cursor: wait;
            transform: none;
            box-shadow: none;
        }

        .message {
            margin-top: 14px;
            min-height: 22px;
            font-size: 14px;
            font-weight: 600;
        }
        .message.success { color: var(--success); }
        .message.error   { color: var(--danger); }

        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #11589b;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
            </svg>
        </div>

        <h1>Reset Password</h1>
        <p class="sub">Choose a strong new password for your account.</p>

        <?php if ($tokenError): ?>
            <div class="error-banner"><?php echo htmlspecialchars($tokenError); ?></div>
            <a class="back-link" href="forget_password.php">← Request a new reset link</a>
        <?php else: ?>
            <form id="resetForm" method="POST" novalidate>
                <div class="field">
                    <label for="new_password">New Password <span style="font-weight:400;opacity:.7;font-size:12px;">(min 8 characters)</span></label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                </div>

                <button type="submit" id="submitBtn">Reset Password</button>
                <p id="message" class="message" aria-live="polite"></p>
            </form>

            <a class="back-link" href="member_login.php">← Back to Login</a>

            <script>
                const form      = document.getElementById('resetForm');
                const msgBox    = document.getElementById('message');
                const submitBtn = document.getElementById('submitBtn');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    msgBox.textContent = '';
                    msgBox.className = 'message';
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Resetting...';

                    try {
                        const resp   = await fetch(window.location.href, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const result = await resp.json();

                        msgBox.textContent = result.message || 'Unexpected server response.';
                        msgBox.classList.add(resp.ok ? 'success' : 'error');

                        if (resp.ok && result.redirect) {
                            setTimeout(() => { window.location.href = result.redirect; }, 1400);
                        }
                    } catch {
                        msgBox.textContent = 'Network error. Please try again.';
                        msgBox.classList.add('error');
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Reset Password';
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>