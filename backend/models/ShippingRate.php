<?php
class ShippingRate {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTable();
    }

    private function ensureTable() {
        if ($this->conn === null) return;

        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS shipping_rates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                fee DECIMAL(15,2) NOT NULL DEFAULT 0,
                free_shipping_threshold DECIMAL(15,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");

        $count = (int) $this->conn->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn();
        if ($count === 0) {
            // Seed from old settings if present, else defaults
            $fee = 30000;
            $threshold = 15000000;
            try {
                $stmt = $this->conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('shipping_fee','free_shipping_threshold')");
                if ($stmt) {
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        if ($row['setting_key'] === 'shipping_fee') $fee = (float) $row['setting_value'];
                        if ($row['setting_key'] === 'free_shipping_threshold') $threshold = (float) $row['setting_value'];
                    }
                }
            } catch (Exception $e) {
                // settings table may not exist
            }

            $insert = $this->conn->prepare(
                "INSERT INTO shipping_rates (name, fee, free_shipping_threshold, is_active, is_default)
                 VALUES ('Giao hàng tiêu chuẩn', :fee, :threshold, 1, 1)"
            );
            $insert->execute([':fee' => $fee, ':threshold' => $threshold]);
        }
    }

    public function getAll() {
        if ($this->conn === null) return [];
        $stmt = $this->conn->query("SELECT * FROM shipping_rates ORDER BY is_default DESC, id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive() {
        if ($this->conn === null) return [];
        $stmt = $this->conn->query(
            "SELECT * FROM shipping_rates WHERE is_active = 1 ORDER BY is_default DESC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        if ($this->conn === null) return null;
        $stmt = $this->conn->prepare("SELECT * FROM shipping_rates WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefault() {
        if ($this->conn === null) return null;
        $stmt = $this->conn->query(
            "SELECT * FROM shipping_rates WHERE is_active = 1 AND is_default = 1 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $stmt = $this->conn->query(
            "SELECT * FROM shipping_rates WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create($name, $fee, $threshold, $isActive = 1, $isDefault = 0) {
        if ($this->conn === null) return ["success" => false, "message" => "DB error"];
        try {
            if ($isDefault) {
                $this->conn->exec("UPDATE shipping_rates SET is_default = 0");
            }
            $stmt = $this->conn->prepare(
                "INSERT INTO shipping_rates (name, fee, free_shipping_threshold, is_active, is_default)
                 VALUES (:name, :fee, :threshold, :active, :default)"
            );
            $stmt->execute([
                ':name' => $name,
                ':fee' => max(0, (float) $fee),
                ':threshold' => max(0, (float) $threshold),
                ':active' => $isActive ? 1 : 0,
                ':default' => $isDefault ? 1 : 0,
            ]);
            return ["success" => true, "id" => (int) $this->conn->lastInsertId()];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function update($id, $name, $fee, $threshold, $isActive = 1, $isDefault = 0) {
        if ($this->conn === null) return ["success" => false, "message" => "DB error"];
        try {
            if ($isDefault) {
                $this->conn->exec("UPDATE shipping_rates SET is_default = 0");
            }
            $stmt = $this->conn->prepare(
                "UPDATE shipping_rates SET
                    name = :name,
                    fee = :fee,
                    free_shipping_threshold = :threshold,
                    is_active = :active,
                    is_default = :default
                 WHERE id = :id"
            );
            $stmt->execute([
                ':name' => $name,
                ':fee' => max(0, (float) $fee),
                ':threshold' => max(0, (float) $threshold),
                ':active' => $isActive ? 1 : 0,
                ':default' => $isDefault ? 1 : 0,
                ':id' => $id,
            ]);
            return ["success" => true];
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function delete($id) {
        if ($this->conn === null) return ["success" => false, "message" => "DB error"];
        $rate = $this->getById($id);
        if (!$rate) return ["success" => false, "message" => "Không tìm thấy"];

        $stmt = $this->conn->prepare("DELETE FROM shipping_rates WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Ensure one default remains
        $remaining = $this->getAll();
        if (!empty($remaining) && !$this->getDefault()) {
            $firstId = (int) $remaining[0]['id'];
            $fix = $this->conn->prepare("UPDATE shipping_rates SET is_default = 1, is_active = 1 WHERE id = :id");
            $fix->execute([':id' => $firstId]);
        }

        return ["success" => true];
    }

    public function toPublicShipping($rate = null) {
        $rate = $rate ?: $this->getDefault();
        if (!$rate) {
            return [
                'shipping_fee' => 30000,
                'free_shipping_threshold' => 15000000,
                'shipping_rate_id' => null,
                'name' => 'Giao hàng tiêu chuẩn',
            ];
        }
        return [
            'shipping_fee' => (float) $rate['fee'],
            'free_shipping_threshold' => (float) $rate['free_shipping_threshold'],
            'shipping_rate_id' => (int) $rate['id'],
            'name' => $rate['name'],
        ];
    }
}
