<?php
require_once __DIR__ . '/../config/PayOS.php';

class PayOSService {
    private array $config;

    public function __construct() {
        $this->config = PayOSConfig::get();
    }

    /**
     * Tạo link thanh toán PayOS
     */
    public function createPaymentLink(int $orderCode, int $amount, string $description, array $items = []): array {
        $returnUrl = rtrim($this->config['frontend_url'], '/') . '/payment-success.html?orderCode=' . $orderCode;
        $cancelUrl = rtrim($this->config['frontend_url'], '/') . '/payment-cancel.html?orderCode=' . $orderCode;

        $payload = [
            'orderCode'   => $orderCode,
            'amount'      => $amount,
            'description' => $description,
            'cancelUrl'   => $cancelUrl,
            'returnUrl'   => $returnUrl,
        ];

        if (!empty($items)) {
            $payload['items'] = $items;
        }

        $payload['signature'] = $this->createPaymentRequestSignature($payload);

        $response = $this->request('POST', '/v2/payment-requests', $payload);

        if (($response['code'] ?? '') !== '00') {
            throw new Exception($response['desc'] ?? 'Không thể tạo link thanh toán PayOS');
        }

        return $response['data'] ?? [];
    }

    /**
     * Lấy thông tin link thanh toán
     */
    public function getPaymentInfo(int $orderCode): array {
        $response = $this->request('GET', '/v2/payment-requests/' . $orderCode);

        if (($response['code'] ?? '') !== '00') {
            throw new Exception($response['desc'] ?? 'Không tìm thấy thông tin thanh toán');
        }

        return $response['data'] ?? [];
    }

    /**
     * Xác minh chữ ký webhook từ PayOS
     */
    public function verifyWebhook(array $webhookBody): array {
        $data = $webhookBody['data'] ?? null;
        $signature = $webhookBody['signature'] ?? '';

        if (!$data || !$signature) {
            throw new Exception('Webhook không hợp lệ');
        }

        $computed = $this->createSignatureFromObject($data);
        if (!$computed || !hash_equals($computed, $signature)) {
            throw new Exception('Chữ ký webhook không hợp lệ');
        }

        return $data;
    }

    private function createPaymentRequestSignature(array $data): string {
        $str = sprintf(
            'amount=%s&cancelUrl=%s&description=%s&orderCode=%s&returnUrl=%s',
            (string) $data['amount'],
            (string) $data['cancelUrl'],
            (string) $data['description'],
            (string) $data['orderCode'],
            (string) $data['returnUrl']
        );

        return hash_hmac('sha256', $str, $this->config['checksum_key']);
    }

    private function createSignatureFromObject(array $data): string {
        ksort($data);
        $pairs = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $serialized = '[object Object]';
                } else {
                    $items = array_map(function ($item) {
                        if (is_array($item)) {
                            ksort($item);
                            return $item;
                        }
                        return $item;
                    }, $value);
                    $serialized = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } elseif (is_object($value)) {
                $serialized = '[object Object]';
            } else {
                $serialized = $this->normalizeScalar($value);
            }
            $pairs[] = $key . '=' . $serialized;
        }

        $queryString = implode('&', $pairs);
        return hash_hmac('sha256', $queryString, $this->config['checksum_key']);
    }

    private function normalizeScalar(mixed $value): string {
        if ($value === null || $value === 'undefined' || $value === 'null') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }

    private function isAssoc(array $arr): bool {
        return !array_is_list($arr);
    }

    private function request(string $method, string $path, ?array $body = null): array {
        $url = rtrim($this->config['api_url'], '/') . $path;

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'x-client-id: ' . $this->config['client_id'],
            'x-api-key: ' . $this->config['api_key'],
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Lỗi kết nối PayOS: ' . $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new Exception('Phản hồi PayOS không hợp lệ (HTTP ' . $httpCode . ')');
        }

        return $decoded;
    }
}
