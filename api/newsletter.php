<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helper::json(['error' => 'Method not allowed'], 405);
}

$email = trim((string)($_POST['email'] ?? ''));
$token = trim((string)($_POST['csrf_token'] ?? ''));
$context = trim((string)($_POST['newsletter_context'] ?? ''));
$anchor = $context === 'mobile' ? '#mobileNewsletter' : '#homeNewsletter';

if (!Csrf::verify($token)) {
    Helper::redirect('/?newsletter=csrf' . $anchor);
}
Csrf::regenerate();

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Helper::redirect('/?newsletter=invalid' . $anchor);
}

try {
    $db = Database::getInstance();
    $db->query(
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            context VARCHAR(20) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_newsletter_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $normalizedEmail = strtolower($email);
    $existing = $db->fetchOne("SELECT id FROM newsletter_subscribers WHERE email=?", [$normalizedEmail]);

    if ($existing) {
        $db->update('newsletter_subscribers', [
            'ip_address' => Helper::ip(),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'context' => $context === 'mobile' ? 'mobile' : 'desktop',
            'is_active' => 1,
            'subscribed_at' => date('Y-m-d H:i:s'),
        ], 'id=?', [(int) $existing['id']]);

        Helper::redirect('/?newsletter=exists' . $anchor);
    }

    $db->insert('newsletter_subscribers', [
        'email' => $normalizedEmail,
        'ip_address' => Helper::ip(),
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'context' => $context === 'mobile' ? 'mobile' : 'desktop',
        'is_active' => 1,
        'subscribed_at' => date('Y-m-d H:i:s'),
    ]);

    $subject = 'Welcome to the FatakNews newsletter';
    $textBody = implode("\n\n", [
        'Thanks for subscribing to FatakNews.',
        'You will receive breaking headlines, major updates, and top stories on this email address.',
        'If you did not subscribe, you can ignore this message.',
    ]);
    $htmlBody = implode('', [
        '<p>Thanks for subscribing to <strong>FatakNews</strong>.</p>',
        '<p>You will receive breaking headlines, major updates, and top stories on this email address.</p>',
        '<p>If you did not subscribe, you can safely ignore this message.</p>',
    ]);
    Mailer::send($normalizedEmail, $subject, $textBody, $htmlBody);
} catch (Throwable $e) {
    $dir = BASE_PATH . '/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $entry = json_encode([
        'email' => strtolower($email),
        'ip' => Helper::ip(),
        'context' => $context === 'mobile' ? 'mobile' : 'desktop',
        'time' => date('c'),
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;

    file_put_contents($dir . '/newsletter_subscribers.log', $entry, FILE_APPEND | LOCK_EX);
    Helper::redirect('/?newsletter=success' . $anchor);
}

Helper::redirect('/?newsletter=success' . $anchor);
