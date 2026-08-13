<?php
// Simple REST API Router for V.K Global Shop
class Router {
    private $routes = [];

    public function add($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function handle($requestMethod, $requestUri) {
        // Strip query string
        $path = parse_url($requestUri, PHP_URL_PATH);

        // Đường dẫn project trên localhost
        $basePath = '/DATTDN/backend/public';

        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }

        // Fallback: lấy phần bắt đầu từ /api/...
        if (strpos($path, '/api/') !== false) {
            $path = substr($path, strpos($path, '/api/'));
        }

        // Nếu path không bắt đầu bằng / sau khi strip, thêm / vào đầu
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) continue;

            // Convert route path to regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match
                
                // Instantiate controller and call method
                list($controllerName, $methodName) = explode('@', $route['handler']);
                require_once __DIR__ . '/../controllers/' . $controllerName . '.php';
                $controller = new $controllerName();
                call_user_func_array([$controller, $methodName], $matches);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(["success" => false, "message" => "API Route not found: " . $path], JSON_UNESCAPED_UNICODE);
    }
}
