<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class SettingsController extends Controller {
    private $db;
    private $settingModel;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->settingModel = new Setting($this->db);
        } catch (Exception $e) {
            $this->db = null;
            $this->settingModel = null;
        }
    }

    private function requireAdmin() {
        $user = AuthMiddleware::handle();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::send(false, "Bạn không có quyền truy cập", null, 403);
        }
        return $user;
    }

    /** Public: cart / checkout */
    public function getShipping() {
        if ($this->settingModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $this->sendResponse(true, "Lấy cấu hình vận chuyển thành công", $this->settingModel->getShipping());
    }

    /** Admin: đọc cấu hình */
    public function getShippingAdmin() {
        $this->requireAdmin();
        if ($this->settingModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $this->sendResponse(true, "Lấy cấu hình vận chuyển thành công", $this->settingModel->getShipping());
    }

    /** Admin: cập nhật */
    public function updateShipping() {
        $this->requireAdmin();
        if ($this->settingModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody() ?: [];
        if (!isset($body['shipping_fee']) || !isset($body['free_shipping_threshold'])) {
            $this->sendResponse(false, "Vui lòng nhập đầy đủ phí vận chuyển và ngưỡng miễn phí", null, 400);
        }

        $fee = (float) $body['shipping_fee'];
        $threshold = (float) $body['free_shipping_threshold'];

        if ($fee < 0 || $threshold < 0) {
            $this->sendResponse(false, "Giá trị không được âm", null, 400);
        }

        if ($this->settingModel->updateShipping($fee, $threshold)) {
            $this->sendResponse(true, "Đã cập nhật cấu hình vận chuyển", $this->settingModel->getShipping());
        }

        $this->sendResponse(false, "Không thể cập nhật cấu hình", null, 500);
    }
}
