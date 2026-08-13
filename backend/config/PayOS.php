<?php
// PayOS Payment Gateway Configuration
// Điền giá trị trong backend/.env (xem .env.example)
require_once __DIR__ . '/Env.php';

class PayOSConfig {
    public static function get(): array {
        Env::load();

        return [
            'client_id'     => Env::get('PAYOS_CLIENT_ID', 'YOUR_PAYOS_CLIENT_ID'),
            'api_key'       => Env::get('PAYOS_API_KEY', 'YOUR_PAYOS_API_KEY'),
            'checksum_key'  => Env::get('PAYOS_CHECKSUM_KEY', 'YOUR_PAYOS_CHECKSUM_KEY'),
            'api_url'       => 'https://api-merchant.payos.vn',
            'frontend_url'  => Env::get('PAYOS_FRONTEND_URL', 'http://localhost/DATTDN/vk-global-html'),
            'webhook_url'   => Env::get('PAYOS_WEBHOOK_URL', 'http://localhost/DATTDN/backend/public/api/payments/payos/webhook'),
        ];
    }

    public static function isConfigured(): bool {
        $cfg = self::get();
        return $cfg['client_id'] !== 'YOUR_PAYOS_CLIENT_ID'
            && $cfg['api_key'] !== 'YOUR_PAYOS_API_KEY'
            && $cfg['checksum_key'] !== 'YOUR_PAYOS_CHECKSUM_KEY'
            && !empty($cfg['client_id'])
            && !empty($cfg['api_key'])
            && !empty($cfg['checksum_key']);
    }
}
