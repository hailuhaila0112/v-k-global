<?php
class Setting {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureTable();
    }

    private function ensureTable() {
        if ($this->conn === null) return;
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");

        $defaults = [
            'shipping_fee' => '30000',
            'free_shipping_threshold' => '15000000',
        ];

        foreach ($defaults as $key => $value) {
            $stmt = $this->conn->prepare(
                "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:k, :v)"
            );
            $stmt->execute([':k' => $key, ':v' => $value]);
        }
    }

    public function get($key, $default = null) {
        if ($this->conn === null) return $default;
        $stmt = $this->conn->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    }

    public function set($key, $value) {
        if ($this->conn === null) return false;
        $stmt = $this->conn->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([':k' => $key, ':v' => (string) $value]);
    }

    public function getShipping() {
        return [
            'shipping_fee' => (float) $this->get('shipping_fee', '30000'),
            'free_shipping_threshold' => (float) $this->get('free_shipping_threshold', '15000000'),
        ];
    }

    public function updateShipping($shippingFee, $freeThreshold) {
        $ok1 = $this->set('shipping_fee', max(0, (float) $shippingFee));
        $ok2 = $this->set('free_shipping_threshold', max(0, (float) $freeThreshold));
        return $ok1 && $ok2;
    }
}
