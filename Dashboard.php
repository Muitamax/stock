<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'inventory.php';
require_once 'sales.php';

$auth = new Auth($pdo);
$auth->requireAuth();

$inventory = new Inventory($pdo);
$sales = new Sales($pdo);

// Get stats for dashboard
$product_count = $inventory->pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock = $inventory->pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= low_stock_threshold")->fetchColumn();
$expiring_soon = $inventory->pdo->query("SELECT COUNT(*) FROM products WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Recent sales
$recent_sales = $sales->pdo->query("
    SELECT s.sale_id, s.sale_date, s.total_amount, u.full_name as cashier
    FROM sales s
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC
    LIMIT 5
")->fetchAll();

// Low stock items
$low_stock_items = $inventory->pdo->query("
    SELECT p.product_id, p.name, p.quantity, p.low_stock_threshold
    FROM products p
    WHERE p.quantity <= p.low_stock_threshold
    ORDER BY p.quantity ASC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supermarket System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <p class="card-text display-4"><?= $product_count ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Low Stock Items</h5>
                        <p class="card-text display-4"><?= $low_stock ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-danger mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Expiring Soon</h5>
                        <p class="card-text display-4"><?= $expiring_soon ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Sales</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Cashier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><?= $sale['sale_id'] ?></td>
                                    <td><?= date('M j, H:i', strtotime($sale['sale_date'])) ?></td>
                                    <td>$<?= number_format($sale['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($sale['cashier']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Low Stock Items</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current</th>
                                    <th>Threshold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= $item['low_stock_threshold'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>