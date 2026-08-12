<?php
// Product Controller to handle REST API requests for products
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';

class ProductController extends Controller {
    private $db;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            $this->db = null;
        }
    }

    public function getAll() {
        try {
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN brands b ON p.brand_id = b.id
                      WHERE p.status = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($products as &$p) {
                if (isset($p['features'])) $p['features'] = json_decode($p['features'], true);
                if (isset($p['specs'])) $p['specs'] = json_decode($p['specs'], true);
            }

            $this->sendResponse(true, "Lấy danh sách sản phẩm thành công", $products);
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function getById($id) {
        try {
            if ($this->db === null) {
                $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
                return;
            }
            $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      LEFT JOIN brands b ON p.brand_id = b.id
                      WHERE p.id = :id AND p.status = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                if (isset($product['features'])) $product['features'] = json_decode($product['features'], true);
                if (isset($product['specs'])) $product['specs'] = json_decode($product['specs'], true);
                $this->sendResponse(true, "Lấy chi tiết sản phẩm thành công", $product);
            } else {
                $this->sendResponse(false, "Không tìm thấy sản phẩm", null, 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }
}
