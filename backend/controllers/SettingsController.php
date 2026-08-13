<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ShippingRate.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class SettingsController extends Controller {
    private $db;
    private $shippingModel;
    private $initError = null;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            if ($this->db === null) {
                $this->initError = 'Không kết nối được database';
                return;
            }
            $this->shippingModel = new ShippingRate($this->db);
            if (!empty($this->shippingModel->lastError)) {
                // Table may still work on next call; keep model
                error_log('ShippingRate warning: ' . $this->shippingModel->lastError);
            }
        } catch (Throwable $e) {
            $this->initError = $e->getMessage();
            $this->db = null;
            $this->shippingModel = null;
            error_log('SettingsController init error: ' . $e->getMessage());
        }
    }

    private function requireAdmin() {
        $user = AuthMiddleware::handle();
        $role = $user['role'] ?? '';
        if (!$user || $role !== 'admin') {
            Response::send(false, "Bạn không có quyền truy cập (role: " . $role . ")", null, 403);
        }
        return $user;
    }

    private function requireModel() {
        if ($this->shippingModel === null) {
            $this->sendResponse(
                false,
                "Lỗi kết nối / khởi tạo phí vận chuyển" . ($this->initError ? (': ' . $this->initError) : ''),
                null,
                500
            );
        }
    }

    /** Public: phí mặc định cho cart/checkout */
    public function getShipping() {
        $this->requireModel();
        $this->sendResponse(true, "Lấy cấu hình vận chuyển thành công", $this->shippingModel->toPublicShipping());
    }

    /** Public: danh sách phí đang bật */
    public function getActiveRates() {
        $this->requireModel();
        $rates = $this->shippingModel->getActive();
        $this->sendResponse(true, "Lấy danh sách phí vận chuyển thành công", $rates);
    }

    /** Admin: list all */
    public function getShippingRates() {
        $this->requireAdmin();
        $this->requireModel();
        $rates = $this->shippingModel->getAll();
        if ($rates === [] && !empty($this->shippingModel->lastError)) {
            $this->sendResponse(false, "Không đọc được bảng shipping_rates: " . $this->shippingModel->lastError, null, 500);
        }
        $this->sendResponse(true, "Lấy danh sách phí vận chuyển thành công", $rates);
    }

    /** Admin: create */
    public function createShippingRate() {
        $this->requireAdmin();
        $this->requireModel();

        $body = $this->getRequestBody() ?: [];
        $name = trim($body['name'] ?? '');
        if ($name === '') {
            $this->sendResponse(false, "Vui lòng nhập tên gói vận chuyển", null, 400);
        }

        $result = $this->shippingModel->create(
            $name,
            $body['fee'] ?? 0,
            $body['free_shipping_threshold'] ?? 0,
            array_key_exists('is_active', $body) ? (int) $body['is_active'] : 1,
            !empty($body['is_default']) ? 1 : 0
        );

        if ($result['success']) {
            $this->sendResponse(true, "Đã thêm phí vận chuyển", $this->shippingModel->getById($result['id']));
        }
        $this->sendResponse(false, "Không thể thêm: " . ($result['message'] ?? ''), null, 500);
    }

    /** Admin: update */
    public function updateShippingRate() {
        $this->requireAdmin();
        $this->requireModel();

        $body = $this->getRequestBody() ?: [];
        $id = (int) ($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        if ($id <= 0 || $name === '') {
            $this->sendResponse(false, "Thiếu id hoặc tên gói vận chuyển", null, 400);
        }

        if (!$this->shippingModel->getById($id)) {
            $this->sendResponse(false, "Không tìm thấy phí vận chuyển", null, 404);
        }

        $result = $this->shippingModel->update(
            $id,
            $name,
            $body['fee'] ?? 0,
            $body['free_shipping_threshold'] ?? 0,
            isset($body['is_active']) ? (int) $body['is_active'] : 1,
            !empty($body['is_default'])
        );

        if ($result['success']) {
            $this->sendResponse(true, "Đã cập nhật phí vận chuyển", $this->shippingModel->getById($id));
        }
        $this->sendResponse(false, "Không thể cập nhật: " . ($result['message'] ?? ''), null, 500);
    }

    /** Admin: delete */
    public function deleteShippingRate() {
        $this->requireAdmin();
        $this->requireModel();

        $body = $this->getRequestBody() ?: [];
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            $this->sendResponse(false, "Thiếu id", null, 400);
        }

        $result = $this->shippingModel->delete($id);
        if ($result['success']) {
            $this->sendResponse(true, "Đã xóa phí vận chuyển", null);
        }
        $this->sendResponse(false, $result['message'] ?? "Không thể xóa", null, 500);
    }

    /** Legacy admin endpoints */
    public function getShippingAdmin() {
        $this->getShippingRates();
    }

    public function updateShipping() {
        $this->createShippingRate();
    }
}
