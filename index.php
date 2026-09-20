<?php
require_once 'db.php';

// --- ฟังก์ชันจัดการการทำงาน (Actions) ---

// 1. เพิ่มข้อมูลสินค้า (ข้อ 1)
if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $code = $conn->real_escape_string($_POST['code']);
    $name = $conn->real_escape_string($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $year = (int)$_POST['manufacture_year'];
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock_quantity'];

    $sql = "INSERT INTO products (code, name, category_id, manufacture_year, price, stock_quantity) 
            VALUES ('$code', '$name', $category_id, $year, $price, $stock)";
    $conn->query($sql);
    header("Location: index.php");
    exit;
}

// 2. แก้ไขข้อมูลสินค้า (ข้อ 1)
if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $id = (int)$_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $year = (int)$_POST['manufacture_year'];
    $price = (float)$_POST['price'];

    $sql = "UPDATE products SET name='$name', category_id=$category_id, manufacture_year=$year, price=$price WHERE id=$id";
    $conn->query($sql);
    header("Location: index.php");
    exit;
}

// 3. ลบข้อมูลสินค้า (ข้อ 1)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE id=$id");
    header("Location: index.php");
    exit;
}

// 4. บันทึกตัด/เพิ่ม สต็อกสินค้า (ข้อ 2)
if (isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $product_id = (int)$_POST['product_id'];
    $type = $_POST['type']; // 'IN' หรือ 'OUT'
    $qty = (int)$_POST['quantity'];
    $note = $conn->real_escape_string($_POST['note']);

    if ($qty > 0) {
        $stmt = $conn->prepare("INSERT INTO stock_transactions (product_id, type, quantity, note) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $product_id, $type, $qty, $note);
        $stmt->execute();

        if ($type === 'IN') {
            $conn->query("UPDATE products SET stock_quantity = stock_quantity + $qty WHERE id = $product_id");
        } else if ($type === 'OUT') {
            $conn->query("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - $qty) WHERE id = $product_id");
        }
    }
    header("Location: index.php");
    exit;
}

// --- การรับค่าและการประมวลผลการค้นหา (ปรับปรุงใหม่ - ข้อ 4) ---
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$search_category = isset($_GET['search_category']) ? trim($_GET['search_category']) : '';
$search_year = isset($_GET['search_year']) ? trim($_GET['search_year']) : '';

$where = ["1=1"];

// ค้นหาตามชื่อสินค้า (พิมพ์แค่บางส่วนก็ค้นหาเจอ)
if ($search_name !== '') {
    $safe_name = $conn->real_escape_string($search_name);
    $where[] = "p.name LIKE '%$safe_name%'";
}

// ค้นหาตามประเภทสินค้า
if ($search_category !== '') {
    $where[] = "p.category_id = " . (int)$search_category;
}

// ค้นหาตามปีที่ผลิต
if ($search_year !== '') {
    $where[] = "p.manufacture_year = " . (int)$search_year;
}

// สร้างคำสั่ง SQL สำหรับการค้นหา
$sql = "SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY p.id DESC";

$products = $conn->query($sql);
$categories = $conn->query("SELECT * FROM categories");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบจัดการสต็อกสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">ระบบจัดการสต็อกสินค้า</a>
        <div class="navbar-nav">
            <a class="nav-link active" href="index.php">จัดการสินค้า & สต็อก</a>
            <a class="nav-link" href="report.php">รายงานสรุป</a>
        </div>
    </div>
</nav>

<div class="container">
    <!-- ฟอร์มค้นหารายการ (ข้อ 4) -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white font-weight-bold d-flex justify-content-between align-items-center">
            <span>🔍 ค้นหารายการสินค้า (ข้อ 4)</span>
            <?php if ($search_name !== '' || $search_category !== '' || $search_year !== ''): ?>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">❌ ล้างการค้นหา</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">ชื่อสินค้า</label>
                    <input type="text" name="search_name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="ระบุชื่อสินค้าบางส่วน...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">ประเภทสินค้า</label>
                    <select name="search_category" class="form-select">
                        <option value="">-- ทุกประเภท --</option>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?= $cat['id'] ?>" <?= $search_category == $cat['id'] ? 'selected' : '' ?>><?= $cat['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">ปีที่ผลิต (ค.ศ.)</label>
                    <input type="number" name="search_year" class="form-control" value="<?= htmlspecialchars($search_year) ?>" placeholder="เช่น 2023">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ปุ่มเพิ่มสินค้า และแสดงตารางสินค้า -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>รายการสินค้าในระบบ</h4>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal">+ เพิ่มสินค้าใหม่ (ข้อ 1)</button>
    </div>

    <!-- ตารางแสดงรายการสินค้า (ข้อ 1 & 2) -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>ประเภท</th>
                        <th>ปีที่ผลิต</th>
                        <th>ราคา (บาท)</th>
                        <th>สต็อกคงเหลือ</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products && $products->num_rows > 0): ?>
                        <?php while ($row = $products->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['code']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><?= $row['manufacture_year'] ?></td>
                                <td><?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $row['stock_quantity'] < 5 ? 'bg-danger' : 'bg-success' ?> fs-6">
                                        <?= $row['stock_quantity'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info text-white" onclick="openStockModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')">ปรับสต็อก</button>
                                    <button class="btn btn-sm btn-warning text-white" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)">แก้ไข</button>
                                    <a href="index.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ยืนยันการลบรายการนี้?')">ลบ</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">❌ ไม่พบข้อมูลสินค้าที่ตรงตามเงื่อนไขการค้นหา</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: เพิ่มสินค้า -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add_product">
            <div class="modal-header">
                <h5 class="modal-title">เพิ่มสินค้าใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><label>รหัสสินค้า</label><input type="text" name="code" class="form-control" required></div>
                <div class="mb-2"><label>ชื่อสินค้า</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-2">
                    <label>ประเภทสินค้า</label>
                    <select name="category_id" class="form-select" required>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-2"><label>ปีที่ผลิต (ค.ศ.)</label><input type="number" name="manufacture_year" class="form-control" value="2023" required></div>
                <div class="mb-2"><label>ราคา</label><input type="number" step="0.01" name="price" class="form-control" required></div>
                <div class="mb-2"><label>จำนวนสต็อกเริ่มต้น</label><input type="number" name="stock_quantity" class="form-control" value="0" required></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: ปรับเพิ่ม/ตัดสต็อก (ข้อ 2) -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update_stock">
            <input type="hidden" name="product_id" id="stock_product_id">
            <div class="modal-header">
                <h5 class="modal-title">บันทึกเพิ่ม/ตัดรายการสินค้า: <span id="stock_product_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>ประเภทรายการ</label>
                    <select name="type" class="form-select" required>
                        <option value="IN">➕ รับสินค้าเข้า (เพิ่มสต็อก)</option>
                        <option value="OUT">➖ ตัดจ่ายสินค้า (ลดสต็อก)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>จำนวน</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="mb-3">
                    <label>หมายเหตุ</label>
                    <input type="text" name="note" class="form-control" placeholder="เช่น สั่งซื้อเพิ่ม / ขายออก">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">บันทึกรายการ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: แก้ไขสินค้า -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">แก้ไขสินค้า</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><label>ชื่อสินค้า</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div class="mb-2">
                    <label>ประเภทสินค้า</label>
                    <select name="category_id" id="edit_category_id" class="form-select" required>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-2"><label>ปีที่ผลิต (ค.ศ.)</label><input type="number" name="manufacture_year" id="edit_year" class="form-control" required></div>
                <div class="mb-2"><label>ราคา</label><input type="number" step="0.01" name="price" id="edit_price" class="form-control" required></div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-warning text-white">อัปเดตข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openStockModal(id, name) {
    document.getElementById('stock_product_id').value = id;
    document.getElementById('stock_product_name').innerText = name;
    new bootstrap.Modal(document.getElementById('stockModal')).show();
}

function openEditModal(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('edit_year').value = product.manufacture_year;
    document.getElementById('edit_price').value = product.price;
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}
</script>
</body>
</html>