<?php
// Order Model to handle orders and order items
class Order {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($userId, $totalAmount, $paymentMethod, $shippingAddress, $items, $paymentStatus = 'unpaid') {
        if ($this->conn === null) return ["success" => false, "message" => "Lỗi kết nối cơ sở dữ liệu"];
        try {
            $this->conn->beginTransaction();

            $orderCode = "VK-" . strtoupper(bin2hex(random_bytes(4)));
            $query = "INSERT INTO orders (order_code, user_id, total_amount, payment_method, shipping_address, payment_status)
                      VALUES (:order_code, :user_id, :total_amount, :payment_method, :shipping_address, :payment_status)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_code', $orderCode);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':total_amount', $totalAmount);
            $stmt->bindParam(':payment_method', $paymentMethod);
            $stmt->bindParam(':shipping_address', $shippingAddress);
            $stmt->bindParam(':payment_status', $paymentStatus);
            $stmt->execute();

            $orderId = (int) $this->conn->lastInsertId();

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

    public function updatePayosInfo($orderId, $payosOrderCode, $paymentLinkId, $qrData = []) {
        if ($this->conn === null) return false;
        $query = "UPDATE orders SET
                    payos_order_code = :payos_order_code,
                    payos_payment_link_id = :payment_link_id,
                    payos_qr_code = :qr_code,
                    payos_checkout_url = :checkout_url,
                    payos_account_number = :account_number,
                    payos_account_name = :account_name
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':payos_order_code', $payosOrderCode, PDO::PARAM_INT);
        $stmt->bindValue(':payment_link_id', $qrData['payment_link_id'] ?? $paymentLinkId);
        $stmt->bindValue(':qr_code', $qrData['qr_code'] ?? null);
        $stmt->bindValue(':checkout_url', $qrData['checkout_url'] ?? null);
        $stmt->bindValue(':account_number', $qrData['account_number'] ?? null);
        $stmt->bindValue(':account_name', $qrData['account_name'] ?? null);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function markAsPaid($orderId, $paymentLinkId = null, $transactionId = null) {
        if ($this->conn === null) return false;
        $query = "UPDATE orders SET payment_status = 'paid', status = 'Đang xử lý', paid_at = NOW(),
                  payos_payment_link_id = COALESCE(:payment_link_id, payos_payment_link_id),
                  payos_transaction_id = COALESCE(:transaction_id, payos_transaction_id)
                  WHERE id = :id AND payment_status != 'paid'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':payment_link_id', $paymentLinkId);
        $stmt->bindValue(':transaction_id', $transactionId);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getById($orderId) {
        if ($this->conn === null) return null;
        $query = "SELECT * FROM orders WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByPayosOrderCode($payosOrderCode) {
        if ($this->conn === null) return null;
        $query = "SELECT * FROM orders WHERE payos_order_code = :payos_order_code LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':payos_order_code', $payosOrderCode, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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
                    'payment_status' => $row['payment_status'] ?? 'unpaid',
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
