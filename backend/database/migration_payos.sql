-- Migration: Thêm cột PayOS cho bảng orders
-- Chạy file này nếu database đã tồn tại trước khi tích hợp PayOS
USE vk_global_db;

ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid' AFTER payment_method;
ALTER TABLE orders ADD COLUMN payos_order_code BIGINT NULL UNIQUE AFTER payment_status;
ALTER TABLE orders ADD COLUMN payos_qr_code TEXT NULL AFTER payos_payment_link_id;
ALTER TABLE orders ADD COLUMN payos_checkout_url VARCHAR(500) NULL AFTER payos_qr_code;
ALTER TABLE orders ADD COLUMN payos_account_number VARCHAR(50) NULL AFTER payos_checkout_url;
ALTER TABLE orders ADD COLUMN payos_account_name VARCHAR(255) NULL AFTER payos_account_number;
ALTER TABLE orders ADD COLUMN paid_at TIMESTAMP NULL AFTER payos_transaction_id;
