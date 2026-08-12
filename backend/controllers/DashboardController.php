<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class DashboardController extends Controller {
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

    public function getStats() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $totalUsers = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalOrders = $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $totalProducts = $this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $totalRevenue = $this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
        $totalProjects = $this->db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $totalContacts = $this->db->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

        $ordersByStatus = $this->db->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

        $monthlyRevenue = $this->db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_amount), 0) as revenue
            FROM orders WHERE status != 'Cancelled'
            GROUP BY month ORDER BY month DESC LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        $recentOrders = $this->db->query("
            SELECT o.id, o.order_code, o.total_amount, o.status, o.payment_method, o.created_at,
                   u.name as user_name, u.email as user_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.id DESC LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->sendResponse(true, "Thống kê dashboard", [
            "totalUsers" => (int)$totalUsers,
            "totalOrders" => (int)$totalOrders,
            "totalProducts" => (int)$totalProducts,
            "totalRevenue" => (float)$totalRevenue,
            "totalProjects" => (int)$totalProjects,
            "totalContacts" => (int)$totalContacts,
            "ordersByStatus" => $ordersByStatus,
            "monthlyRevenue" => $monthlyRevenue,
            "recentOrders" => $recentOrders
        ]);
    }

    public function getOrders() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $orders = $this->db->query("
            SELECT o.*, u.name as user_name, u.email as user_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $stmt = $this->db->prepare("
                SELECT oi.*, p.name as product_name, p.image as product_image
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id
            ");
            $stmt->bindParam(':order_id', $order['id']);
            $stmt->execute();
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->sendResponse(true, "Danh sách đơn hàng", $orders);
    }

    public function updateOrderStatus() {
        $this->requireAdmin();
        $body = $this->getRequestBody();
        $orderId = $body['order_id'] ?? null;
        $status = $body['status'] ?? '';

        if (!$orderId || !$status) {
            $this->sendResponse(false, "Thiếu thông tin đơn hàng hoặc trạng thái", null, 400);
        }

        $validStatuses = ['Chờ xác nhận', 'Đang xử lý', 'Đang giao hàng', 'Đã giao hàng', 'Đã hủy'];
        if (!in_array($status, $validStatuses)) {
            $this->sendResponse(false, "Trạng thái không hợp lệ", null, 400);
        }

        $stmt = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $orderId);

        if ($stmt->execute()) {
            $this->sendResponse(true, "Cập nhật trạng thái đơn hàng thành công");
        } else {
            $this->sendResponse(false, "Lỗi cập nhật trạng thái", null, 500);
        }
    }

    public function getUsers() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $users = $this->db->query("
            SELECT u.id, u.name, u.email, u.phone, u.avatar, u.created_at,
                   r.name as role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->sendResponse(true, "Danh sách người dùng", $users);
    }

    public function getMessages() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $messages = $this->db->query("
            SELECT * FROM contact_messages ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->sendResponse(true, "Danh sách tin nhắn", $messages);
    }

    // ===== PRODUCT CRUD =====

    public function getAllProducts() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $products = $this->db->query("
            SELECT p.*, c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            ORDER BY p.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$p) {
            if (isset($p['features'])) $p['features'] = json_decode($p['features'], true);
            if (isset($p['specs'])) $p['specs'] = json_decode($p['specs'], true);
        }

        $this->sendResponse(true, "Danh sách sản phẩm", $products);
    }

    public function createProduct() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $name = trim($body['name'] ?? '');
        $slug = trim($body['slug'] ?? '');
        $categoryId = $body['category_id'] ?? null;
        $brandId = $body['brand_id'] ?? null;
        $price = $body['price'] ?? 0;
        $originalPrice = $body['original_price'] ?? null;
        $image = trim($body['image'] ?? '');
        $shortDesc = trim($body['short_description'] ?? '');
        $description = trim($body['description'] ?? '');
        $features = !empty($body['features']) ? json_encode($body['features']) : null;
        $specs = !empty($body['specs']) ? json_encode($body['specs']) : null;
        $stock = (int)($body['stock'] ?? 0);
        $badge = trim($body['badge'] ?? '');
        $status = (int)($body['status'] ?? 1);

        if (empty($name) || empty($slug) || !$categoryId || !$brandId || $price <= 0) {
            $this->sendResponse(false, "Vui lòng nhập đầy đủ thông tin: tên, slug, danh mục, thương hiệu, giá", null, 400);
        }

        if (empty($image)) {
            $image = 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80';
        }

        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name), '-'));
        }

        $query = "INSERT INTO products (slug, name, category_id, brand_id, price, original_price, image, short_description, description, features, specs, stock, badge, status)
                  VALUES (:slug, :name, :category_id, :brand_id, :price, :original_price, :image, :short_description, :description, :features, :specs, :stock, :badge, :status)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':original_price', $originalPrice);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':short_description', $shortDesc);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':features', $features);
        $stmt->bindParam(':specs', $specs);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':badge', $badge);
        $stmt->bindParam(':status', $status);

        try {
            $stmt->execute();
            $this->sendResponse(true, "Thêm sản phẩm thành công", ["id" => (int)$this->db->lastInsertId()]);
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function updateProduct() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID sản phẩm", null, 400);
        }

        $name = trim($body['name'] ?? '');
        $slug = trim($body['slug'] ?? '');
        $categoryId = $body['category_id'] ?? null;
        $brandId = $body['brand_id'] ?? null;
        $price = $body['price'] ?? 0;
        $originalPrice = $body['original_price'] ?? null;
        $image = trim($body['image'] ?? '');
        $shortDesc = trim($body['short_description'] ?? '');
        $description = trim($body['description'] ?? '');
        $features = isset($body['features']) ? json_encode($body['features']) : null;
        $specs = isset($body['specs']) ? json_encode($body['specs']) : null;
        $stock = (int)($body['stock'] ?? 0);
        $badge = trim($body['badge'] ?? '');
        $status = (int)($body['status'] ?? 1);

        $query = "UPDATE products SET slug=:slug, name=:name, category_id=:category_id, brand_id=:brand_id,
                  price=:price, original_price=:original_price, image=:image,
                  short_description=:short_description, description=:description,
                  features=:features, specs=:specs, stock=:stock, badge=:badge, status=:status
                  WHERE id=:id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':brand_id', $brandId);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':original_price', $originalPrice);
        $stmt->bindParam(':image', $image);
        $stmt->bindParam(':short_description', $shortDesc);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':features', $features);
        $stmt->bindParam(':specs', $specs);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':badge', $badge);
        $stmt->bindParam(':status', $status);

        try {
            $stmt->execute();
            $this->sendResponse(true, "Cập nhật sản phẩm thành công");
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function deleteProduct() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }

        $body = $this->getRequestBody();
        $id = $body['id'] ?? null;
        if (!$id) {
            $this->sendResponse(false, "Thiếu ID sản phẩm", null, 400);
        }

        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id);

        try {
            $stmt->execute();
            $this->sendResponse(true, "Xóa sản phẩm thành công");
        } catch (Exception $e) {
            $this->sendResponse(false, "Lỗi: " . $e->getMessage(), null, 500);
        }
    }

    public function getCategories() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $cats = $this->db->query("SELECT * FROM categories ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $this->sendResponse(true, "Danh sách danh mục", $cats);
    }

    public function getBrands() {
        $this->requireAdmin();
        if ($this->db === null) {
            $this->sendResponse(false, "Lỗi kết nối cơ sở dữ liệu", null, 500);
        }
        $brands = $this->db->query("SELECT * FROM brands ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $this->sendResponse(true, "Danh sách thương hiệu", $brands);
    }
}
