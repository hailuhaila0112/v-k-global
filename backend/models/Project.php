<?php
// Project Model to handle database operations for projects
class Project {
    private $conn;
    private $table_name = "projects";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name, $category, $image, $description, $technologies, $progress, $status) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (name, category, image, description, technologies, progress, status) 
                  VALUES (:name, :category, :image, :description, :technologies, :progress, :status)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':technologies', $technologies);
        $stmt->bindParam(':progress', $progress);
        $stmt->bindParam(':status', $status);

        return $stmt->execute();
    }
}
