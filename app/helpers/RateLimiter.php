<?php

class RateLimiter {
    private static function ensureDirectory(): string {
        $dir = BASE_PATH . '/tmp/ratelimits';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir;
    }

    private static function filePath(string $namespace, string $identifier): string {
        $payload = strtolower(trim($namespace)) . '|' . strtolower(trim($identifier));
        return self::ensureDirectory() . '/' . hash('sha256', $payload) . '.json';
    }

    public static function consume(string $namespace, string $identifier, int $limit, int $windowSeconds): array {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $path = self::filePath($namespace, $identifier !== '' ? $identifier : 'anonymous');
        $now = time();
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            return ['allowed' => true, 'remaining' => max(0, $limit - 1), 'retry_after' => 0];
        }

        flock($handle, LOCK_EX);
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($state)) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $resetAt = (int)($state['reset_at'] ?? 0);
        if ($resetAt <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $state['count'] = (int)($state['count'] ?? 0) + 1;
        $allowed = $state['count'] <= $limit;
        $remaining = max(0, $limit - $state['count']);
        $retryAfter = max(0, (int)$state['reset_at'] - $now);

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
        ];
    }

    public static function clear(string $namespace, string $identifier): void {
        $path = self::filePath($namespace, $identifier !== '' ? $identifier : 'anonymous');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
