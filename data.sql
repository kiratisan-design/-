USE `project_db`;

-- 1. สร้างตารางประเภทสินค้า
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ใส่ข้อมูลประเภทตัวอย่าง
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'อิเล็กทรอนิกส์'),
(2, 'เครื่องใช้ไฟฟ้า'),
(3, 'อุปกรณ์สำนักงาน');

-- 2. สร้างตารางสินค้า
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `category_id` INT NOT NULL,
  `manufacture_year` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ใส่ข้อมูลสินค้าตัวอย่าง
INSERT INTO `products` (`code`, `name`, `category_id`, `manufacture_year`, `price`, `stock_quantity`) VALUES
('P001', 'โน้ตบุ๊ก Dell XPS 13', 1, 2023, 45000.00, 10),
('P002', 'จอภาพ LG UltraGear 27"', 1, 2022, 12500.00, 15),
('P003', 'เครื่องปรินท์ HP LaserJet', 3, 2021, 6900.00, 5);

-- 3. สร้างตารางประวัติสต็อก
CREATE TABLE IF NOT EXISTS `stock_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `type` ENUM('IN', 'OUT') NOT NULL,
  `quantity` INT NOT NULL,
  `note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;