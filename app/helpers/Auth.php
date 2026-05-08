<?php
// app/helpers/Auth.php

class Auth {
    private static function hasSession(): bool {
        return session_status() === PHP_SESSION_ACTIVE || (isset($_SESSION) && is_array($_SESSION));
    }

    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role_slug'] = $user['role_slug'];
        $_SESSION['username']  = $user['username'];
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
        Helper::redirect('/');
    }

    public static function check(): bool {
        if (!self::hasSession()) {
            return false;
        }
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int {
        if (!self::hasSession()) {
            return null;
        }
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string {
        if (!self::hasSession()) {
            return null;
        }
        return $_SESSION['role_slug'] ?? null;
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return (new UserModel())->findById(self::id());
    }

    public static function isAdmin(): bool {
        return in_array(self::role(), ['super_admin', 'admin']);
    }

    public static function isManager(): bool {
        return in_array(self::role(), ['super_admin', 'admin', 'manager']);
    }

    public static function isEmployee(): bool {
        return in_array(self::role(), ['super_admin', 'admin', 'manager', 'editor', 'reporter', 'hr']);
    }

    public static function isHR(): bool {
        return in_array(self::role(), ['super_admin', 'admin', 'hr']);
    }

    public static function can(string $permission): bool {
        $user = self::user();
        if (!$user) return false;
        $perms = json_decode($user['permissions'] ?? '[]', true);
        return in_array('all', $perms) || in_array($permission, $perms);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            Helper::redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        }
    }

    public static function requireRole(string ...$roles): void {
        self::requireLogin();
        if (!in_array(self::role(), $roles)) {
            http_response_code(403);
            include BASE_PATH . '/app/views/pages/403.php';
            exit;
        }
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
