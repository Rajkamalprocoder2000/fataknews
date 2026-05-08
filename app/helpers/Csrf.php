<?php
// app/helpers/Csrf.php
class Csrf {
    public static function init(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        if (!empty($_SESSION['csrf_token']) && empty($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'][] = [
                'token' => $_SESSION['csrf_token'],
                'time' => $_SESSION['csrf_time'] ?? time(),
            ];
        }

        if (empty($_SESSION['csrf_token'])) {
            self::regenerate();
        }
    }

    public static function regenerate(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $time = time();

        $tokens = $_SESSION['csrf_tokens'] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }

        $tokens[] = ['token' => $token, 'time' => $time];
        $tokens = array_values(array_filter($tokens, static function ($entry): bool {
            return !empty($entry['token']) && !empty($entry['time']) && (time() - (int)$entry['time'] <= CSRF_EXPIRE);
        }));

        if (count($tokens) > 5) {
            $tokens = array_slice($tokens, -5);
        }

        $_SESSION['csrf_tokens'] = $tokens;
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_time']  = $time;
    }

    public static function token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE && (!isset($_SESSION) || !is_array($_SESSION))) {
            return '';
        }
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::token() . '">';
    }

    public static function verify(string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE && (!isset($_SESSION) || !is_array($_SESSION))) {
            return false;
        }

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $tokens = $_SESSION['csrf_tokens'] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }

        if (empty($tokens) && !empty($_SESSION['csrf_token'])) {
            $tokens[] = [
                'token' => $_SESSION['csrf_token'],
                'time' => $_SESSION['csrf_time'] ?? time(),
            ];
        }

        foreach ($tokens as $entry) {
            $entryToken = (string)($entry['token'] ?? '');
            $entryTime = (int)($entry['time'] ?? 0);
            if ($entryToken === '' || $entryTime <= 0) {
                continue;
            }
            if (time() - $entryTime > CSRF_EXPIRE) {
                continue;
            }
            if (hash_equals($entryToken, $token)) {
                return true;
            }
        }

        return false;
    }

    public static function check(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!self::verify($token)) {
            if (Helper::isAjax()) Helper::json(['error' => 'Invalid CSRF token'], 403);
            http_response_code(403);
            die('CSRF validation failed.');
        }
        self::regenerate();
    }
}
