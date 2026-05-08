<?php
// app/models/Model.php — Base Model
class Model {
    protected Database $db;
    protected string $table = '';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE id=?", [$id]);
    }

    public function findBy(string $col, $val): ?array {
        return $this->db->fetchOne("SELECT * FROM `{$this->table}` WHERE `$col`=?", [$val]);
    }

    public function create(array $data) {
        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): int {
        return $this->db->update($this->table, $data, 'id=?', [$id]);
    }

    public function delete(int $id): int {
        return $this->db->delete($this->table, 'id=?', [$id]);
    }
}
