<?php
// api/auth.php - Login & Register API
require_once __DIR__ . '/../includes/bootstrap.php';
Csrf::check();
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userModel = new UserModel();

function enforceRateLimit(string $namespace, string $identifier, int $limit, int $windowSeconds): void {
    $result = RateLimiter::consume($namespace, $identifier, $limit, $windowSeconds);
    if (!$result['allowed']) {
        Helper::json([
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $result['retry_after'],
        ], 429);
    }
}

function passwordResetDelivery(string $email, string $link): void {
    $subject = 'Reset your FatakNews password';
    $message = "Use this link to reset your password:\n\n" . $link . "\n\nThis link expires in 60 minutes.";
    $html = '<p>Use this link to reset your password:</p>'
        . '<p><a href="' . Helper::sanitize($link) . '">' . Helper::sanitize($link) . '</a></p>'
        . '<p>This link expires in 60 minutes.</p>';

    if (!Mailer::send($email, $subject, $message, $html) && DEBUG) {
        error_log('Password reset email could not be delivered.');
    }
}

if (str_ends_with($_SERVER['REQUEST_URI'], '/login')) {
    $email    = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    if ($email === '' || $password === '') Helper::json(['error' => 'Email and password are required'], 400);

    $authIp = Helper::ip();
    $identityKey = strtolower($email) . '|' . $authIp;
    enforceRateLimit('auth.login.ip', $authIp, AUTH_LOGIN_RATE_LIMIT, AUTH_LOGIN_RATE_WINDOW);
    enforceRateLimit('auth.login.identity', $identityKey, AUTH_LOGIN_RATE_LIMIT, AUTH_LOGIN_RATE_WINDOW);

    $user = strpos($email, '@') !== false
          ? $userModel->findByEmail($email)
          : $userModel->findByUsername($email);

    if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
        Helper::json(['error' => 'Invalid email/username or password'], 401);
    }
    if (!$user['is_active']) Helper::json(['error' => 'Your account has been suspended'], 403);

    Auth::login($user);
    $userModel->updateLastLogin((int)$user['id']);
    RateLimiter::clear('auth.login.ip', $authIp);
    RateLimiter::clear('auth.login.identity', $identityKey);

    $redirect = match($user['role_slug']) {
        'super_admin', 'admin' => '/admin',
        'manager' => '/manager',
        'editor', 'reporter' => '/employee',
        'hr' => '/hr',
        default => Helper::safeLocalPath((string)($_GET['redirect'] ?? '/'), '/'),
    };
    Helper::json(['success' => true, 'redirect' => $redirect]);
}

if (str_ends_with($_SERVER['REQUEST_URI'], '/register')) {
    $fn       = trim((string)($input['first_name'] ?? ''));
    $ln       = trim((string)($input['last_name'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $email    = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    enforceRateLimit('auth.register.ip', Helper::ip(), AUTH_REGISTER_RATE_LIMIT, AUTH_REGISTER_RATE_WINDOW);

    if ($fn === '' || $ln === '' || $username === '' || $email === '' || $password === '') {
        Helper::json(['error' => 'All fields are required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Helper::json(['error' => 'Please enter a valid email address'], 400);
    }
    if (strlen($password) < 8) Helper::json(['error' => 'Password must be at least 8 characters'], 400);
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        Helper::json(['error' => 'Username must be 3-30 chars (letters, numbers, underscore)'], 400);
    }

    if ($userModel->findByEmail($email)) Helper::json(['error' => 'Email already registered'], 409);
    if ($userModel->findByUsername($username)) Helper::json(['error' => 'Username already taken'], 409);

    $id = $userModel->register([
        'username'  => $username,
        'email'     => $email,
        'password'  => $password,
        'full_name' => "$fn $ln",
    ]);

    $user = $userModel->findById((int)$id);
    Auth::login($user);
    Helper::json(['success' => true, 'redirect' => '/']);
}

if (str_ends_with($_SERVER['REQUEST_URI'], '/forgot-password')) {
    $email = trim((string)($input['email'] ?? ''));
    $authIp = Helper::ip();
    enforceRateLimit('auth.forgot.ip', $authIp, AUTH_FORGOT_RATE_LIMIT, AUTH_FORGOT_RATE_WINDOW);
    enforceRateLimit('auth.forgot.identity', strtolower($email) . '|' . $authIp, AUTH_FORGOT_RATE_LIMIT, AUTH_FORGOT_RATE_WINDOW);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Helper::json(['error' => 'A valid email address is required'], 422);
    }

    $user = $userModel->findByEmail($email);
    $response = [
        'success' => true,
        'message' => 'If an account exists for that email, a reset link has been generated.',
    ];

    if ($user && !empty($user['is_active'])) {
        $token = bin2hex(random_bytes(32));
        $userModel->storeResetToken((int)$user['id'], $token, 3600);
        $link = Helper::siteUrl('reset-password') . '?token=' . urlencode($token);
        passwordResetDelivery((string)$user['email'], $link);
    }

    Helper::json($response);
}

if (str_ends_with($_SERVER['REQUEST_URI'], '/reset-password')) {
    $token = trim((string)($input['token'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $confirm = (string)($input['password_confirm'] ?? '');

    if ($token === '') {
        Helper::json(['error' => 'Reset token is required'], 422);
    }
    if (strlen($password) < 8) {
        Helper::json(['error' => 'Password must be at least 8 characters'], 422);
    }
    if ($password !== $confirm) {
        Helper::json(['error' => 'Passwords do not match'], 422);
    }

    $user = $userModel->findByResetToken($token);
    if (!$user) {
        Helper::json(['error' => 'This reset link is invalid or has expired'], 410);
    }

    $userModel->updatePassword((int)$user['id'], $password);
    Helper::json(['success' => true, 'message' => 'Password reset successful', 'redirect' => '/login']);
}
