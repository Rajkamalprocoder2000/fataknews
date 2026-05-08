<?php
// app/models/UserModel.php
class UserModel extends Model {
    protected string $table = 'users';

    public function findById(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.permissions
             FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?", [$id]
        );
    }

    public function findByEmail(string $email): ?array {
        return $this->db->fetchOne(
            "SELECT u.*, r.slug AS role_slug, r.permissions
             FROM users u JOIN roles r ON u.role_id=r.id WHERE u.email=?", [$email]
        );
    }

    public function findByUsername(string $username): ?array {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.permissions
             FROM users u JOIN roles r ON u.role_id=r.id WHERE u.username=?", [$username]
        );
    }

    public function findByGoogleIdentity(string $googleId): ?array {
        return $this->db->fetchOne(
            "SELECT u.*, r.slug AS role_slug, r.permissions
             FROM users u JOIN roles r ON u.role_id=r.id WHERE u.google_id=?",
            [$googleId]
        );
    }

    public function register(array $data) {
        return $this->create([
            'username'      => $data['username'],
            'email'         => $data['email'],
            'password_hash' => Auth::hashPassword($data['password']),
            'full_name'     => $data['full_name'],
            'role_id'       => 7, // User
        ]);
    }

    public function registerWithGoogle(array $data) {
        $email = trim((string)($data['email'] ?? ''));
        $googleId = trim((string)($data['google_id'] ?? ''));
        $fullName = trim((string)($data['full_name'] ?? '')) ?: 'FatakNews User';

        if ($email === '' || $googleId === '') {
            throw new InvalidArgumentException('Google profile is incomplete.');
        }

        return $this->create([
            'username' => $this->generateUniqueUsername($email, $fullName),
            'email' => $email,
            'password_hash' => Auth::hashPassword(bin2hex(random_bytes(16))),
            'full_name' => $fullName,
            'role_id' => 7,
            'google_id' => $googleId,
            'auth_provider' => 'google',
            'email_verified' => !empty($data['email_verified']) ? 1 : 0,
        ]);
    }

    public function linkGoogleIdentity(int $id, string $googleId, bool $emailVerified = true): void {
        $payload = [
            'google_id' => $googleId,
            'auth_provider' => 'google',
        ];

        if ($emailVerified) {
            $payload['email_verified'] = 1;
        }

        $this->db->update('users', $payload, 'id=?', [$id]);
    }

    public function updateLastLogin(int $id): void {
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s'), 'login_ip' => Helper::ip()], 'id=?', [$id]);
    }

    public function storeResetToken(int $id, string $token, int $ttlSeconds = 3600): void {
        $this->db->update('users', [
            'reset_token' => hash('sha256', $token),
            'reset_expires' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ], 'id=?', [$id]);
    }

    public function findByResetToken(string $token): ?array {
        $hash = hash('sha256', $token);
        return $this->db->fetchOne(
            "SELECT u.*, r.slug AS role_slug, r.permissions
             FROM users u
             JOIN roles r ON u.role_id=r.id
             WHERE u.reset_token=? AND u.reset_expires IS NOT NULL AND u.reset_expires >= NOW()
             LIMIT 1",
            [$hash]
        );
    }

    public function clearResetToken(int $id): void {
        $this->db->update('users', [
            'reset_token' => null,
            'reset_expires' => null,
        ], 'id=?', [$id]);
    }

    public function updatePassword(int $id, string $password): void {
        $this->db->update('users', [
            'password_hash' => Auth::hashPassword($password),
            'reset_token' => null,
            'reset_expires' => null,
        ], 'id=?', [$id]);
    }

    public function getFollowers(int $userId, int $limit = 20): array {
        return $this->db->fetchAll(
            "SELECT u.id, u.username, u.full_name, u.avatar, u.is_verified, u.badge_level
             FROM follows f JOIN users u ON f.follower_id=u.id
             WHERE f.following_id=? LIMIT ?", [$userId, $limit]
        );
    }

    public function isFollowing(int $followerId, int $followingId): bool {
        return (bool)$this->db->fetchOne(
            "SELECT 1 FROM follows WHERE follower_id=? AND following_id=?",
            [$followerId, $followingId]
        );
    }

    public function follow(int $followerId, int $followingId): void {
        if ($this->isFollowing($followerId, $followingId)) return;
        $this->db->insert('follows', ['follower_id' => $followerId, 'following_id' => $followingId]);
        $this->db->query("UPDATE users SET following_count=following_count+1 WHERE id=?", [$followerId]);
        $this->db->query("UPDATE users SET followers_count=followers_count+1 WHERE id=?", [$followingId]);
    }

    public function unfollow(int $followerId, int $followingId): void {
        $this->db->delete('follows', 'follower_id=? AND following_id=?', [$followerId, $followingId]);
        $this->db->query("UPDATE users SET following_count=GREATEST(0,following_count-1) WHERE id=?", [$followerId]);
        $this->db->query("UPDATE users SET followers_count=GREATEST(0,followers_count-1) WHERE id=?", [$followingId]);
    }

    public function search(string $q, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT id,username,full_name,avatar,is_verified,badge_level FROM users
             WHERE is_active=1 AND (username LIKE ? OR full_name LIKE ?) LIMIT ?",
            ["%$q%", "%$q%", $limit]
        );
    }

    public function getAllStaff(int $page = 1): array {
        return $this->db->paginate(
            "SELECT u.*, r.name AS role_name, d.name AS dept_name
             FROM users u
             JOIN roles r ON u.role_id=r.id
             LEFT JOIN employee_profiles ep ON u.id=ep.user_id
             LEFT JOIN departments d ON ep.department_id=d.id
             WHERE r.slug != 'user'",
            [], $page, 20
        );
    }

    private function generateUniqueUsername(string $email, string $fullName): string {
        $candidates = [
            preg_replace('/[^a-z0-9_]+/', '_', strtolower(str_replace(' ', '_', $fullName))) ?? '',
            preg_replace('/[^a-z0-9_]+/', '_', strtolower((string)strstr($email, '@', true))) ?? '',
            'user',
        ];

        $base = 'user';
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate, '_');
            if (strlen($candidate) >= 3) {
                $base = substr($candidate, 0, 24);
                break;
            }
        }

        $username = $base;
        $suffix = 1;
        while ($this->findByUsername($username)) {
            $suffixPart = (string)$suffix++;
            $username = substr($base, 0, max(3, 30 - strlen($suffixPart) - 1)) . '_' . $suffixPart;
        }

        return $username;
    }
}
