-- Migration: bảng shipping_rates (CRUD phí vận chuyển)
USE vk_global_db;

CREATE TABLE IF NOT EXISTS shipping_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    fee DECIMAL(15,2) NOT NULL DEFAULT 0,
    free_shipping_threshold DECIMAL(15,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO shipping_rates (name, fee, free_shipping_threshold, is_active, is_default)
SELECT 'Giao hàng tiêu chuẩn', 30000, 15000000, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM shipping_rates LIMIT 1);
