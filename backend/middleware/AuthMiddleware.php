<?php
// Simple JWT Authentication Middleware
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    public static function handle() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!$headers) $headers = [];

        // Normalize header keys
        $normalized = [];
        foreach ($headers as $k => $v) {
            $normalized[strtolower($k)] = $v;
        }

        $authHeader = $normalized['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (empty($authHeader)) {
            Response::send(false, "Yêu cầu quyền truy cập (Token không tồn tại)", null, 401);
        }

        $token = preg_replace('/^Bearer\s+/i', '', trim($authHeader));
        $decoded = JWT::decode($token);

        if (!$decoded) {
            Response::send(false, "Token không hợp lệ hoặc đã hết hạn", null, 401);
        }

        return $decoded; // Returns user payload
    }
}
