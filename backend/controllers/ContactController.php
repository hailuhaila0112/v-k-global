<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../models/Contact.php';

class ContactController extends Controller {
    private $db;
    private $contactModel;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->contactModel = new Contact($this->db);
        } catch (Exception $e) {
            $this->db = null;
            $this->contactModel = null;
        }
    }

    private function requireAdmin() {
        $user = AuthMiddleware::handle();
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            Response::send(false, "Bạn không có quyền truy cập", null, 403);
        }
        return $user;
    }

    // Public: contact form submission
    public function submit() {
        $body = $this->getRequestBody();
        $name = $body['name'] ?? '';
        $email = $body['email'] ?? '';
        $message = $body['message'] ?? '';

        if (empty($name) || empty($email) || empty($message)) {
            $this->sendResponse(false, "Vui lòng điền đầy đủ thông tin", null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, "Email không hợp lệ", null, 400);
        }

        if ($this->contactModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        if ($this->contactModel->create($name, $email, $message)) {
            $this->sendResponse(true, "Gửi liên hệ thành công! Chúng tôi sẽ phản hồi sớm nhất.");
        } else {
            $this->sendResponse(false, "Không thể gửi liên hệ", null, 500);
        }
    }

    // Admin: get all messages
    public function getAll() {
        $this->requireAdmin();
        if ($this->contactModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $messages = $this->contactModel->getAll();
        $this->sendResponse(true, "Danh sách tin nhắn", $messages);
    }

    // Admin: get single message
    public function getById() {
        $this->requireAdmin();
        if ($this->contactModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $body = $this->getRequestBody();
        $id = $body['id'] ?? ($_GET['id'] ?? null);
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID tin nhắn", null, 400);
        }
        $message = $this->contactModel->getById($id);
        if ($message) {
            $this->sendResponse(true, "Chi tiết tin nhắn", $message);
        } else {
            $this->sendResponse(false, "Không tìm thấy tin nhắn", null, 404);
        }
    }

    // Admin: reply to message
    public function reply() {
        $this->requireAdmin();
        if ($this->contactModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        $reply = trim($body['reply'] ?? '');

        if (!$id || empty($reply)) {
            $this->sendResponse(false, "Thiếu ID tin nhắn hoặc nội dung phản hồi", null, 400);
        }

        if ($this->contactModel->updateReply($id, $reply)) {
            $this->sendResponse(true, "Phản hồi tin nhắn thành công");
        } else {
            $this->sendResponse(false, "Không thể phản hồi tin nhắn", null, 500);
        }
    }

    // Admin: delete message
    public function delete() {
        $this->requireAdmin();
        if ($this->contactModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID tin nhắn", null, 400);
        }

        if ($this->contactModel->delete($id)) {
            $this->sendResponse(true, "Xóa tin nhắn thành công");
        } else {
            $this->sendResponse(false, "Không thể xóa tin nhắn", null, 500);
        }
    }
}
