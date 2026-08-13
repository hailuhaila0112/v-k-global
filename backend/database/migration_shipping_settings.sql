-- Migration: bảng settings (phí vận chuyển)
USE vk_global_db;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('shipping_fee', '30000'),
    ('free_shipping_threshold', '15000000')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
