<?php
// Order Controller to handle checkout and order history
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class OrderController extends Controller {
    private $db;
    private $orderModel;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->orderModel = new Order($this->db);
        } catch (Exception $e) {
            $this->db = null;
            $this->orderModel = null;
        }
    }

    public function checkout() {
        $user = AuthMiddleware::handle();
        $body = $this->getRequestBody();

        if ($this->orderModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $totalAmount = $body['total_amount'] ?? 0;
        $paymentMethod = $body['payment_method'] ?? 'COD';
        $shippingAddress = $body['shipping_address'] ?? '';
        $items = $body['items'] ?? [];

        if (empty($shippingAddress) || empty($items)) {
            $this->sendResponse(false, "Vui lòng cung cấp địa chỉ giao hàng và danh sách sản phẩm", null, 400);
        }

        $result = $this->orderModel->create($user['id'], $totalAmount, $paymentMethod, $shippingAddress, $items);

        if ($result['success']) {
            $this->sendResponse(true, "Đặt hàng thành công!", [
                "order_id" => (int)$result['order_id'],
                "order_code" => $result['order_code']
            ]);
        } else {
            $this->sendResponse(false, "Lỗi đặt hàng: " . $result['message'], null, 500);
        }
    }

    public function getMyOrders() {
        $user = AuthMiddleware::handle();

        if ($this->orderModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $orders = $this->orderModel->getByUserId($user['id']);
        $this->sendResponse(true, "Lấy lịch sử đơn hàng thành công", $orders);
    }
}
