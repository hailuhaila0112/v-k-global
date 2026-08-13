<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php_error.log');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            "success" => false,
            "message" => "Lỗi PHP: " . $error['message'] . " tại " . $error['file'] . ":" . $error['line']
        ], JSON_UNESCAPED_UNICODE);
        if (ob_get_level()) ob_end_flush();
    }
});

try {
  // Load .env (PayOS credentials, etc.)
  require_once __DIR__ . '/../config/Env.php';
  Env::load();

  // Entry point for RESTful API routing
  require_once __DIR__ . '/../middleware/CorsMiddleware.php';
  CorsMiddleware::handle();

  $requestUri = $_SERVER['REQUEST_URI'];
  $requestMethod = $_SERVER['REQUEST_METHOD'];

  require_once __DIR__ . '/../routes/api.php';

  $router = new Router();

// Auth Routes
$router->add('POST', '/api/auth/login', 'AuthController@login');
$router->add('POST', '/api/auth/register', 'AuthController@register');

// Product Routes
$router->add('GET', '/api/products', 'ProductController@getAll');
$router->add('GET', '/api/products/{id}', 'ProductController@getById');

// Slider Routes
$router->add('GET', '/api/sliders', 'SliderController@getAll');
$router->add('GET', '/api/admin/sliders', 'SliderController@getAll');
$router->add('POST', '/api/admin/sliders', 'SliderController@create');
$router->add('PUT', '/api/admin/sliders', 'SliderController@update');
$router->add('PUT', '/api/admin/sliders/reorder', 'SliderController@reorder');
$router->add('DELETE', '/api/admin/sliders', 'SliderController@delete');

// Project Routes
$router->add('GET', '/api/projects', 'ProjectController@getAll');
$router->add('GET', '/api/projects/{id}', 'ProjectController@getById');

// Admin Project CRUD
$router->add('GET', '/api/admin/projects', 'ProjectController@getAll');
$router->add('POST', '/api/admin/projects', 'ProjectController@create');
$router->add('PUT', '/api/admin/projects', 'ProjectController@update');
$router->add('DELETE', '/api/admin/projects', 'ProjectController@delete');

// Contact Routes
$router->add('POST', '/api/contact', 'ContactController@submit');

// Chatbot Route
$router->add('POST', '/api/chat', 'ChatController@respond');

// Order Routes
$router->add('POST', '/api/orders/checkout', 'OrderController@checkout');
$router->add('GET', '/api/orders/my', 'OrderController@getMyOrders');

// PayOS Payment Routes
$router->add('POST', '/api/payments/payos/webhook', 'PaymentController@webhook');
$router->add('GET', '/api/payments/payos/status/{orderCode}', 'PaymentController@getStatus');
$router->add('GET', '/api/payments/payos/qr/{orderCode}', 'PaymentController@getQrInfo');

// Admin Dashboard Routes
$router->add('GET', '/api/admin/stats', 'DashboardController@getStats');
$router->add('GET', '/api/admin/orders', 'DashboardController@getOrders');
$router->add('PUT', '/api/admin/orders/status', 'DashboardController@updateOrderStatus');
$router->add('GET', '/api/admin/users', 'UserController@getAll');

// Admin User CRUD
$router->add('POST', '/api/admin/users/create', 'UserController@create');
$router->add('PUT', '/api/admin/users/update', 'UserController@update');
$router->add('DELETE', '/api/admin/users/delete', 'UserController@delete');

// Admin Contact Management
$router->add('GET', '/api/admin/messages', 'ContactController@getAll');
$router->add('POST', '/api/admin/messages/reply', 'ContactController@reply');
$router->add('DELETE', '/api/admin/messages/delete', 'ContactController@delete');

// Admin Product CRUD
$router->add('GET', '/api/admin/products', 'DashboardController@getAllProducts');
$router->add('POST', '/api/admin/products', 'DashboardController@createProduct');
$router->add('PUT', '/api/admin/products', 'DashboardController@updateProduct');
$router->add('DELETE', '/api/admin/products', 'DashboardController@deleteProduct');
$router->add('GET', '/api/admin/categories', 'DashboardController@getCategories');
$router->add('GET', '/api/admin/brands', 'DashboardController@getBrands');

$router->handle($requestMethod, $requestUri);

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "Lỗi máy chủ: " . $e->getMessage() . " tại " . $e->getFile() . ":" . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
