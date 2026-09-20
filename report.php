<?php
require_once 'db.php';

// ฟังก์ชันส่งออก CSV (ข้อ 5: ความสามารถเพิ่มเติม)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=stock_report_'.date('Y-m-d').'.csv');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM สำหรับอ่านภาษาไทยบน Excel
    fputcsv($output, ['รหัสสินค้า', 'ชื่อสินค้า', 'ประเภท', 'ปีที่ผลิต', 'ราคา', 'คงเหลือ']);
    
    $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [$row['code'], $row['name'], $row['category_name'], $row['manufacture_year'], $row['price'], $row['stock_quantity']]);
    }
    fclose($output);
    exit;
}

// รายงาน 1: สินค้าที่มีสต็อกเหลือน้อยกว่า 5 ชิ้น (ข้อ 3)
$low_stock = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock_quantity < 5");

// รายงาน 2: ประวัติการเพิ่ม/ตัดสต็อกล่าสุด (ข้อ 3)
$transactions = $conn->query("SELECT t.*, p.name AS product_name, p.code FROM stock_transactions t JOIN products p ON t.product_id = p.id ORDER BY t.created_at DESC LIMIT 20");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานตามเงื่อนไข</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">ระบบจัดการสต็อกสินค้า</a>
        <div class="navbar-nav">
            <a class="nav-link" href="index.php">จัดการสินค้า & สต็อก</a>
            <a class="nav-link active" href="report.php">รายงานสรุป</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📊 รายงานตามเงื่อนไข (ข้อ 3)</h3>
        <!-- ฟังก์ชันเพิ่มเติม (ข้อ 5) -->
        <a href="report.php?export=csv" class="btn btn-outline-success">📥 ส่งออกรายงานเป็น CSV / Excel (ข้อ 5)</a>
    </div>

    <!-- รายงานที่ 1 -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-danger text-white fw-bold">⚠️ รายงานสินค้าที่ต้องเติมสต็อกด่วน (คงเหลือน้อยกว่า 5 ชิ้น)</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>ประเภท</th>
                        <th>คงเหลือ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($low_stock->num_rows > 0): ?>
                        <?php while ($row = $low_stock->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['code']) ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><span class="badge bg-danger fs-6"><?= $row['stock_quantity'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">ไม่มีสินค้าที่สต็อกต่ำกว่าเกณฑ์</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- รายงานที่ 2 -->
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white fw-bold">📜 รายงานประวัติการเคลื่อนไหวสต็อกล่าสุด</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>วัน-เวลา</th>
                        <th>สินค้า</th>
                        <th>ประเภทรายการ</th>
                        <th>จำนวน</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions->num_rows > 0): ?>
                        <?php while ($t = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?= $t['created_at'] ?></td>
                                <td><?= htmlspecialchars($t['code'] . ' - ' . $t['product_name']) ?></td>
                                <td>
                                    <?php if ($t['type'] === 'IN'): ?>
                                        <span class="badge bg-success">รับเข้า (+)</span
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">ตัดจ่าย (-)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $t['quantity'] ?></td>
                                <td><?= htmlspecialchars($t['note']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">ยังไม่มีประวัติการทำรายการ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>