<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../models/User.php';

class UserController extends Controller {
    private $db;
    private $userModel;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->userModel = new User($this->db);
        } catch (Exception $e) {
            $this->db = null;
            $this->userModel = null;
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
        $this->requireAdmin();
        if ($this->userModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $users = $this->userModel->getAll();
        $this->sendResponse(true, "Danh sách người dùng", $users);
    }

    public function create() {
        $this->requireAdmin();
        if ($this->userModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $phone = trim($body['phone'] ?? '');
        $role_id = (int)($body['role_id'] ?? 2);

        if (empty($name) || empty($email) || empty($password)) {
            $this->sendResponse(false, "Vui lòng nhập tên, email và mật khẩu", null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, "Email không hợp lệ", null, 400);
        }

        if ($this->userModel->getByEmail($email)) {
            $this->sendResponse(false, "Email đã tồn tại", null, 409);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        if ($this->userModel->create($name, $email, $hashed, $phone, $role_id)) {
            $this->sendResponse(true, "Thêm người dùng thành công", ["id" => (int)$this->db->lastInsertId()]);
        } else {
            $this->sendResponse(false, "Không thể thêm người dùng", null, 500);
        }
    }

    public function update() {
        $this->requireAdmin();
        if ($this->userModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID người dùng", null, 400);
        }

        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $phone = trim($body['phone'] ?? '');
        $role_id = (int)($body['role_id'] ?? 2);
        $password = $body['password'] ?? '';

        if (empty($name) || empty($email)) {
            $this->sendResponse(false, "Vui lòng nhập tên và email", null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, "Email không hợp lệ", null, 400);
        }

        // Check email uniqueness (exclude self)
        $existing = $this->userModel->getByEmail($email);
        if ($existing && (int)$existing['id'] !== (int)$id) {
            $this->sendResponse(false, "Email đã được sử dụng bởi người dùng khác", null, 409);
        }

        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $this->userModel->updatePassword($id, $hashed);
        }

        if ($this->userModel->update($id, $name, $email, $phone, $role_id)) {
            $this->sendResponse(true, "Cập nhật người dùng thành công");
        } else {
            $this->sendResponse(false, "Không thể cập nhật người dùng", null, 500);
        }
    }

    public function delete() {
        $this->requireAdmin();
        if ($this->userModel === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID người dùng", null, 400);
        }

        if ($this->userModel->delete($id)) {
            $this->sendResponse(true, "Xóa người dùng thành công");
        } else {
            $this->sendResponse(false, "Không thể xóa người dùng", null, 500);
        }
    }
}
