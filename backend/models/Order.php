<?php
// Order Model to handle orders and order items
class Order {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($userId, $totalAmount, $paymentMethod, $shippingAddress, $items) {
        if ($this->conn === null) return ["success" => false, "message" => "Lỗi kết nối cơ sở dữ liệu"];
        try {
            $this->conn->beginTransaction();

            $orderCode = "VK-" . strtoupper(bin2hex(random_bytes(4)));
            $query = "INSERT INTO orders (order_code, user_id, total_amount, payment_method, shipping_address) 
                      VALUES (:order_code, :user_id, :total_amount, :payment_method, :shipping_address)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_code', $orderCode);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':total_amount', $totalAmount);
            $stmt->bindParam(':payment_method', $paymentMethod);
            $stmt->bindParam(':shipping_address', $shippingAddress);
            $stmt->execute();

            $orderId = $this->conn->lastInsertId();

            foreach ($items as $item) {
                $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, price) 
                              VALUES (:order_id, :product_id, :quantity, :price)";
                $itemStmt = $this->conn->prepare($itemQuery);
                $itemStmt->bindParam(':order_id', $orderId);
                $itemStmt->bindParam(':product_id', $item['product_id']);
                $itemStmt->bindParam(':quantity', $item['quantity']);
                $itemStmt->bindParam(':price', $item['price']);
                $itemStmt->execute();
            }

            $this->conn->commit();
            return ["success" => true, "order_code" => $orderCode, "order_id" => $orderId];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function getByUserId($userId) {
        if ($this->conn === null) return [];
        $query = "SELECT o.*, 
                         oi.id as item_id, oi.product_id, oi.quantity, oi.price as item_price,
                         p.name as product_name, p.image as product_image
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE o.user_id = :user_id 
                  ORDER BY o.id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group items by order
        $orders = [];
        foreach ($rows as $row) {
            $orderId = $row['id'];
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'id' => $row['id'],
                    'order_code' => $row['order_code'],
                    'user_id' => $row['user_id'],
                    'total_amount' => $row['total_amount'],
                    'payment_method' => $row['payment_method'],
                    'shipping_address' => $row['shipping_address'],
                    'status' => $row['status'] ?? 'Chờ xác nhận',
                    'created_at' => $row['created_at'],
                    'items' => []
                ];
            }

            if ($row['item_id']) {
                $orders[$orderId]['items'][] = [
                    'id' => $row['item_id'],
                    'product_id' => $row['product_id'],
                    'quantity' => $row['quantity'],
                    'price' => $row['item_price'],
                    'product_name' => $row['product_name'],
                    'product_image' => $row['product_image']
                ];
            }
        }

        return array_values($orders);
    }
}
