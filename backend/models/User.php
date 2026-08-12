<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByEmail($email) {
        if ($this->conn === null) return false;
        $query = "SELECT u.*, r.name as role_name FROM " . $this->table_name . " u 
                  LEFT JOIN roles r ON u.role_id = r.id 
                  WHERE u.email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        if ($this->conn === null) return false;
        $query = "SELECT u.id, u.name, u.email, u.phone, u.avatar, u.role_id, u.created_at,
                         r.name as role_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  ORDER BY u.id ASC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $email, $password, $phone, $role_id = 2) {
        if ($this->conn === null) return false;
        $query = "INSERT INTO " . $this->table_name . " (name, email, password, phone, role_id) 
                  VALUES (:name, :email, :password, :phone, :role_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':role_id', $role_id);
        return $stmt->execute();
    }

    public function getById($id) {
        if ($this->conn === null) return false;
        $query = "SELECT id, name, email, phone, avatar, role_id, created_at FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $email, $phone, $role_id) {
        if ($this->conn === null) return false;
        $query = "UPDATE " . $this->table_name . " SET name = :name, email = :email, phone = :phone, role_id = :role_id WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':role_id', $role_id);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function updatePassword($id, $password) {
        if ($this->conn === null) return false;
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $password);
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
