<?php

class GoogleAuth {
    public static function isConfigured(): bool {
        return trim((string)GOOGLE_CLIENT_ID) !== '' && trim((string)GOOGLE_CLIENT_SECRET) !== '';
    }

    public static function redirectUri(): string {
        return Helper::siteUrl('auth/google/callback');
    }

    public static function beginAuthentication(string $returnTo = '/login', string $redirectAfterLogin = '/'): void {
        if (!self::isConfigured()) {
            self::flashError($returnTo, 'Google login is not configured on this server yet.');
        }

        $returnTo = self::normalizeLocalPath($returnTo, '/login');
        $redirectAfterLogin = self::normalizeLocalPath($redirectAfterLogin, '/');

        $state = bin2hex(random_bytes(24));
        $_SESSION['google_oauth_state'] = $state;
        $_SESSION['google_oauth_return_to'] = $returnTo;
        $_SESSION['google_oauth_redirect_after_login'] = $redirectAfterLogin;

        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => GOOGLE_OAUTH_SCOPES,
            'state' => $state,
            'prompt' => 'select_account',
            'include_granted_scopes' => 'true',
        ];

        header('Location: ' . GOOGLE_AUTH_BASE_URL . '?' . http_build_query($params));
        exit;
    }

    public static function handleCallback(): void {
        $returnTo = self::normalizeLocalPath((string)($_SESSION['google_oauth_return_to'] ?? '/login'), '/login');
        $redirectAfterLogin = self::normalizeLocalPath((string)($_SESSION['google_oauth_redirect_after_login'] ?? '/'), '/');

        try {
            if (!self::isConfigured()) {
                throw new RuntimeException('Google login is not configured on this server yet.');
            }

            $state = trim((string)($_GET['state'] ?? ''));
            $storedState = trim((string)($_SESSION['google_oauth_state'] ?? ''));
            if ($state === '' || $storedState === '' || !hash_equals($storedState, $state)) {
                throw new RuntimeException('Google login could not be verified. Please try again.');
            }

            if (!empty($_GET['error'])) {
                $message = ((string)$_GET['error'] === 'access_denied')
                    ? 'Google sign-in was cancelled.'
                    : 'Google sign-in failed. Please try again.';
                throw new RuntimeException($message);
            }

            $code = trim((string)($_GET['code'] ?? ''));
            if ($code === '') {
                throw new RuntimeException('Google sign-in did not return an authorization code.');
            }

            $token = self::exchangeCodeForToken($code);
            $profile = self::fetchUserProfile((string)($token['access_token'] ?? ''));

            $googleId = trim((string)($profile['sub'] ?? ''));
            $email = trim((string)($profile['email'] ?? ''));
            $name = trim((string)($profile['name'] ?? ''));
            $emailVerified = !empty($profile['email_verified']);

            if ($googleId === '' || $email === '') {
                throw new RuntimeException('Google sign-in did not return a usable profile.');
            }

            $userModel = new UserModel();
            $user = $userModel->findByGoogleIdentity($googleId);

            if (!$user) {
                $user = $userModel->findByEmail($email);
                if ($user) {
                    $userModel->linkGoogleIdentity((int)$user['id'], $googleId, $emailVerified);
                    $user = $userModel->findById((int)$user['id']);
                }
            }

            if (!$user) {
                $userId = $userModel->registerWithGoogle([
                    'google_id' => $googleId,
                    'email' => $email,
                    'full_name' => $name !== '' ? $name : strstr($email, '@', true),
                    'email_verified' => $emailVerified,
                ]);
                $user = $userModel->findById((int)$userId);
            }

            if (!$user || empty($user['is_active'])) {
                throw new RuntimeException('Your account is not available for login.');
            }

            Auth::login($user);
            $userModel->updateLastLogin((int)$user['id']);
            self::clearOauthSession();
            Helper::redirect($redirectAfterLogin);
        } catch (Throwable $e) {
            self::clearOauthSession();
            self::flashError($returnTo, $e->getMessage());
        }
    }

    private static function exchangeCodeForToken(string $code): array {
        $response = self::request(GOOGLE_TOKEN_URL, [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new RuntimeException('Google token exchange failed.');
        }

        return $response;
    }

    private static function fetchUserProfile(string $accessToken): array {
        if ($accessToken === '') {
            throw new RuntimeException('Google access token is missing.');
        }

        $ch = curl_init(GOOGLE_USERINFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if (defined('AI_CA_BUNDLE') && is_file(AI_CA_BUNDLE)) {
            curl_setopt($ch, CURLOPT_CAINFO, AI_CA_BUNDLE);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Unable to contact Google profile service: ' . ($error ?: 'unknown cURL error'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google profile service returned an invalid response.');
        }

        if ($status >= 400) {
            $message = $decoded['error_description'] ?? $decoded['error'] ?? $decoded['message'] ?? 'Google profile request failed.';
            throw new RuntimeException((string)$message);
        }

        return $decoded;
    }

    private static function request(string $url, array $payload): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Google login.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if (defined('AI_CA_BUNDLE') && is_file(AI_CA_BUNDLE)) {
            curl_setopt($ch, CURLOPT_CAINFO, AI_CA_BUNDLE);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Unable to contact Google OAuth service: ' . ($error ?: 'unknown cURL error'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google OAuth service returned an invalid response.');
        }

        if ($status >= 400) {
            $message = $decoded['error_description'] ?? $decoded['error'] ?? $decoded['message'] ?? 'Google OAuth request failed.';
            throw new RuntimeException((string)$message);
        }

        return $decoded;
    }

    private static function normalizeLocalPath(string $path, string $fallback): string {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/')) {
            return $fallback;
        }

        $base = Helper::basePath();
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base)) ?: '/';
        } elseif ($base !== '' && $path === $base) {
            $path = '/';
        }

        if (preg_match('#^//+#', $path)) {
            return $fallback;
        }

        return $path;
    }

    private static function clearOauthSession(): void {
        unset(
            $_SESSION['google_oauth_state'],
            $_SESSION['google_oauth_return_to'],
            $_SESSION['google_oauth_redirect_after_login']
        );
    }

    private static function flashError(string $returnTo, string $message): void {
        if ($returnTo === '/register') {
            $_SESSION['register_error'] = $message;
        } else {
            $_SESSION['login_error'] = $message;
        }

        Helper::redirect($returnTo);
    }
}
