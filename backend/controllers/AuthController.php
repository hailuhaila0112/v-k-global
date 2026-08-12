
<?php
// Auth Controller to handle user authentication
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/JWT.php';

class AuthController extends Controller {
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

    public function login() {
        $body = $this->getRequestBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, "Email không hợp lệ", null, 400);
        }

        if (empty($password)) {
            $this->sendResponse(false, "Mật khẩu không được để trống", null, 400);
        }

        try {
            if ($this->userModel === null) {
                throw new Exception("Lỗi kết nối cơ sở dữ liệu");
            }
            $user = $this->userModel->getByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                // Generate JWT Token
                $token = JWT::encode([
                    "id" => $user['id'],
                    "email" => $user['email'],
                    "role" => $user['role_name'] ?? 'customer'
                ]);

                $this->sendResponse(true, "Đăng nhập thành công", [
                    "id" => (int)$user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "role" => $user['role_name'] ?? 'customer',
                    "token" => $token
                ]);
            } else {
                $this->sendResponse(false, "Email hoặc mật khẩu không chính xác", null, 401);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi hệ thống: " . $e->getMessage(), null, 500);
        }
    }

    public function register() {
        $body = $this->getRequestBody();
        $name = $body['name'] ?? '';
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';
        $phone = $body['phone'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $this->sendResponse(false, "Vui lòng điền đầy đủ thông tin bắt buộc", null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(false, "Email không hợp lệ", null, 400);
        }

        try {
            if ($this->userModel === null) {
                throw new Exception("Lỗi kết nối cơ sở dữ liệu");
            }
            // Check if email already exists
            $existingUser = $this->userModel->getByEmail($email);
            if ($existingUser) {
                $this->sendResponse(false, "Email này đã được đăng ký sử dụng", null, 400);
            }

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Create user with default role_id = 2 (customer)
            if ($this->userModel->create($name, $email, $hashedPassword, $phone, 2)) {
                $this->sendResponse(true, "Đăng ký tài khoản thành công!");
            } else {
                $this->sendResponse(false, "Không thể tạo tài khoản", null, 500);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi hệ thống: " . $e->getMessage(), null, 500);
        }
    }
}