<?php

class Mailer {
    private const DEFAULT_TIMEOUT = 15;

    public static function send(string $toEmail, string $subject, string $textBody, ?string $htmlBody = null, array $options = []): bool {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $settings = self::settings();
        $fromEmail = trim((string)($options['from_email'] ?? $settings['site_email'] ?? MAIL_FROM));
        if ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false) {
            $fromEmail = MAIL_FROM;
        }

        $fromName = trim((string)($options['from_name'] ?? $settings['site_name'] ?? MAIL_FROM_NAME));
        if ($fromName === '') {
            $fromName = MAIL_FROM_NAME;
        }

        $smtpHost = trim((string)($settings['smtp_host'] ?? ''));
        $smtpPort = max(1, (int)($settings['smtp_port'] ?? 587));
        $smtpUser = trim((string)($settings['smtp_user'] ?? ''));
        $smtpPass = (string)($settings['smtp_pass'] ?? '');

        if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
            return self::sendViaSmtp([
                'host' => $smtpHost,
                'port' => $smtpPort,
                'user' => $smtpUser,
                'pass' => $smtpPass,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to_email' => $toEmail,
                'subject' => $subject,
                'text_body' => $textBody,
                'html_body' => $htmlBody,
            ]);
        }

        return self::sendViaMailFunction($toEmail, $subject, $textBody, $htmlBody, $fromEmail, $fromName);
    }

    private static function settings(): array {
        static $settings = null;
        if (is_array($settings)) {
            return $settings;
        }

        $settings = [
            'site_name' => APP_NAME,
            'site_email' => MAIL_FROM,
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
        ];

        try {
            $db = Database::getInstance();
            $rows = $db->fetchAll(
                "SELECT `key`, `value`
                 FROM settings
                 WHERE `key` IN ('site_name', 'site_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass')"
            );

            foreach ($rows as $row) {
                $key = (string)($row['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $settings[$key] = (string)($row['value'] ?? '');
            }
        } catch (Throwable $e) {
            self::logError('settings', $e->getMessage());
        }

        return $settings;
    }

    private static function sendViaMailFunction(string $toEmail, string $subject, string $textBody, ?string $htmlBody, string $fromEmail, string $fromName): bool {
        if (!function_exists('mail')) {
            self::logError('mail', 'mail() is unavailable and SMTP is not configured.');
            return false;
        }

        [$headers, $body] = self::mimeMessage($subject, $textBody, $htmlBody, $fromEmail, $fromName, $toEmail);

        return @mail($toEmail, self::encodeHeader($subject), $body, implode("\r\n", $headers));
    }

    private static function sendViaSmtp(array $payload): bool {
        $host = (string)$payload['host'];
        $port = (int)$payload['port'];
        $timeout = self::DEFAULT_TIMEOUT;
        $transportHost = $port === 465 ? 'ssl://' . $host : $host;
        $remote = $transportHost . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $stream = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($stream)) {
            self::logError('smtp', 'Connection failed: ' . $errstr . ' (' . $errno . ')');
            return false;
        }

        stream_set_timeout($stream, $timeout);

        try {
            self::expect($stream, [220]);
            self::command($stream, 'EHLO localhost', [250]);

            if ($port !== 465) {
                $startTlsReply = self::commandOptional($stream, 'STARTTLS');
                if ($startTlsReply !== null && self::replyMatches($startTlsReply, [220])) {
                    if (!@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new RuntimeException('Failed to enable TLS for SMTP connection.');
                    }
                    self::command($stream, 'EHLO localhost', [250]);
                }
            }

            self::command($stream, 'AUTH LOGIN', [334]);
            self::command($stream, base64_encode((string)$payload['user']), [334]);
            self::command($stream, base64_encode((string)$payload['pass']), [235]);
            self::command($stream, 'MAIL FROM:<' . (string)$payload['from_email'] . '>', [250]);
            self::command($stream, 'RCPT TO:<' . (string)$payload['to_email'] . '>', [250, 251]);
            self::command($stream, 'DATA', [354]);

            [$headers, $body] = self::mimeMessage(
                (string)$payload['subject'],
                (string)$payload['text_body'],
                $payload['html_body'] !== null ? (string)$payload['html_body'] : null,
                (string)$payload['from_email'],
                (string)$payload['from_name'],
                (string)$payload['to_email']
            );

            $message = implode("\r\n", $headers) . "\r\n\r\n" . self::dotStuff($body) . "\r\n.";
            fwrite($stream, $message . "\r\n");
            self::expect($stream, [250]);
            self::commandOptional($stream, 'QUIT');
            fclose($stream);
            return true;
        } catch (Throwable $e) {
            self::logError('smtp', $e->getMessage());
            if (is_resource($stream)) {
                @fwrite($stream, "QUIT\r\n");
                @fclose($stream);
            }
            return false;
        }
    }

    private static function mimeMessage(string $subject, string $textBody, ?string $htmlBody, string $fromEmail, string $fromName, string $toEmail): array {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::formatAddress($fromEmail, $fromName),
            'To: ' . self::formatAddress($toEmail),
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
        ];

        if ($htmlBody !== null && trim($htmlBody) !== '') {
            $boundary = 'bnd_' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $body = '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= trim($textBody) . "\r\n\r\n";
            $body .= '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= trim($htmlBody) . "\r\n\r\n";
            $body .= '--' . $boundary . '--';

            return [$headers, $body];
        }

        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        return [$headers, trim($textBody)];
    }

    private static function formatAddress(string $email, string $name = ''): string {
        $email = trim($email);
        $name = trim($name);

        if ($name === '') {
            return '<' . $email . '>';
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return preg_replace('/[\r\n]+/', ' ', $value) ?? $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function dotStuff(string $message): string {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        return str_replace("\n", "\r\n", $message);
    }

    private static function command($stream, string $command, array $expectedCodes): string {
        fwrite($stream, $command . "\r\n");
        return self::expect($stream, $expectedCodes);
    }

    private static function commandOptional($stream, string $command): ?string {
        fwrite($stream, $command . "\r\n");
        $reply = self::readReply($stream);
        return $reply !== '' ? $reply : null;
    }

    private static function expect($stream, array $expectedCodes): string {
        $reply = self::readReply($stream);
        if (!self::replyMatches($reply, $expectedCodes)) {
            throw new RuntimeException('Unexpected SMTP reply: ' . trim($reply));
        }
        return $reply;
    }

    private static function replyMatches(string $reply, array $expectedCodes): bool {
        $code = (int)substr($reply, 0, 3);
        return in_array($code, $expectedCodes, true);
    }

    private static function readReply($stream): string {
        $reply = '';
        while (($line = fgets($stream, 515)) !== false) {
            $reply .= $line;
            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }

        return $reply;
    }

    private static function logError(string $channel, string $message): void {
        $dir = BASE_PATH . '/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $entry = '[' . date('c') . '] ' . $channel . ': ' . $message . PHP_EOL;
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
