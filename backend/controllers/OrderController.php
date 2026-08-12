<?php
// Order Controller to handle checkout and order history
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/PayOS.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../services/PayOSService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class OrderController extends Controller {
    private $db;
    private $orderModel;
    private $payosService;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->orderModel = new Order($this->db);
            $this->payosService = new PayOSService();
        } catch (Exception $e) {
            $this->db = null;
            $this->orderModel = null;
            $this->payosService = null;
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

        $isPayOS = strcasecmp($paymentMethod, 'PayOS') === 0;
        $paymentStatus = $isPayOS ? 'pending' : 'unpaid';

        if ($isPayOS && !PayOSConfig::isConfigured()) {
            $this->sendResponse(false, "PayOS chưa được cấu hình. Vui lòng cập nhật backend/config/PayOS.php", null, 503);
        }

        $result = $this->orderModel->create(
            $user['id'],
            $totalAmount,
            $paymentMethod,
            $shippingAddress,
            $items,
            $paymentStatus
        );

        if (!$result['success']) {
            $this->sendResponse(false, "Lỗi đặt hàng: " . $result['message'], null, 500);
        }

        $orderId = (int) $result['order_id'];
        $responseData = [
            "order_id"   => $orderId,
            "order_code" => $result['order_code'],
        ];

        if ($isPayOS) {
            try {
                $payosOrderCode = (int) (time() + $orderId);
                $amount = (int) round((float) $totalAmount);
                $description = 'VK' . str_pad((string) $orderId, 7, '0', STR_PAD_LEFT);
                if (strlen($description) > 25) {
                    $description = substr($description, 0, 25);
                }

                $payosItems = array_map(function ($item) {
                    return [
                        'name'     => 'SP ' . $item['product_id'],
                        'quantity' => (int) $item['quantity'],
                        'price'    => (int) round((float) $item['price']),
                    ];
                }, $items);

                $paymentData = $this->payosService->createPaymentLink(
                    $payosOrderCode,
                    $amount,
                    $description,
                    $payosItems
                );

                $this->orderModel->updatePayosInfo(
                    $orderId,
                    $payosOrderCode,
                    $paymentData['paymentLinkId'] ?? null
                );

                $responseData['checkout_url'] = $paymentData['checkoutUrl'] ?? null;
                $responseData['payos_order_code'] = $payosOrderCode;

                if (empty($responseData['checkout_url'])) {
                    $this->sendResponse(false, "Không nhận được link thanh toán từ PayOS", null, 500);
                }

                $this->sendResponse(true, "Tạo đơn hàng thành công! Chuyển đến trang thanh toán...", $responseData);
            } catch (Exception $e) {
                $this->sendResponse(false, "Lỗi tạo link PayOS: " . $e->getMessage(), [
                    "order_id"   => $orderId,
                    "order_code" => $result['order_code'],
                ], 500);
            }
        }

        $this->sendResponse(true, "Đặt hàng thành công!", $responseData);
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
