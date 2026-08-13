-- Optional cleanup: remove hardcoded shipping_fee=30000 from settings if leftover
UPDATE settings SET setting_value = '0' WHERE setting_key = 'shipping_fee' AND setting_value = '30000';

-- If you deleted all rates but seed reappeared earlier, truncate and re-add from Admin:
-- TRUNCATE TABLE shipping_rates;
