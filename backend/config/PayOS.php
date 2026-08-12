<?php
// PayOS Payment Gateway Configuration
// Lấy thông tin tại https://my.payos.vn sau khi tạo kênh thanh toán
class PayOSConfig {
    public static function get(): array {
        return [
            'client_id'     => getenv('PAYOS_CLIENT_ID') ?: 'YOUR_PAYOS_CLIENT_ID',
            'api_key'       => getenv('PAYOS_API_KEY') ?: 'YOUR_PAYOS_API_KEY',
            'checksum_key'  => getenv('PAYOS_CHECKSUM_KEY') ?: 'YOUR_PAYOS_CHECKSUM_KEY',
            'api_url'       => 'https://api-merchant.payos.vn',
            // URL trang frontend (điều chỉnh theo môi trường triển khai)
            'frontend_url'  => getenv('PAYOS_FRONTEND_URL') ?: 'http://localhost/DATTDN/vk-global-html',
            // URL webhook (cần HTTPS công khai khi production; dùng ngrok khi dev)
            'webhook_url'   => getenv('PAYOS_WEBHOOK_URL') ?: 'http://localhost/DATTDN/backend/public/api/payments/payos/webhook',
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
