<?php
// ==========================================
// 1. DATABASE CONNECTION & INITIALIZATION
// ==========================================
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "stock_db";

try {
    // เชื่อมต่อ MySQL Server ผ่าน PDO
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // สร้างฐานข้อมูลถ้ายังไม่มี
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    // สร้างตาราง products ถ้ายังไม่มี
    $sql_create_table = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        manufacture_year INT,
        price DECIMAL(10,2) DEFAULT 0.00,
        stock_quantity INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    $pdo->exec($sql_create_table);

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// ==========================================
// 2. ACTION HANDLING (INSERT, UPDATE, DELETE)
// ==========================================
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- INSERT INTO (เพิ่มสินค้าใหม่) ---
    if ($action === 'add') {
        $code             = trim($_POST['code'] ?? '');
        $name             = trim($_POST['name'] ?? '');
        $category         = trim($_POST['category'] ?? '');
        $manufacture_year = !empty($_POST['manufacture_year']) ? (int)$_POST['manufacture_year'] : null;
        $price            = (float)($_POST['price'] ?? 0);
        $stock_quantity   = (int)($_POST['stock_quantity'] ?? 0);

        if (!empty($code) && !empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO products (code, name, category, manufacture_year, price, stock_quantity) VALUES (:code, :name, :category, :manufacture_year, :price, :stock_quantity)");
            $stmt->execute([
                ':code'             => $code,
                ':name'             => $name,
                ':category'         => $category,
                ':manufacture_year' => $manufacture_year,
                ':price'            => $price,
                ':stock_quantity'   => $stock_quantity
            ]);
            $message = "เพิ่มสินค้าใหม่เรียบร้อยแล้ว!";
            $message_type = "success";
        } else {
            $message = "กรุณากรอกรหัสสินค้าและชื่อสินค้า!";
            $message_type = "danger";
        }
    }

    // --- UPDATE (ปรับสต็อก รับเข้า / ตัดจ่าย ทั้งจาก Modal และปุ่มด่วน) ---
    elseif ($action === 'adjust_stock') {
        $id          = (int)($_POST['id'] ?? 0);
        $adjust_type = $_POST['adjust_type'] ?? 'in'; // 'in' = เพิ่ม, 'out' = ลด
        $quantity    = (int)($_POST['quantity'] ?? 0);

        if ($id > 0 && $quantity > 0) {
            if ($adjust_type === 'in') {
                // คำสั่ง SQL เพิ่มสต็อก (รับเข้า)
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id");
                $msg_txt = "เพิ่มสต็อกสินค้าเรียบร้อยแล้ว (+{$quantity})";
            } else {
                // คำสั่ง SQL ลดสต็อก (ตัดจ่าย) - ใช้ GREATEST ป้องกันสต็อกติดลบ
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - :quantity) WHERE id = :id");
                $msg_txt = "ตัดจ่ายสต็อกสินค้าเรียบร้อยแล้ว (-{$quantity})";
            }
            $stmt->execute([':quantity' => $quantity, ':id' => $id]);
            $message = $msg_txt;
            $message_type = "success";
        }
    }

    // --- UPDATE (แก้ไขรายละเอียดข้อมูลสินค้า) ---
    elseif ($action === 'edit') {
        $id               = (int)($_POST['id'] ?? 0);
        $code             = trim($_POST['code'] ?? '');
        $name             = trim($_POST['name'] ?? '');
        $category         = trim($_POST['category'] ?? '');
        $manufacture_year = !empty($_POST['manufacture_year']) ? (int)$_POST['manufacture_year'] : null;
        $price            = (float)($_POST['price'] ?? 0);

        if ($id > 0 && !empty($code) && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE products SET code = :code, name = :name, category = :category, manufacture_year = :manufacture_year, price = :price WHERE id = :id");
            $stmt->execute([
                ':code'             => $code,
                ':name'             => $name,
                ':category'         => $category,
                ':manufacture_year' => $manufacture_year,
                ':price'            => $price,
                ':id'               => $id
            ]);
            $message = "แก้ไขข้อมูลสินค้าเรียบร้อยแล้ว!";
            $message_type = "success";
        }
    }

    // --- DELETE (ลบรายการสินค้า) ---
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = "ลบรายการสินค้าเรียบร้อยแล้ว!";
            $message_type = "warning";
        }
    }
}

// ==========================================
// 3. SELECT & WHERE (ดึงข้อมูล ค้นหา สรุปยอด)
// ==========================================
$search_keyword  = trim($_GET['search_keyword'] ?? '');
$search_category = trim($_GET['search_category'] ?? '');
$search_year     = trim($_GET['search_year'] ?? '');
$stock_status    = trim($_GET['stock_status'] ?? '');

// สร้างเงื่อนไข Dynamic Query สำหรับ WHERE
$where_clauses = ["1=1"];
$params = [];

if (!empty($search_keyword)) {
    $where_clauses[] = "(code LIKE :keyword OR name LIKE :keyword)";
    $params[':keyword'] = "%$search_keyword%";
}
if (!empty($search_category)) {
    $where_clauses[] = "category = :category";
    $params[':category'] = $search_category;
}
if (!empty($search_year)) {
    $where_clauses[] = "manufacture_year = :year";
    $params[':year'] = (int)$search_year;
}

// กรองตามระดับสถานะสต็อก (Traffic Light Level)
if ($stock_status === 'out_of_stock') {
    $where_clauses[] = "stock_quantity = 0";
} elseif ($stock_status === 'low_stock') {
    $where_clauses[] = "stock_quantity > 0 AND stock_quantity <= 5";
} elseif ($stock_status === 'normal_stock') {
    $where_clauses[] = "stock_quantity > 5";
}

$where_sql = implode(" AND ", $where_clauses);

// ดึงรายการสินค้าทั้งหมดตามเงื่อนไข
$stmt = $pdo->prepare("SELECT * FROM products WHERE $where_sql ORDER BY id DESC");
$stmt->execute($params);
$products = $stmt->fetchAll();

// คำนวณสรุปภาพรวม (Aggregate Functions: COUNT, SUM, CASE WHEN)
$summary_stmt = $pdo->query("SELECT 
    COUNT(*) AS total_items,
    SUM(stock_quantity) AS total_stock,
    SUM(price * stock_quantity) AS total_value,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS count_out,
    SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 5 THEN 1 ELSE 0 END) AS count_low,
    SUM(CASE WHEN stock_quantity > 5 THEN 1 ELSE 0 END) AS count_normal
FROM products");
$summary = $summary_stmt->fetch();

// ดึงหมวดหมู่ทั้งหมดสำหรับใส่ Dropdown
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสต็อกสินค้า (Inventory Management System)</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Sarabun', sans-serif; }
        .card-summary { border: none; border-radius: 12px; transition: transform 0.2s; }
        .card-summary:hover { transform: translateY(-3px); }
        .table-responsive { background-color: #ffffff; border-radius: 10px; }
        
        /* สไตล์ตาราง สัญญาณไฟจราจร */
        .stock-row-out { background-color: #fff5f5 !important; }
        .stock-row-low { background-color: #fffdf0 !important; }
        
        .badge-status-out { background-color: #dc3545; color: white; font-weight: 500; }
        .badge-status-low { background-color: #ffc107; color: #000; font-weight: 500; }
        .badge-status-normal { background-color: #198754; color: white; font-weight: 500; }

        /* ซ่อนส่วนที่ไม่ต้องการเวลาสั่งพิมพ์รายงาน */
        @media print {
            .btn, form, .modal, .no-print, .alert { display: none !important; }
            body { background-color: #ffffff !important; }
            .card-summary { border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1">📦 ระบบจัดการสต็อกสินค้า</h2>
            <p class="text-muted mb-0">ระบบบริหารจัดการคลังสินค้า (PHP + MySQL PDO System)</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print();" class="btn btn-outline-secondary">🖨️ พิมพ์รายงาน</button>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                ➕ เพิ่มสินค้าใหม่
            </button>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show shadow-sm no-print" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards Analytics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-summary bg-white p-3 shadow-sm border-start border-primary border-4">
                <div class="text-muted small">จำนวนรายการสินค้า</div>
                <div class="h3 fw-bold mb-0 text-primary"><?= number_format($summary['total_items'] ?? 0) ?> <span class="fs-6 text-muted">รายการ</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-white p-3 shadow-sm border-start border-info border-4">
                <div class="text-muted small">จำนวนสต็อกสินค้ารวม</div>
                <div class="h3 fw-bold mb-0 text-info"><?= number_format($summary['total_stock'] ?? 0) ?> <span class="fs-6 text-muted">ชิ้น</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-white p-3 shadow-sm border-start border-success border-4">
                <div class="text-muted small">มูลค่าคลังสินค้ารวม</div>
                <div class="h3 fw-bold mb-0 text-success"><?= number_format($summary['total_value'] ?? 0, 2) ?> <span class="fs-6 text-muted">บาท</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-summary bg-white p-3 shadow-sm border-start border-warning border-4">
                <div class="text-muted small">สินค้าต้องติดตาม (หมด/น้อย)</div>
                <div class="h3 fw-bold mb-0 text-warning">
                    <span class="text-danger" title="สินค้าหมด"><?= number_format($summary['count_out'] ?? 0) ?></span> / 
                    <span class="text-warning" title="สต็อกต่ำ"><?= number_format($summary['count_low'] ?? 0) ?></span>
                    <span class="fs-6 text-muted">รายการ</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Traffic Light Filter Form -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search_keyword" class="form-control" placeholder="🔍 ค้นหารหัส หรือ ชื่อสินค้า..." value="<?= htmlspecialchars($search_keyword) ?>">
                </div>
                <div class="col-md-2">
                    <select name="search_category" class="form-select">
                        <option value="">-- หมวดหมู่ทั้งหมด --</option>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $search_category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="search_year" class="form-control" placeholder="ปีที่ผลิต (ค.ศ.)" value="<?= htmlspecialchars($search_year) ?>">
                </div>
                <div class="col-md-3">
                    <select name="stock_status" class="form-select">
                        <option value="">-- สถานะสต็อกทั้งหมด --</option>
                        <option value="out_of_stock" <?= $stock_status === 'out_of_stock' ? 'selected' : '' ?>>🔴 สินค้าหมด (0 ชิ้น)</option>
                        <option value="low_stock" <?= $stock_status === 'low_stock' ? 'selected' : '' ?>>🟡 สต็อกต่ำ (1 - 5 ชิ้น)</option>
                        <option value="normal_stock" <?= $stock_status === 'normal_stock' ? 'selected' : '' ?>>🟢 สต็อกปกติ (> 5 ชิ้น)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                    <a href="index.php" class="btn btn-outline-secondary">รีเซ็ต</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Data Table -->
    <div class="table-responsive shadow-sm p-3">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>ปีที่ผลิต</th>
                    <th class="text-end">ราคา/หน่วย</th>
                    <th class="text-center">ระดับสถานะสต็อก</th>
                    <th class="text-center">สต็อกคงเหลือ</th>
                    <th class="text-center no-print" style="width: 240px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as$p): 
                        // เงื่อนไขแยกสีและสถานะ Traffic Light
                        $stock = (int)$p['stock_quantity'];
                        if ($stock === 0) {$row_class = "stock-row-out";
                            $badge_class = "badge-status-out";
                            $status_text = "🔴 สินค้าหมด";
                        } elseif ($stock <= 5) {$row_class = "stock-row-low";
                            $badge_class = "badge-status-low";
                            $status_text = "🟡 สต็อกน้อย (วิกฤต)";
                        } else {
                            $row_class = "";
                            $badge_class = "badge-status-normal";
                            $status_text = "🟢 สต็อกปกติ";
                        }
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="fw-bold text-secondary"><?= htmlspecialchars($p['code']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['category'] ?: 'ไม่ระบุ') ?></span></td>
                            <td><?= $p['manufacture_year'] ?: '-' ?></td>
                            <td class="text-end fw-bold"><?= number_format($p['price'], 2) ?> ฿</td>
                            <td class="text-center">
                                <span class="badge <?= $badge_class ?> px-2 py-1"><?= $status_text ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-1">
                                    <!-- ปุ่มลดสต็อกด่วน -1 -->
                                    <form method="POST" class="d-inline no-print">
                                        <input type="hidden" name="action" value="adjust_stock">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="adjust_type" value="out">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-0 px-1" title="ลดสต็อก 1 ชิ้น" <?= $stock == 0 ? 'disabled' : '' ?>>-</button>
                                    </form>

                                    <span class="fs-6 fw-bold px-1"><?= number_format($p['stock_quantity']) ?></span>

                                    <!-- ปุ่มเพิ่มสต็อกด่วน +1 -->
                                    <form method="POST" class="d-inline no-print">
                                        <input type="hidden" name="action" value="adjust_stock">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="adjust_type" value="in">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-success p-0 px-1" title="เพิ่มสต็อก 1 ชิ้น">+</button>
                                    </form>
                                </div>
                            </td>
                            <td class="text-center no-print">
                                <!-- ปุ่มเปิด Modal ปรับสต็อกแบบละเอียด -->
                                <button class="btn btn-sm btn-outline-primary me-1" 
                                        onclick="openStockModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>', <?=$p['stock_quantity'] ?>)" 
                                        title="ปรับปรุงสต็อกละเอียด">
                                    📦 ปรับสต็อก
                                </button>
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" 
                                        title="แก้ไขข้อมูล">
                                    ✏️
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรายการสินค้านี้?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบสินค้า">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลสินค้าตรงตามเงื่อนไขการค้นหา</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================
     MODALS SECTION
     ========================================== -->

<!-- 1. Modal: เพิ่มสินค้าใหม่ -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">➕ เพิ่มสินค้าใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">รหัสสินค้า <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" placeholder="เช่น P001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="เช่น คีย์บอร์ดไร้สาย" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <input type="text" name="category" class="form-control" placeholder="เช่น ไอที, อุปกรณ์สำนักงาน">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ปีที่ผลิต (ค.ศ.)</label>
                            <input type="number" name="manufacture_year" class="form-control" placeholder="เช่น 2024">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ราคาต่อหน่วย (บาท)</label>
                            <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">จำนวนสต็อกเริ่มต้น</label>
                            <input type="number" name="stock_quantity" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกสินค้า</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Modal: ปรับอัปเดตสต็อก (IN/OUT) -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="id" id="stock_product_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">📦 ปรับอัปเดตสต็อกสินค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">สินค้า: <strong id="stock_product_name" class="text-primary fs-5"></strong></p>
                    <p class="text-muted small mb-3">จำนวนคงเหลือปัจจุบัน: <span id="stock_current_qty" class="fw-bold fs-6 text-dark"></span> ชิ้น</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">ประเภทการปรับปรุง</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="adjust_type" id="type_in" value="in" checked>
                            <label class="btn btn-outline-success" for="type_in">🟢 รับเข้า (+) เพิ่มสต็อก</label>

                            <input type="radio" class="btn-check" name="adjust_type" id="type_out" value="out">
                            <label class="btn btn-outline-danger" for="type_out">🔴 ตัดจ่าย (-) ลดสต็อก</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">จำนวนที่ต้องการปรับปรุง <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control form-control-lg text-center fw-bold" min="1" value="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการปรับปรุงสต็อก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Modal: แก้ไขข้อมูลสินค้า -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">✏️ แก้ไขข้อมูลสินค้า</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">รหัสสินค้า</label>
                        <input type="text" name="code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อสินค้า</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่</label>
                            <input type="text" name="category" id="edit_category" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ปีที่ผลิต (ค.ศ.)</label>
                            <input type="number" name="manufacture_year" id="edit_manufacture_year" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ราคาต่อหน่วย (บาท)</label>
                        <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS & Script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // เปิด Modal ปรับสต็อก
    function openStockModal(id, name, currentQty) {
        document.getElementById('stock_product_id').value = id;
        document.getElementById('stock_product_name').innerText = name;
        document.getElementById('stock_current_qty').innerText = currentQty;
        document.getElementById('type_in').checked = true; // รีเซ็ตกลับไปเป็นรับเข้า
        new bootstrap.Modal(document.getElementById('stockModal')).show();
    }

    // เปิด Modal แก้ไขข้อมูล
    function openEditModal(product) {
        document.getElementById('edit_id').value = product.id;
        document.getElementById('edit_code').value = product.code;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_category').value = product.category || '';
        document.getElementById('edit_manufacture_year').value = product.manufacture_year || '';
        document.getElementById('edit_price').value = product.price;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>
</body>
</html>
