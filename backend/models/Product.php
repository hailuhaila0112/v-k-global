<?php
// Product Model to handle database operations for products
class Product {
    private $conn;
    private $table_name = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll($filters = []) {
        $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN brands b ON p.brand_id = b.id
                  WHERE p.status = 1";

        if (!empty($filters['category_id'])) {
            $query .= " AND p.category_id = :category_id";
        }
        if (!empty($filters['brand_id'])) {
            $query .= " AND p.brand_id = :brand_id";
        }
        if (!empty($filters['search'])) {
            $query .= " AND (p.name LIKE :search OR p.short_description LIKE :search)";
        }

        $query .= " ORDER BY p.id DESC";
        $stmt = $this->conn->prepare($query);

        if (!empty($filters['category_id'])) {
            $stmt->bindParam(':category_id', $filters['category_id']);
        }
        if (!empty($filters['brand_id'])) {
            $stmt->bindParam(':brand_id', $filters['brand_id']);
        }
        if (!empty($filters['search'])) {
            $searchVal = "%" . $filters['search'] . "%";
            $stmt->bindParam(':search', $searchVal);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
                  FROM " . $this->table_name . " p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN brands b ON p.brand_id = b.id
                  WHERE p.id = :id AND p.status = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
