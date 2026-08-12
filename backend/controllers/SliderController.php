<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../models/Slider.php';

class SliderController extends Controller {
    private $slider;

    public function __construct() {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $this->slider = new Slider($db);
        } catch (Exception $e) {
            $this->slider = null;
        }
    }

    private function requireAdmin() {
        $user = AuthMiddleware::handle();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::send(false, "Bạn không có quyền truy cập", null, 403);
        }
        return $user;
    }

    public function getAll() {
        try {
            if ($this->slider === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $result = $this->slider->getAll();
            $this->sendResponse(true, "Lấy danh sách slider thành công", $result);
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function create() {
        try {
            $this->requireAdmin();
            if ($this->slider === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);

            $image = trim($body['image'] ?? '');
            $icon = trim($body['icon'] ?? '');
            $title = trim($body['title'] ?? '');
            $description = trim($body['description'] ?? '');
            $sort_order = intval($body['sort_order'] ?? 0);
            $status = intval($body['status'] ?? 1);

            if (empty($title)) {
                $this->sendResponse(false, "Tiêu đề không được để trống", null, 400);
                return;
            }

            if ($this->slider->create($image, $icon, $title, $description, $sort_order, $status)) {
                $this->sendResponse(true, "Thêm slider thành công");
            } else {
                $this->sendResponse(false, "Không thể thêm slider", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function update() {
        try {
            $this->requireAdmin();
            if ($this->slider === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $id = intval($body['id'] ?? 0);
            if (!$id) {
                $this->sendResponse(false, "Thiếu ID slider", null, 400);
                return;
            }

            // Partial update: only update fields that are provided
            $fields = [];
            if (isset($body['image'])) $fields['image'] = trim($body['image']);
            if (isset($body['icon'])) $fields['icon'] = trim($body['icon']);
            if (isset($body['title'])) $fields['title'] = trim($body['title']);
            if (isset($body['description'])) $fields['description'] = trim($body['description']);
            if (isset($body['sort_order'])) $fields['sort_order'] = intval($body['sort_order']);
            if (isset($body['status'])) $fields['status'] = intval($body['status']);

            // Title validation only when title is being updated
            if (isset($fields['title']) && empty($fields['title'])) {
                $this->sendResponse(false, "Tiêu đề không được để trống", null, 400);
                return;
            }

            if (empty($fields)) {
                $this->sendResponse(false, "Không có dữ liệu cập nhật", null, 400);
                return;
            }

            if ($this->slider->updatePartial($id, $fields)) {
                $this->sendResponse(true, "Cập nhật slider thành công");
            } else {
                $this->sendResponse(false, "Không thể cập nhật slider", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function reorder() {
        try {
            $this->requireAdmin();
            if ($this->slider === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $items = $body['items'] ?? [];
            if (empty($items)) {
                $this->sendResponse(false, "Không có dữ liệu sắp xếp", null, 400);
                return;
            }
            foreach ($items as $item) {
                $id = intval($item['id'] ?? 0);
                $sort_order = intval($item['sort_order'] ?? 0);
                if ($id) {
                    $this->slider->updatePartial($id, ['sort_order' => $sort_order]);
                }
            }
            $this->sendResponse(true, "Sắp xếp slider thành công");
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function delete() {
        try {
            $this->requireAdmin();
            if ($this->slider === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $id = intval($body['id'] ?? 0);
            if (!$id) {
                $this->sendResponse(false, "Thiếu ID slider", null, 400);
                return;
            }

            if ($this->slider->delete($id)) {
                $this->sendResponse(true, "Xóa slider thành công");
            } else {
                $this->sendResponse(false, "Không thể xóa slider", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }
}
