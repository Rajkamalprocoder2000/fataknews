<?php
// config/Database.php — PDO Singleton

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG) throw $e;
            die(json_encode(['error' => 'Database connection failed']));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getConnection(): PDO { return $this->pdo; }

    // Shorthand helpers
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data) {
        $cols    = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $holders = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($holders)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
        $stmt = $this->query("UPDATE `$table` SET $set WHERE $where",
            [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int {
        return $this->query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public function paginate(string $sql, array $params, int $page, int $perPage = POSTS_PER_PAGE): array {
        $countSql = "SELECT COUNT(*) FROM ($sql) AS _cnt";
        $total    = (int)$this->query($countSql, $params)->fetchColumn();
        $pages    = (int)ceil($total / $perPage);
        $offset   = ($page - 1) * $perPage;
        $rows     = $this->fetchAll("$sql LIMIT $perPage OFFSET $offset", $params);
        return ['data' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage];
    }
}
