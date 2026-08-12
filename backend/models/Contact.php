<?php
class Contact {
    private $conn;
    private $table_name = "contact_messages";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $email, $message) {
        if ($this->conn === null) return false;
        $query = "INSERT INTO " . $this->table_name . " (name, email, message) VALUES (:name, :email, :message)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':message', $message);
        return $stmt->execute();
    }

    public function getAll() {
        if ($this->conn === null) return false;
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        if ($this->conn === null) return false;
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateReply($id, $reply) {
        if ($this->conn === null) return false;
        $query = "UPDATE " . $this->table_name . " SET reply = :reply, replied_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reply', $reply);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function delete($id) {
        if ($this->conn === null) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
