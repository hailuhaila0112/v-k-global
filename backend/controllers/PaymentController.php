<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/PayOS.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../services/PayOSService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class PaymentController extends Controller {
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

    /**
     * Webhook nhận thông báo thanh toán từ PayOS (không cần auth)
     */
    public function webhook() {
        if ($this->orderModel === null) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid payload']);
            exit;
        }

        try {
            if (!PayOSConfig::isConfigured()) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'PayOS chưa được cấu hình']);
                exit;
            }

            $data = $this->payosService->verifyWebhook($body);
            $orderCode = (int) ($data['orderCode'] ?? 0);

            if ($orderCode <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing orderCode']);
                exit;
            }

            $order = $this->orderModel->getByPayosOrderCode($orderCode);
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Order not found']);
                exit;
            }

            // Idempotent: đã thanh toán thì bỏ qua
            if ($order['payment_status'] === 'paid') {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Already processed']);
                exit;
            }

            $this->orderModel->markAsPaid(
                (int) $order['id'],
                $data['paymentLinkId'] ?? null,
                $data['reference'] ?? null
            );

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'OK']);
            exit;

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Kiểm tra trạng thái thanh toán (cho trang success)
     */
    public function getStatus($orderCode) {
        $user = AuthMiddleware::handle();

        if ($this->orderModel === null) {
            $this->sendResponse(false, 'Lỗi kết nối cơ sở dữ liệu', null, 500);
        }

        $order = $this->orderModel->getByPayosOrderCode((int) $orderCode);
        if (!$order || (int) $order['user_id'] !== (int) $user['id']) {
            $this->sendResponse(false, 'Không tìm thấy đơn hàng', null, 404);
        }

        // Nếu chưa paid, thử đồng bộ từ PayOS
        if ($order['payment_status'] !== 'paid' && PayOSConfig::isConfigured() && !empty($order['payos_order_code'])) {
            try {
                $paymentInfo = $this->payosService->getPaymentInfo((int) $order['payos_order_code']);
                if (($paymentInfo['status'] ?? '') === 'PAID') {
                    $this->orderModel->markAsPaid((int) $order['id'], $paymentInfo['id'] ?? null, null);
                    $order = $this->orderModel->getById((int) $order['id']);
                }
            } catch (Exception $e) {
                // Giữ nguyên trạng thái hiện tại nếu không lấy được từ PayOS
            }
        }

        $this->sendResponse(true, 'Lấy trạng thái thành công', [
            'order_code'      => $order['order_code'],
            'payment_status'  => $order['payment_status'],
            'status'          => $order['status'],
            'total_amount'    => $order['total_amount'],
        ]);
    }
}
