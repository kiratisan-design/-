<?php
// ==========================================
// 1. การเชื่อมต่อฐานข้อมูล MySQL (สร้าง DB & Table อัตโนมัติ)
// ==========================================
$host     = 'localhost';$dbname   = 'stock_db';
$username = 'root';$password = '';

try {
    // 1.1 เชื่อมต่อ MySQL Server
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1.2 สร้าง Database อัตโนมัติหากยังไม่มีในระบบ
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    // 1.3 สร้าง Table 'products' อัตโนมัติหากยังไม่มี
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
    die("❌ เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

$message = '';$alert_type = 'success';

// ==========================================
// 2. ประมวลผลคำสั่ง INSERT INTO / UPDATE / DELETE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action =$_POST['action'] ?? '';

    // --- [เกณฑ์ข้อ 1] INSERT INTO: ใช้เพิ่มข้อมูลใหม่ ---
    if ($action === 'add') {
        $code             = trim($_POST['code']);
        $name             = trim($_POST['name']);
        $category         = trim($_POST['category']);
        $manufacture_year = intval($_POST['manufacture_year']);
        $price            = floatval($_POST['price']);
        $stock_quantity   = intval($_POST['stock_quantity']);

        if (!empty($code) && !empty($name)) {$sql = "INSERT INTO products (code, name, category, manufacture_year, price, stock_quantity) 
                    VALUES (:code, :name, :category, :manufacture_year, :price, :stock_quantity)";
            $stmt =$pdo->prepare($sql);$stmt->execute([
                ':code'             => $code,
                ':name'             => $name,
                ':category'         => $category,
                ':manufacture_year' => $manufacture_year,
                ':price'            => $price,
                ':stock_quantity'   => $stock_quantity
            ]);
            $message = "✅ เพิ่มข้อมูลสินค้าใหม่ (INSERT INTO) สำเร็จ!";
        }
    }

    // --- [เกณฑ์ข้อ 2] UPDATE: ใช้ปรับปรุงข้อมูล (ตัด/เพิ่ม สต็อกคงเหลือ) ---
    elseif ($action === 'update_stock') {
        $id   = intval($_POST['id']);
        $type =$_POST['type']; // 'IN' หรือ 'OUT'
        $qty  = intval($_POST['qty']);

        if ($id > 0 &&$qty > 0) {
            if ($type === 'IN') {$sql = "UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id";
            } else {
                $sql = "UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - :qty) WHERE id = :id";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':qty' =>$qty, ':id' => $id]);$message = "✅ อัปเดตสต็อกสินค้า (UPDATE) สำเร็จ!";
        }
    }

    // --- [เกณฑ์ข้อ 1] DELETE: ใช้ลบข้อมูล ---
    elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id > 0) {$sql = "DELETE FROM products WHERE id = :id";
            $stmt =$pdo->prepare($sql);$stmt->execute([':id' => $id]);$message = "🗑️ ลบรายการสินค้า (DELETE) สำเร็จ!";
            $alert_type = 'danger';
        }
    }
}

// ==========================================
// 3. [เกณฑ์ข้อ 3 & 4] SELECT + WHERE: ดึงและกรองข้อมูล
// ==========================================
$search_name     = trim($_GET['search_name'] ?? '');
$search_category = trim($_GET['search_category'] ?? '');
$search_year     = trim($_GET['search_year'] ?? '');

// เริ่มต้นด้วยคำสั่ง SELECT และ WHERE 1=1 เพื่อต่อเงื่อนไขค้นหาได้ยืดหยุ่น
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

// ค้นหาจากชื่อสินค้า
if (!empty($search_name)) {$sql .= " AND name LIKE :search_name";
    $params[':search_name'] = "\%$search_name%";
}

// ค้นหาจากประเภท/หมวดหมู่
if (!empty($search_category)) {$sql .= " AND category LIKE :search_category";
    $params[':search_category'] = "\%$search_category%";
}

// ค้นหาจากปีที่ผลิต
if (!empty($search_year)) {$sql .= " AND manufacture_year = :search_year";
    $params[':search_year'] =$search_year;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products =$stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// 4. [เกณฑ์ข้อ 5] SELECT สรุปรายงานรวม (ความสามารถเพิ่มขึ้น 1 อย่าง)
// ==========================================
$sql_summary = "SELECT 
                    COUNT(id) AS total_items, 
                    SUM(stock_quantity) AS total_stock, 
                    SUM(price * stock_quantity) AS total_value 
                FROM products";
$summary = $pdo->query($sql_summary)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการสต็อกสินค้า (PHP + MySQL SQL Core)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">📦 ระบบจัดการสต็อกสินค้า (MySQL Core SQL)</a>
    </div>
</nav>

<div class="container">

    <!-- ข้อความแจ้งเตือนผลลัพธ์การทำงาน -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- [เกณฑ์ข้อ 5] ฟีเจอร์เพิ่มเติม: การ์ดสรุปยอดรวม (SELECT) -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">จำนวนรายการทั้งหมด</h6>
                    <h3 class="fw-bold m-0"><?= number_format($summary['total_items'] ?? 0) ?> รายการ</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">จำนวนสต็อกรวม</h6>
                    <h3 class="fw-bold m-0"><?= number_format($summary['total_stock'] ?? 0) ?> ชิ้น</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">มูลค่าสินค้ารวม</h6>
                    <h3 class="fw-bold m-0"><?= number_format($summary['total_value'] ?? 0, 2) ?> บาท</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- [เกณฑ์ข้อ 4] ฟอร์มค้นหาข้อมูล (SELECT ... WHERE ...) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white font-weight-bold">
            <span>🔍 ค้นหารายการสินค้า (SELECT ... WHERE ...)</span>
        </div>
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">ชื่อสินค้า</label>
                    <input type="text" name="search_name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="ระบุชื่อสินค้า...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">หมวดหมู่</label>
                    <input type="text" name="search_category" class="form-control" value="<?= htmlspecialchars($search_category) ?>" placeholder="ระบุหมวดหมู่...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">ปีที่ผลิต (ค.ศ.)</label>
                    <input type="number" name="search_year" class="form-control" value="<?= htmlspecialchars($search_year) ?>" placeholder="เช่น 2024">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                    <a href="index.php" class="btn btn-secondary">ล้าง</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ปุ่มเปิด Modal [เกณฑ์ข้อ 1] INSERT INTO -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>รายการสินค้าในระบบ</h4>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ เพิ่มสินค้าใหม่ (INSERT INTO)</button>
    </div>

    <!-- [เกณฑ์ข้อ 3] ตารางแสดงรายการสินค้า (SELECT) -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>หมวดหมู่</th>
                        <th>ปีที่ผลิต</th>
                        <th>ราคา (บาท)</th>
                        <th>สต็อกคงเหลือ</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as$item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['code']) ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td><?= htmlspecialchars($item['manufacture_year']) ?></td>
                                <td><?= number_format($item['price'], 2) ?></td>
                                <td>
                                    <!-- [เกณฑ์ข้อ 3] แสดงแถบสีตามเงื่อนไข สต็อกต่ำกว่า 5 ชิ้น -->
                                    <span class="badge <?= $item['stock_quantity'] < 5 ? 'bg-danger' : 'bg-success' ?> fs-6">
                                        <?= $item['stock_quantity'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <!-- ปุ่มปรับสต็อก [เกณฑ์ข้อ 2] UPDATE -->
                                    <button class="btn btn-sm btn-info text-white" 
                                            onclick="openStockModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?=$item['stock_quantity'] ?>)">
                                        ปรับสต็อก (UPDATE)
                                    </button>
                                    
                                    <!-- ฟอร์มลบข้อมูล [เกณฑ์ข้อ 1] DELETE -->
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบรายการนี้? (DELETE)');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">ลบ (DELETE)</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">❌ ไม่พบข้อมูลสินค้าในระบบ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal เพิ่มสินค้า (INSERT INTO) -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">เพิ่มสินค้าใหม่ (INSERT INTO)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label>รหัสสินค้า</label><input type="text" name="code" class="form-control" required></div>
                    <div class="mb-2"><label>ชื่อสินค้า</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-2"><label>หมวดหมู่</label><input type="text" name="category" class="form-control" placeholder="เช่น ไอที" required></div>
                    <div class="mb-2"><label>ปีที่ผลิต (ค.ศ.)</label><input type="number" name="manufacture_year" class="form-control" value="2024" required></div>
                    <div class="mb-2"><label>ราคา (บาท)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
                    <div class="mb-2"><label>จำนวนสต็อกเริ่มต้น</label><input type="number" name="stock_quantity" class="form-control" value="0" required></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">บันทึกข้อมูล (INSERT)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ปรับสต็อก (UPDATE) -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="id" id="modal_stock_id">
                <div class="modal-header">
                    <h5 class="modal-title">บันทึกเพิ่ม/ตัดรายการสินค้า (UPDATE)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fw-bold" id="modal_stock_name"></p>
                    <p>สต็อกปัจจุบัน: <span id="modal_stock_current" class="badge bg-secondary fs-6"></span></p>
                    <div class="mb-3">
                        <label>ประเภทรายการ</label>
                        <select name="type" class="form-select">
                            <option value="IN">➕ รับสินค้าเข้า (เพิ่มสต็อก)</option>
                            <option value="OUT">➖ ตัดจ่ายสินค้า (ลดสต็อก)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>จำนวนที่ต้องการปรับปรุง</label>
                        <input type="number" name="qty" class="form-control" min="1" value="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">อัปเดตสต็อก (UPDATE)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openStockModal(id, name, currentStock) {
    document.getElementById('modal_stock_id').value = id;
    document.getElementById('modal_stock_name').innerText = "สินค้า: " + name;
    document.getElementById('modal_stock_current').innerText = currentStock;
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}
</script>
</body>
</html>
