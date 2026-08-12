-- Seed Data for V.K Global Shop Database
-- Categories
INSERT INTO categories (id, name, slug) VALUES
(1, 'Xe Golf Tự Hành', 'xe-golf-tu-hanh'),
(2, 'AI Camera', 'ai-camera'),
(3, 'LiDAR', 'lidar'),
(4, 'Động Cơ Bước', 'dong-co-buoc'),
(5, 'Driver Động Cơ', 'driver-dong-co'),
(6, 'Arduino', 'arduino'),
(7, 'ESP', 'esp'),
(8, 'STM32', 'stm32'),
(9, 'Raspberry Pi', 'raspberry-pi'),
(10, 'Bluetooth', 'bluetooth'),
(11, 'Cảm Biến', 'cam-bien'),
(12, 'Relay', 'relay'),
(13, 'Nguồn', 'nguon'),
(14, 'Mạch Bảo Vệ', 'mach-bao-ve'),
(15, 'Phụ Kiện', 'phu-kien')
ON DUPLICATE KEY UPDATE name=VALUES(name), slug=VALUES(slug);

-- Brands
INSERT INTO brands (id, name) VALUES
(1, 'V.K Global'),
(2, 'Arduino'),
(3, 'STMicroelectronics'),
(4, 'Espressif'),
(5, 'Raspberry Pi'),
(6, 'Leadshine'),
(7, 'YDLidar'),
(8, 'Livox'),
(9, 'Luxonis'),
(10, 'Intel'),
(11, 'NVIDIA'),
(12, 'Seeed Studio'),
(13, 'DFRobot'),
(14, 'Waveshare')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Products
INSERT INTO products (id, slug, name, category_id, brand_id, price, original_price, image, short_description, description, features, specs, rating, reviews_count, stock, sold, badge, status) VALUES
(1, 'bo-nang-cap-xe-golf-tu-hanh-vk-autodrive-pro', 'Bộ nâng cấp xe golf tự hành V.K AutoDrive Pro', 1, 1, 45000000.00, 50000000.00, 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80', 'Giải pháp chuyển đổi xe golf truyền thống thành xe tự hành thông minh cao cấp.', 'Bộ kit đầy đủ bao gồm cảm biến LiDAR 3D thế hệ mới, camera AI nhận diện vật thể thời gian thực, bộ điều khiển trung tâm ARM Cortex-M7 và động cơ bước lực kéo cao giúp xe tự động tránh vật cản, đi theo lộ trình vạch sẵn một cách hoàn hảo.', '["Tự động tránh vật cản thông minh", "Bản đồ số hóa 3D độ chính xác cao", "Điều khiển qua App iOS/Android", "Hỗ trợ sạc năng lượng mặt trời"]', '{"Điện áp": "24V-48V DC", "Tải trọng": "800kg", "Độ chính xác": "±2cm", "Cảm biến": "LiDAR 3D + Camera AI", "Chuẩn giao tiếp": "CAN, Ethernet"}', 4.90, 24, 8, 12, 'HOT', 1),
(2, 'bo-dieu-khien-lai-tu-dong-vk-steer-v1', 'Bộ điều khiển lái tự động V.K Steer-Drive v1', 1, 1, 12500000.00, 14000000.00, 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=600&q=80', 'Cơ cấu chấp hành vô lăng tự động tích hợp động cơ bước lực kéo lớn.', 'Hệ thống bánh răng hành tinh tỉ số truyền cao kết hợp động cơ bước vòng lặp kín giúp điều khiển góc lái vô lăng chính xác tuyệt đối dưới 0.1 độ.', '["Độ chính xác cực cao", "Phản hồi lực lái thông minh", "Dễ dàng lắp đặt không phá zin vô lăng"]', '{"Điện áp": "24V DC", "Dòng điện": "6A", "Chuẩn giao tiếp": "CAN, UART", "Moment xoắn": "20Nm"}', 4.80, 15, 12, 5, 'NEW', 1),
(3, 'bo-dieu-khien-chan-ga-dien-tu-vk-throttle-v2', 'Bộ điều khiển chân ga điện tử V.K Throttle-DAC v2', 1, 1, 4500000.00, 5200000.00, 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=600&q=80', 'Mạch giả lập tín hiệu Hall chân ga điều khiển tốc độ xe golf qua DAC.', 'Chuyển đổi lệnh điều khiển số từ máy tính nhúng thành điện áp analog tuyến tính chính xác để điều khiển tốc độ xe golf mượt mà.', '["Cách ly quang an toàn", "Độ phân giải DAC 12-bit", "Bảo vệ quá áp"]', '{"Điện áp": "5V-12V DC", "Chuẩn giao tiếp": "I2C, UART", "Độ phân giải": "12-bit DAC"}', 4.70, 9, 20, 14, 'BEST SELLER', 1),
(4, 'bo-dieu-khien-phanh-dien-vk-brake-v1', 'Bộ điều khiển phanh điện tự động V.K Brake-Actuator v1', 1, 1, 8900000.00, 9800000.00, 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=600&q=80', 'Cơ cấu phanh khẩn cấp tự động sử dụng xi lanh điện lực kéo lớn.', 'Tích hợp cảm biến lực phanh và công tắc hành trình đảm bảo phanh xe an toàn tuyệt đối khi phát hiện vật cản khẩn cấp.', '["Lực kéo phanh lên tới 1500N", "Phản hồi trạng thái phanh", "Kích hoạt khẩn cấp dưới 50ms"]', '{"Điện áp": "24V DC", "Dòng điện": "8A", "Lực kéo": "1500N", "Chuẩn giao tiếp": "CAN"}', 4.90, 11, 6, 8, 'HOT', 1),
(5, 'raspberry-pi-compute-module-4', 'Bo mạch nhúng Raspberry Pi Compute Module 4 (CM4)', 9, 5, 1450000.00, 1650000.00, 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80', 'Bo mạch nhúng CM4 nhỏ gọn chuyên dụng cho các thiết kế bo mạch tùy chỉnh.', 'Tích hợp vi xử lý Broadcom BCM2711 lõi tư mạnh mẽ, thích hợp cho các ứng dụng công nghiệp và xe tự hành cần tối ưu hóa không gian phần cứng.', '["Thiết kế dạng module nhỏ gọn", "Hỗ trợ bộ nhớ eMMC tích hợp", "Tương thích bo mạch IO CM4"]', '{"Điện áp": "5V DC", "Chuẩn giao tiếp": "PCIe, HDMI, MIPI CSI/DSI", "RAM": "4GB", "eMMC": "32GB"}', 4.90, 14, 15, 22, 'HOT', 1),
(6, 'raspberry-pi-camera-module-3', 'Module Camera Raspberry Pi Camera Module 3', 9, 5, 850000.00, 950000.00, 'https://images.unsplash.com/photo-1507146426996-ef05306b995a?auto=format&fit=crop&w=600&q=80', 'Module camera độ phân giải 12MP hỗ trợ lấy nét tự động (Autofocus).', 'Sử dụng cảm biến Sony IMX708 cao cấp, hỗ trợ HDR và lấy nét tự động cực nhanh, lý tưởng cho các ứng dụng nhận diện làn đường và biển báo.', '["Cảm biến Sony IMX708 12MP", "Lấy nét tự động bằng phần cứng", "Hỗ trợ dải động cao HDR"]', '{"Điện áp": "3.3V (qua cáp FPC)", "Chuẩn giao tiếp": "MIPI CSI", "Độ phân giải": "11.9 Megapixels"}', 4.80, 26, 30, 45, 'NEW', 1),
(7, 'cam-bien-encoder-vong-quay-omron-600p', 'Cảm biến Encoder vòng quay Omron 600 xung (600P/R)', 11, 3, 650000.00, 750000.00, 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?auto=format&fit=crop&w=600&q=80', 'Cảm biến mã hóa vòng quay tương đối đo tốc độ và quãng đường xe tự hành.', 'Độ phân giải 600 xung/vòng giúp đo chính xác tốc độ quay của bánh xe golf tự hành để tính toán quãng đường di chuyển (Odometry).', '["Độ phân giải 600 xung/vòng", "Ngõ ra pha A, B, Z", "Vỏ kim loại chống va đập"]', '{"Điện áp": "5V-24V DC", "Chuẩn giao tiếp": "Xung vuông pha A B", "Tần số đáp ứng": "20kHz"}', 4.90, 18, 25, 34, 'BEST SELLER', 1),
(8, 'bo-mach-pcb-vk-ecu-carrier-v1', 'Bo mạch PCB Carrier V.K Smart ECU v1', 15, 1, 1250000.00, 1500000.00, 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?auto=format&fit=crop&w=600&q=80', 'Bo mạch PCB thiết kế sẵn để kết nối STM32, ESP32 và các driver động cơ.', 'Bo mạch trung gian giúp kết nối đồng bộ, gọn gàng giữa vi điều khiển trung tâm, module truyền thông CAN BUS và các driver động cơ bước.', '["Thiết kế mạch in 4 lớp chống nhiễu", "Tích hợp cầu chì bảo vệ quá dòng", "Terminal block kết nối chắc chắn"]', '{"Điện áp hoạt động": "12V-24V DC", "Chuẩn giao tiếp": "CAN, UART, SPI, I2C", "Kích thước": "100x120mm"}', 5.00, 8, 10, 15, 'NEW', 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), price=VALUES(price), original_price=VALUES(original_price), image=VALUES(image), short_description=VALUES(short_description), description=VALUES(description), features=VALUES(features), specs=VALUES(specs), rating=VALUES(rating), reviews_count=VALUES(reviews_count), stock=VALUES(stock), sold=VALUES(sold), badge=VALUES(badge), status=VALUES(status);

-- Projects
INSERT INTO projects (id, name, category, image, description, technologies, progress, status) VALUES
(1, 'Hệ thống Xe Golf Tự Hành V.K AutoDrive', 'Autonomous Vehicle', 'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=600&q=80', 'Hệ thống xe golf tự hành hoàn chỉnh ứng dụng công nghệ LiDAR 3D, Camera AI và thuật toán SLAM để tự động vận chuyển hành khách trong các khu nghỉ dưỡng, sân golf và khu đô thị thông minh.', '["LiDAR 3D", "ROS2", "Computer Vision", "STM32", "CAN BUS"]', '90%', 'Đang thử nghiệm thực tế')
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), image=VALUES(image), description=VALUES(description), technologies=VALUES(technologies), progress=VALUES(progress), status=VALUES(status);

-- Users (password: admin123 / customer123)
INSERT INTO users (id, name, email, password, phone, avatar, role_id) VALUES
(1, 'Admin V.K Global', 'admin@vkglobal.com', '$2y$10$J58we1UDgLcwCszRKkFD..G1sTYOpv5miOywbSmZp2xu2.u335RrO', '0901234567', 'https://ui-avatars.com/api/?name=Admin+VK&background=0D6EFD&color=fff', 1),
(2, 'Nguyễn Văn An', 'khach1@vkglobal.com', '$2y$10$VnB9t1THddy345P2qZ/cM.bn8IY0bijPiC9OdKcUBA9NaMxYlGTgC', '0912345678', 'https://ui-avatars.com/api/?name=Nguyen+Van+An&background=198754&color=fff', 2),
(3, 'Trần Thị Bình', 'khach2@vkglobal.com', '$2y$10$WfKi.Qho5pzwUy0x.o2slOfMDYHh9mE5Lk5Byz.Sa8WN5E30/tzfa', '0923456789', 'https://ui-avatars.com/api/?name=Tran+Thi+Binh&background=FFC107&color=000', 2)
ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), password=VALUES(password), phone=VALUES(phone), avatar=VALUES(avatar), role_id=VALUES(role_id);

-- Carts
INSERT INTO carts (id, user_id) VALUES
(1, 2),
(2, 3)
ON DUPLICATE KEY UPDATE user_id=VALUES(user_id);

-- Cart Items
INSERT INTO cart_items (id, cart_id, product_id, quantity) VALUES
(1, 1, 1, 1),
(2, 1, 5, 2),
(3, 2, 3, 1),
(4, 2, 7, 3)
ON DUPLICATE KEY UPDATE cart_id=VALUES(cart_id), product_id=VALUES(product_id), quantity=VALUES(quantity);

-- Orders
INSERT INTO orders (id, order_code, user_id, total_amount, status, payment_method, shipping_address) VALUES
(1, 'ORD-20250101-001', 2, 47900000.00, 'Delivered', 'COD', '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh'),
(2, 'ORD-20250115-002', 3, 6450000.00, 'Processing', 'Bank Transfer', '456 Lê Lợi, Quận 3, TP. Hồ Chí Minh')
ON DUPLICATE KEY UPDATE order_code=VALUES(order_code), user_id=VALUES(user_id), total_amount=VALUES(total_amount), status=VALUES(status), payment_method=VALUES(payment_method), shipping_address=VALUES(shipping_address);

-- Order Items
INSERT INTO order_items (id, order_id, product_id, quantity, price) VALUES
(1, 1, 1, 1, 45000000.00),
(2, 1, 5, 2, 1450000.00),
(3, 2, 3, 1, 4500000.00),
(4, 2, 7, 3, 650000.00)
ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), product_id=VALUES(product_id), quantity=VALUES(quantity), price=VALUES(price);

-- Contact Messages
INSERT INTO contact_messages (id, name, email, message) VALUES
(1, 'Lê Văn Cường', 'cuong.le@email.com', 'Tôi muốn tư vấn về bộ nâng cấp xe golf tự hành V.K AutoDrive Pro. Xin vui lòng liên hệ sớm.'),
(2, 'Phạm Thị Dung', 'dung.pham@email.com', 'Cho tôi hỏi về giá sỉ của Raspberry Pi CM4 và Camera Module 3 khi mua số lượng lớn trên 50 sản phẩm.'),
(3, 'Hoàng Minh Đức', 'duc.hoang@email.com', 'Bộ mạch PCB Carrier V.K Smart ECU v1 có hỗ trợ kết nối với ESP32 và STM32 cùng lúc không?')
ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), message=VALUES(message);

-- Projects (additional)
INSERT INTO projects (id, name, category, image, description, technologies, progress, status) VALUES
(2, 'Robot AGV Vận Chuyển Hàng Hóa V.K Logistic', 'Robot', 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=600&q=80', 'Robot tự hành vận chuyển hàng hóa trong nhà máy thông minh, sử dụng SLAM và dẫn đường bằng băng từ kết hợp AI Camera.', '["ROS2", "Navigation2", "STM32", "LiDAR", "AI Camera"]', '75%', 'Đang phát triển'),
(3, 'Hệ Thống Giám Sát Năng Lượng Mặt Trời V.K Solar', 'IoT', 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=600&q=80', 'Hệ thống IoT giám sát và tối ưu hóa hiệu suất pin năng lượng mặt trời thời gian thực, tích hợp cảm biến dòng, áp và nhiệt độ.', '["ESP32", "MQTT", "InfluxDB", "Grafana", "Modbus"]', '60%', 'Đang phát triển')
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), image=VALUES(image), description=VALUES(description), technologies=VALUES(technologies), progress=VALUES(progress), status=VALUES(status);
