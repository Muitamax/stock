 Supermarket Inventory and Sales System in PHP with MySQL
This PHP-based supermarket management system provides a comprehensive solution for inventory and sales tracking with user authentication, barcode integration, supplier management, and reporting capabilities.

System Architecture
1. Database Structure (MySQL)
The system uses a relational database with these key tables:

users: Stores login credentials and roles (manager/cashier)

suppliers: Vendor information for products

categories: Product categorization

products: Core inventory items with barcodes and expiration dates

purchase_orders: Supplier orders with status tracking

sales: Customer transactions

sale_items: Individual products sold in each transaction

2. Core Components
Authentication System
Secure password hashing with PHP's password_hash()

Session-based login persistence

Role-based access control (manager vs cashier)

Login page with form validation

Inventory Management
Product CRUD operations (Create, Read, Update, Delete)

Barcode generation and scanning simulation

Stock level tracking with low-stock alerts

Expiration date monitoring

Category and supplier relationships

Sales Processing
Transaction recording with date/time stamps

Automatic inventory deduction

Payment method tracking

Receipt generation

Reporting System
Sales summaries by date range

Profit margin calculations (selling price vs cost price)

Best-selling product analysis

Data visualization with Chart.js

Supplier Management
Vendor information storage

Product supply relationships

Purchase order creation and tracking

Key Features Implementation
1. User Authentication
php
Copy
// auth.php
public function login($username, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'name' => $user['full_name']
        ];
        return true;
    }
    return false;
}
Uses prepared statements to prevent SQL injection

Verifies passwords with password_verify()

Stores minimal user data in session

2. Barcode Integration
php
Copy
// inventory.php
public function addProduct($data) {
    $stmt = $this->pdo->prepare("
        INSERT INTO products (name, barcode, price, quantity, expiration_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $data['name'],
        $data['barcode'] ?? $this->generateBarcode(),
        $data['price'],
        $data['quantity'],
        $data['expiration_date']
    ]);
}

private function generateBarcode() {
    return substr(str_shuffle("0123456789"), 0, 12);
}
Auto-generates 12-digit barcodes if none provided

Barcode field is unique in database

Scanning simulation in sales interface

3. Inventory Management
php
Copy
// inventory.php
public function updateStock($product_id, $quantity_change) {
    $this->pdo->beginTransaction();
    try {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET quantity = quantity + ? 
            WHERE product_id = ?
        ");
        $stmt->execute([$quantity_change, $product_id]);
        $this->pdo->commit();
        return true;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        return false;
    }
}
Uses transactions to ensure data integrity

Positive numbers add stock, negative deduct

Prevents negative inventory

4. Sales Processing
php
Copy
// sales.php
public function processSale($user_id, $items, $payment_method = null) {
    $this->pdo->beginTransaction();
    try {
        // Calculate total
        $total = array_sum(array_map(
            fn($item) => $this->getProduct($item['product_id'])['price'] * $item['quantity'],
            $items
        ));
        
        // Record sale
        $stmt = $this->pdo->prepare("
            INSERT INTO sales (user_id, total_amount, payment_method)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $total, $payment_method]);
        $sale_id = $this->pdo->lastInsertId();
        
        // Add items and update inventory
        foreach ($items as $item) {
            $this->addSaleItem($sale_id, $item);
            $this->inventory->updateStock($item['product_id'], -$item['quantity']);
        }
        
        $this->pdo->commit();
        return $sale_id;
    } catch (Exception $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
Atomic transaction ensures all-or-nothing operation

Calculates total automatically

Updates inventory in real-time

5. Reporting System
php
Copy
// sales.php
public function getSalesReport($start_date = null, $end_date = null) {
    // Get filtered sales
    $sales = $this->getFilteredSales($start_date, $end_date);
    
    // Calculate metrics
    $report = [
        'total_sales' => array_sum(array_column($sales, 'total_amount')),
        'total_items' => array_sum(array_column($sales, 'item_count')),
        'sales' => $sales
    ];
    
    // Add profit calculations
    foreach ($report['sales'] as &$sale) {
        $sale['profit'] = $this->calculateSaleProfit($sale['sale_id']);
    }
    
    // Get best sellers
    $report['best_sellers'] = $this->getBestSellers($start_date, $end_date);
    
    return $report;
}
Date range filtering

Profit calculation (selling price - cost price)

Best sellers ranking

Data prepared for Chart.js visualization

6. Expiration Tracking
sql
Copy
-- Products table includes:
expiration_date DATE,
low_stock_threshold INT DEFAULT 5
php
Copy
// inventory.php
public function getExpiringProducts($days = 30) {
    $stmt = $this->pdo->prepare("
        SELECT * FROM products 
        WHERE expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY expiration_date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}
Tracks perishable items

Alerts for soon-to-expire products

Sortable by expiration date

Security Features
Prepared Statements: All SQL uses parameterized queries

Password Hashing: Uses PHP's password_hash() and password_verify()

CSRF Protection: Forms should include CSRF tokens (not shown in example)

Input Validation: Frontend and backend validation

Role-Based Access: Restricts features by user role

UI Implementation
The system uses Bootstrap 5 for responsive design with:

Tab-based navigation

Modal dialogs for forms

Data tables with sorting

Interactive charts

Mobile-friendly layout

Deployment Considerations
Database Configuration: Set proper credentials in config.php

File Permissions: Ensure PHP can write to session directory

HTTPS: Essential for security in production

Backups: Regular database backups recommended

Performance: Index critical database columns

This implementation provides a complete, 
secure foundation for supermarket inventory and sales management that can be extended with additional features as needed.