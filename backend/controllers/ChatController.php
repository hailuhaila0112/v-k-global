<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../config/Database.php';

class ChatController extends Controller {
    private $db;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            $this->db = null;
        }
    }

    public function respond() {
        $body = $this->getRequestBody();
        $message = trim($body['message'] ?? '');

        if (empty($message)) {
            $this->sendResponse(false, "Tin nhắn không được để trống", null, 400);
        }

        $reply = $this->generateReply($message);
        $this->sendResponse(true, "Phản hồi thành công", ["reply" => $reply]);
    }

    private function getProducts() {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->prepare("SELECT id, name, price, short_description, description, slug FROM products WHERE status = 1 LIMIT 50");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    private function getProjects() {
        if ($this->db === null) return [];
        try {
            $stmt = $this->db->prepare("SELECT name, description, status, progress FROM projects");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    private function searchProducts($keyword, $products) {
        $results = [];
        $kw = mb_strtolower($keyword, 'UTF-8');
        foreach ($products as $p) {
            $name = mb_strtolower($p['name'], 'UTF-8');
            $desc = mb_strtolower($p['short_description'] ?? '', 'UTF-8');
            if (strpos($name, $kw) !== false || strpos($desc, $kw) !== false) {
                $results[] = $p;
            }
        }
        return $results;
    }

    private function formatProduct($p) {
        $price = number_format($p['price'], 0, ',', '.');
        $desc = !empty($p['short_description']) ? ' — ' . $p['short_description'] : '';
        return "<strong>{$p['name']}</strong>: {$price}đ{$desc}";
    }

    private function generateReply($message) {
        $textLower = mb_strtolower($message, 'UTF-8');
        $products = $this->getProducts();
        $projects = $this->getProjects();

        // Greetings
        if (preg_match('/^(chào|hi|hello|hey|\\bai\\b|bot|robot|ê|anh ơi|chị ơi)/u', $textLower)) {
            return "Xin chào! Tôi là trợ lý AI của <strong>V.K Global</strong>.<br><br>Tôi có thể giúp gì cho bạn?<br>• 🚗 Tư vấn xe golf tự hành<br>• 📡 Cảm biến LiDAR, Camera AI<br>• 🔧 Linh kiện điện tử, động cơ<br>• 📋 Thông tin dự án<br>• 📞 Liên hệ tư vấn";
        }

        // Thanks
        if (preg_match('/^(cảm ơn|cám ơn|thank|thanks)/u', $textLower)) {
            return "Cảm ơn bạn đã quan tâm đến V.K Global! Nếu cần thêm thông tin, đừng ngần ngại hỏi tôi nhé 😊";
        }

        // Full product list
        if (preg_match('/(sản phẩm|danh sách|tất cả|list)/u', $textLower) && !preg_match('/(tìm|kiếm|có.*không)/u', $textLower)) {
            if (empty($products)) return "Hiện chưa có sản phẩm nào trong danh sách.";
            $lines = array_map([$this, 'formatProduct'], $products);
            return "Danh sách sản phẩm của V.K Global:<br>" . implode("<br>", $lines);
        }

        // Search products by keyword
        $searchKeywords = ['tìm', 'kiếm', 'có.*không', 'bán', 'giá', 'bao nhiêu', 'mua'];
        $isSearching = false;
        foreach ($searchKeywords as $pattern) {
            if (preg_match("/$pattern/u", $textLower)) { $isSearching = true; break; }
        }

        if ($isSearching) {
            // Extract potential product name (remove search words)
            $searchTerm = preg_replace('/\b(tìm|kiếm|có|không|bán|giá|bao nhiêu|mua|sản phẩm|linh kiện)\b/ui', '', $message);
            $searchTerm = trim($searchTerm);

            if (!empty($searchTerm)) {
                $matched = $this->searchProducts($searchTerm, $products);
                if (!empty($matched)) {
                    $lines = array_map([$this, 'formatProduct'], $matched);
                    return "Kết quả tìm kiếm cho \"{$searchTerm}\":<br>" . implode("<br>", $lines);
                }
            }
        }

        // Specific product category: xe golf / tự hành
        if (preg_match('/(xe golf|tự hành|autodrive|nâng cấp xe)/u', $textLower)) {
            $matched = $this->searchProducts('xe golf', $products);
            if (!empty($matched)) {
                $lines = array_map([$this, 'formatProduct'], $matched);
                return "Sản phẩm xe golf tự hành của V.K Global:<br>" . implode("<br>", $lines);
            }
            return "🚗 <strong>V.K AutoDrive Pro</strong> là giải pháp nâng cấp xe golf truyền thống thành xe tự hành thông minh, sử dụng LiDAR 3D và Camera AI. Liên hệ ngay để được tư vấn chi tiết!";
        }

        // LiDAR
        if (preg_match('/(lidar|quét 3d|cảm biến.*quét|laser)/u', $textLower)) {
            $matched = $this->searchProducts('lidar', $products);
            if (!empty($matched)) {
                $lines = array_map([$this, 'formatProduct'], $matched);
                return "Sản phẩm LiDAR của V.K Global:<br>" . implode("<br>", $lines);
            }
            return "📡 V.K Global cung cấp cảm biến LiDAR 2D và 3D chất lượng cao, phù hợp cho xe tự hành, robot AGV và hệ thống đo lường công nghiệp.";
        }

        // Camera AI
        if (preg_match('/(camera|nhận diện|oak|computer vision|thị giác)/u', $textLower)) {
            $matched = $this->searchProducts('camera', $products);
            if (!empty($matched)) {
                $lines = array_map([$this, 'formatProduct'], $matched);
                return "Sản phẩm Camera AI của V.K Global:<br>" . implode("<br>", $lines);
            }
            return "📷 V.K Global cung cấp Camera AI thông minh hỗ trợ nhận diện vật thể, đo độ sâu thời gian thực — phù hợp cho robot và xe tự hành.";
        }

        // Động cơ / motor
        if (preg_match('/(động cơ|motor|bước|servo|encoder)/u', $textLower)) {
            $matched = $this->searchProducts('động cơ', $products);
            if (!empty($matched)) {
                $lines = array_map([$this, 'formatProduct'], $matched);
                return "Động cơ và phụ kiện tại V.K Global:<br>" . implode("<br>", $lines);
            }
            return "🔧 V.K Global có các loại động cơ bước (Stepper Motor), Servo, Encoder và driver đi kèm phù hợp cho robot và xe tự hành.";
        }

        // Vi điều khiển / board mạch
        if (preg_match('/(vi điều khiển|arduino|raspberry|stm32|esp32|board mạch|mạch)/u', $textLower)) {
            $matched = $this->searchProducts('arduino', $products);
            if (!empty($matched)) {
                $lines = array_map([$this, 'formatProduct'], $matched);
                return "Board mạch vi điều khiển:<br>" . implode("<br>", $lines);
            }
            return "💻 V.K Global phân phối các dòng vi điều khiển Arduino, Raspberry Pi, STM32, ESP32 và các module mở rộng IoT.";
        }

        // Projects
        if (preg_match('/(dự án|triển khai|project)/u', $textLower)) {
            if (!empty($projects)) {
                $lines = [];
                foreach ($projects as $proj) {
                    $lines[] = "• <strong>{$proj['name']}</strong> (Tiến độ: {$proj['progress']}, TT: {$proj['status']})";
                }
                return "Các dự án tiêu biểu của V.K Global:<br>" . implode("<br>", $lines);
            }
            return "V.K Global đang triển khai nhiều dự án xe golf tự hành, hệ thống IoT công nghiệp thực tế. Liên hệ để biết thêm chi tiết!";
        }

        // Contact / support
        if (preg_match('/(liên hệ|hotline|email|tư vấn|kỹ sư|địa chỉ|hỗ trợ)/u', $textLower)) {
            return "📞 <strong>Thông tin liên hệ V.K Global</strong><br>• Hotline: <strong>0987.654.321</strong><br>• Email: <strong>contact@vkglobal.vn</strong><br>• Địa chỉ: Khu Công nghệ Cao, TP. Hồ Chí Minh<br><br>Đội ngũ kỹ sư của chúng tôi sẵn sàng tư vấn giải pháp phù hợp nhất cho bạn!";
        }

        // Price inquiry
        if (preg_match('/(giá|đắt|rẻ|khuyến mãi|giảm)/u', $textLower)) {
            return "💰 Sản phẩm của V.K Global có giá từ <strong>650.000đ</strong> đến <strong>45.000.000đ</strong>.<br><br>Bạn muốn hỏi giá sản phẩm cụ thể nào? Hãy gõ tên sản phẩm để tôi tìm giúp bạn!";
        }

        // Fallback with suggestions
        return "Cảm ơn bạn đã quan tâm đến V.K Global! 🤖<br><br>Tôi có thể giúp bạn:<br>• 🚗 <strong>Xe golf tự hành</strong> — Giải pháp nâng cấp<br>• 📡 <strong>Cảm biến LiDAR</strong> — Quét 3D, đo khoảng cách<br>• 📷 <strong>Camera AI</strong> — Nhận diện thông minh<br>• 🔧 <strong>Linh kiện</strong> — Động cơ, vi điều khiển<br>• 📋 <strong>Dự án</strong> — Các dự án đã triển khai<br><br>Bạn muốn tìm hiểu về gì ạ?";
    }
}
