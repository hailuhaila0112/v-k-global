-- Migration bổ sung: cột lưu mã QR PayOS
-- Chạy nếu đã chạy migration_payos.sql trước đó
USE vk_global_db;

ALTER TABLE orders ADD COLUMN payos_qr_code TEXT NULL AFTER payos_payment_link_id;
ALTER TABLE orders ADD COLUMN payos_checkout_url VARCHAR(500) NULL AFTER payos_qr_code;
ALTER TABLE orders ADD COLUMN payos_account_number VARCHAR(50) NULL AFTER payos_checkout_url;
ALTER TABLE orders ADD COLUMN payos_account_name VARCHAR(255) NULL AFTER payos_account_number;
