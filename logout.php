<?php

// Ensure we have the DB connection and session available
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If a remember-me cookie exists, remove its token(s) from the DB and clear the cookie
if (isset($_COOKIE['remember_me'])) {
    $cookie = $_COOKIE['remember_me'];
    if (strpos($cookie, ':') !== false) {
        list($selector, $validator) = explode(':', $cookie, 2);
        // Attempt to delete from both possible table names to be robust
        if (isset($conn)) {
            $stmt = $conn->prepare("DELETE FROM user_token WHERE selector = ?");
            if ($stmt) {
                $stmt->bind_param('s', $selector);
                $stmt->execute();
                $stmt->close();
            }
            $stmt2 = $conn->prepare("DELETE FROM user_tokens WHERE selector = ?");
            if ($stmt2) {
                $stmt2->bind_param('s', $selector);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }
    setcookie('remember_me', '', time() - 3600, '/');
}

// Clear all session data
$_SESSION = [];

// If session uses cookies, expire the session cookie too
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Finally destroy the session and redirect
session_destroy();
header('Location: index.php');
exit;

?>