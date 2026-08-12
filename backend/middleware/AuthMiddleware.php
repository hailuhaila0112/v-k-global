<?php
// Simple JWT Authentication Middleware
require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    public static function handle() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            Response::send(false, "Yêu cầu quyền truy cập (Token không tồn tại)", null, 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $decoded = JWT::decode($token);

        if (!$decoded) {
            Response::send(false, "Token không hợp lệ hoặc đã hết hạn", null, 401);
        }

        return $decoded; // Returns user payload
    }
}
