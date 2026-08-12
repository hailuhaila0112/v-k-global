<?php
// Base Controller to handle JSON responses and common utilities
class Controller {
    public function sendResponse($success, $message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => $success,
            "message" => $message,
            "data" => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    public function getRequestBody() {
        return json_decode(file_get_contents('php://input'), true);
    }
}
