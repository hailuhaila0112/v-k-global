<?php
class Slider {
    private $conn;
    private $table_name = "sliders";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY sort_order ASC, id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($image, $icon, $title, $description, $sort_order, $status) {
        $query = "INSERT INTO " . $this->table_name . " (image, icon, title, description, sort_order, status) 
                  VALUES (:image, :icon, :title, :description, :sort_order, :status)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':icon', $icon);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':sort_order', $sort_order, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function update($id, $image, $icon, $title, $description, $sort_order, $status) {
        $query = "UPDATE " . $this->table_name . " 
                  SET image=:image, icon=:icon, title=:title, description=:description, 
                      sort_order=:sort_order, status=:status 
                  WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':icon', $icon);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':sort_order', $sort_order, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function updatePartial($id, $fields) {
        $set = [];
        $params = [':id' => $id];
        foreach ($fields as $col => $val) {
            $set[] = "$col=:$col";
            $params[":$col"] = $val;
        }
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $set) . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
