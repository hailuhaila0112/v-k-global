<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class ProjectController extends Controller {
    private $db;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            $this->db = null;
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
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $query = "SELECT * FROM projects ORDER BY id DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($projects as &$p) {
                if (isset($p['technologies'])) $p['technologies'] = json_decode($p['technologies'], true);
            }

            $this->sendResponse(true, "Lấy danh sách dự án thành công", $projects);
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function getById($id) {
        try {
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $query = "SELECT * FROM projects WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                $this->sendResponse(false, "Không tìm thấy dự án", null, 404);
                return;
            }
            if (isset($project['technologies'])) {
                $project['technologies'] = json_decode($project['technologies'], true);
            }
            $this->sendResponse(true, "Lấy thông tin dự án thành công", $project);
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function create() {
        try {
            $this->requireAdmin();
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);

            $name = trim($body['name'] ?? '');
            $category = trim($body['category'] ?? '');
            $image = trim($body['image'] ?? '');
            $description = trim($body['description'] ?? '');
            $technologies = isset($body['technologies']) ? json_encode($body['technologies']) : '[]';
            $progress = trim($body['progress'] ?? '0%');
            $status = trim($body['status'] ?? 'Đang phát triển');

            if (empty($name)) {
                $this->sendResponse(false, "Tên dự án không được để trống", null, 400);
                return;
            }

            $query = "INSERT INTO projects (name, category, image, description, technologies, progress, status) 
                      VALUES (:name, :category, :image, :description, :technologies, :progress, :status)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':technologies', $technologies);
            $stmt->bindParam(':progress', $progress);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $id = $this->db->lastInsertId();
                $project = $this->getByIdRaw($id);
                $this->sendResponse(true, "Thêm dự án thành công", $project);
            } else {
                $this->sendResponse(false, "Không thể thêm dự án", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function update() {
        try {
            $this->requireAdmin();
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $id = $body['id'] ?? null;

            if (!$id) {
                $this->sendResponse(false, "Thiếu ID dự án", null, 400);
                return;
            }

            $name = trim($body['name'] ?? '');
            $category = trim($body['category'] ?? '');
            $image = trim($body['image'] ?? '');
            $description = trim($body['description'] ?? '');
            $technologies = isset($body['technologies']) ? json_encode($body['technologies']) : '[]';
            $progress = trim($body['progress'] ?? '0%');
            $status = trim($body['status'] ?? 'Đang phát triển');

            if (empty($name)) {
                $this->sendResponse(false, "Tên dự án không được để trống", null, 400);
                return;
            }

            $query = "UPDATE projects SET name=:name, category=:category, image=:image, 
                      description=:description, technologies=:technologies, progress=:progress, status=:status 
                      WHERE id=:id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':technologies', $technologies);
            $stmt->bindParam(':progress', $progress);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $project = $this->getByIdRaw($id);
                $this->sendResponse(true, "Cập nhật dự án thành công", $project);
            } else {
                $this->sendResponse(false, "Không thể cập nhật dự án", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function delete() {
        try {
            $this->requireAdmin();
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            $id = $body['id'] ?? null;
            if (!$id) {
                $this->sendResponse(false, "Thiếu ID dự án", null, 400);
                return;
            }
            $query = "DELETE FROM projects WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->sendResponse(true, "Xóa dự án thành công");
            } else {
                $this->sendResponse(false, "Không thể xóa dự án", null, 500);
            }
        } catch (\Throwable $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    private function getByIdRaw($id) {
        $query = "SELECT * FROM projects WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p && isset($p['technologies'])) {
            $p['technologies'] = json_decode($p['technologies'], true);
        }
        return $p;
    }
}
