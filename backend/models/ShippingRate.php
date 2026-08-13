<?php
class ShippingRate {
    private $conn;
    public $lastError = null;

    public function __construct($db) {
        $this->conn = $db;
        try {
            $this->ensureTable();
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('ShippingRate ensureTable error: ' . $e->getMessage());
        }
    }

    private function ensureTable() {
        if ($this->conn === null) return;

        // Chỉ tạo bảng — KHÔNG tự insert 30000 (tránh hiện lại sau khi admin xóa)
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function syncSettingsFromDefault() {
        try {
            $rate = $this->getDefault();
            $fee = $rate ? (float) $rate['fee'] : 0;
            $threshold = $rate ? (float) $rate['free_shipping_threshold'] : 0;

            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $stmt = $this->conn->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([':k' => 'shipping_fee', ':v' => (string) $fee]);
            $stmt->execute([':k' => 'free_shipping_threshold', ':v' => (string) $threshold]);
        } catch (Throwable $e) {
            // settings sync is best-effort
        }
    }

    public function getAll() {
        if ($this->conn === null) return [];
        try {
            $this->ensureTable();
            $stmt = $this->conn->query("SELECT * FROM shipping_rates ORDER BY is_default DESC, id DESC");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getActive() {
        if ($this->conn === null) return [];
        try {
            $this->ensureTable();
            $stmt = $this->conn->query(
                "SELECT * FROM shipping_rates WHERE is_active = 1 ORDER BY is_default DESC, id ASC"
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getById($id) {
        if ($this->conn === null) return null;
        $stmt = $this->conn->prepare("SELECT * FROM shipping_rates WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefault() {
        if ($this->conn === null) return null;
        try {
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
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function create($name, $fee, $threshold, $isActive = 1, $isDefault = 0) {
        if ($this->conn === null) return ["success" => false, "message" => "DB error"];
        try {
            $this->ensureTable();
            if ($isDefault) {
                $this->conn->exec("UPDATE shipping_rates SET is_default = 0");
            }
            $stmt = $this->conn->prepare(
                "INSERT INTO shipping_rates (name, fee, free_shipping_threshold, is_active, is_default)
                 VALUES (?, ?, ?, ?, ?)"
            );
            // Nếu đây là bản ghi đầu tiên → tự đặt mặc định
            $count = (int) $this->conn->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn();
            if ($count === 0) {
                $isDefault = 1;
                $isActive = 1;
            }

            $stmt->execute([
                $name,
                max(0, (float) $fee),
                max(0, (float) $threshold),
                $isActive ? 1 : 0,
                $isDefault ? 1 : 0,
            ]);
            $newId = (int) $this->conn->lastInsertId();
            $this->syncSettingsFromDefault();
            return ["success" => true, "id" => $newId];
        } catch (Throwable $e) {
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
                    name = ?,
                    fee = ?,
                    free_shipping_threshold = ?,
                    is_active = ?,
                    is_default = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $name,
                max(0, (float) $fee),
                max(0, (float) $threshold),
                $isActive ? 1 : 0,
                $isDefault ? 1 : 0,
                (int) $id,
            ]);
            $this->syncSettingsFromDefault();
            return ["success" => true];
        } catch (Throwable $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function delete($id) {
        if ($this->conn === null) return ["success" => false, "message" => "DB error"];
        $rate = $this->getById($id);
        if (!$rate) return ["success" => false, "message" => "Không tìm thấy"];

        $stmt = $this->conn->prepare("DELETE FROM shipping_rates WHERE id = ?");
        $stmt->execute([(int) $id]);

        $remaining = $this->getAll();
        if (!empty($remaining) && !$this->getDefault()) {
            $firstId = (int) $remaining[0]['id'];
            $fix = $this->conn->prepare("UPDATE shipping_rates SET is_default = 1, is_active = 1 WHERE id = ?");
            $fix->execute([$firstId]);
        }

        $this->syncSettingsFromDefault();
        return ["success" => true];
    }

    public function toPublicShipping($rate = null) {
        $rate = $rate ?: $this->getDefault();
        if (!$rate) {
            return [
                'shipping_fee' => 0,
                'free_shipping_threshold' => 0,
                'shipping_rate_id' => null,
                'name' => 'Chưa cấu hình',
                'configured' => false,
            ];
        }
        return [
            'shipping_fee' => (float) $rate['fee'],
            'free_shipping_threshold' => (float) $rate['free_shipping_threshold'],
            'shipping_rate_id' => (int) $rate['id'],
            'name' => $rate['name'],
            'configured' => true,
        ];
    }
}
