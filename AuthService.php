<?php
declare(strict_types=1);

/**
 * AuthService
 * Instance-based wrapper around authentication + CSRF + rate limiting helpers.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class AuthService
{
    private bool $sessionEnsured = false;

    private function ensureSessionStarted(): void
    {
        if ($this->sessionEnsured) {
            return;
        }
        if (session_status() !== PHP_SESSION_NONE) {
            $this->sessionEnsured = true;
            return;
        }

        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'TASK_TRACKER_SESS');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', defined('SESSION_HTTPONLY') ? (int)SESSION_HTTPONLY : 1);
        ini_set('session.cookie_secure', defined('SESSION_SECURE') ? (int)SESSION_SECURE : 0);
        ini_set('session.cookie_samesite', 'Strict');

        session_start();
        $this->sessionEnsured = true;
    }

    public function checkAuth(): int|false
    {
        $this->ensureSessionStarted();

        if (!isset($_SESSION['token'], $_SESSION['user_id'], $_SESSION['login_time'])) {
            return false;
        }

        $user_id = (int)$_SESSION['user_id'];

        if (empty($_SESSION['token']) || $user_id <= 0) {
            session_destroy();
            return false;
        }

        $timeout = defined('SESSION_TIMEOUT') ? (int)SESSION_TIMEOUT : 3600;
        if ((time() - (int)$_SESSION['login_time']) > $timeout) {
            session_destroy();
            return false;
        }

        if (!isset($_SESSION['last_activity']) || (time() - (int)$_SESSION['last_activity']) > 60) {
            $_SESSION['last_activity'] = time();
        }

        return $user_id;
    }

    public function requireAuth(): void
    {
        if ($this->checkAuth() === false) {
            header('Location: login.html');
            exit();
        }
    }

    public function loginUser(int $user_id, string $username): string
    {
        // Session must exist
        $this->ensureSessionStarted();

        session_regenerate_id(true);
        $token = bin2hex(random_bytes(defined('TOKEN_LENGTH') ? (int)TOKEN_LENGTH : 32));

        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['token'] = $token;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        return $token;
    }

    public function logoutUser(): void
    {
        $this->ensureSessionStarted();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }
        session_destroy();
    }

    public function verifyCSRFToken(string $token): bool
    {
        $this->ensureSessionStarted();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function checkRateLimit(string $action, int $max_attempts, int $window_seconds): array
    {
        $this->ensureSessionStarted();

        if (defined('DEV_MODE') && DEV_MODE === true) {
            return ['allowed' => true, 'wait_seconds' => null];
        }

        $key = 'ratelimit_' . $action . '_' . ($this->getClientIP());
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first' => time()];
        }

        if ((time() - (int)$_SESSION[$key]['first']) > $window_seconds) {
            $_SESSION[$key] = ['attempts' => 1, 'first' => time()];
            return ['allowed' => true, 'wait_seconds' => null];
        }

        $_SESSION[$key]['attempts']++;
        return $_SESSION[$key]['attempts'] <= $max_attempts
            ? ['allowed' => true, 'wait_seconds' => null]
            : ['allowed' => false, 'wait_seconds' => 60];
    }

    private function getClientIP(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function generateCSRFToken(): string
    {
        $this->ensureSessionStarted();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return (string)$_SESSION['csrf_token'];
    }
}

