<?php
require_once 'config.php';

class Inventory {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Product management
    public function addProduct($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO products (name, description, barcode, price, cost_price, quantity, 
                                 category_id, supplier_id, expiration_date, low_stock_threshold)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['barcode'],
            $data['price'],
            $data['cost_price'],
            $data['quantity'],
            $data['category_id'],
            $data['supplier_id'],
            $data['expiration_date'],
            $data['low_stock_threshold']
        ]);
    }
    
    public function updateProduct($product_id, $data) {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, barcode = ?, price = ?, cost_price = ?, 
                quantity = ?, category_id = ?, supplier_id = ?, expiration_date = ?, 
                low_stock_threshold = ?
            WHERE product_id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['barcode'],
            $data['price'],
            $data['cost_price'],
            $data['quantity'],
            $data['category_id'],
            $data['supplier_id'],
            $data['expiration_date'],
            $data['low_stock_threshold'],
            $product_id
        ]);
    }
    
    public function getProduct($product_id) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name as category_name, s.name as supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            WHERE p.product_id = ?
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    }
    
    public function getProducts($filter = []) {
        $sql = "
            SELECT p.*, c.name as category_name, s.name as supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filter['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filter['category_id'];
        }
        
        if (!empty($filter['supplier_id'])) {
            $sql .= " AND p.supplier_id = ?";
            $params[] = $filter['supplier_id'];
        }
        
        if (!empty($filter['low_stock'])) {
            $sql .= " AND p.quantity <= p.low_stock_threshold";
        }
        
        if (!empty($filter['expiring_soon'])) {
            $sql .= " AND p.expiration_date IS NOT NULL AND p.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
        
        $sql .= " ORDER BY p.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updateStock($product_id, $quantity_change) {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET quantity = quantity + ? 
            WHERE product_id = ?
        ");
        return $stmt->execute([$quantity_change, $product_id]);
    }
    
    public function getProductByBarcode($barcode) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE barcode = ?");
        $stmt->execute([$barcode]);
        return $stmt->fetch();
    }
    
    // Category management
    public function getCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // Supplier management
    public function getSuppliers() {
        $stmt = $this->pdo->query("SELECT * FROM suppliers ORDER BY name");
        return $stmt->fetchAll();
    }
    
    // Purchase orders
    public function createPurchaseOrder($supplier_id, $items, $expected_delivery = null, $notes = null) {
        $this->pdo->beginTransaction();
        
        try {
            // Calculate total amount
            $total = 0;
            foreach ($items as $item) {
                $total += $item['quantity'] * $item['unit_price'];
            }
            
            // Create order
            $stmt = $this->pdo->prepare("
                INSERT INTO purchase_orders (supplier_id, expected_delivery_date, total_amount, notes)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$supplier_id, $expected_delivery, $total, $notes]);
            $order_id = $this->pdo->lastInsertId();
            
            // Add order items
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['quantity'] * $item['unit_price']
                ]);
            }
            
            $this->pdo->commit();
            return $order_id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function receiveOrder($order_id) {
        $this->pdo->beginTransaction();
        
        try {
            // Get order items
            $stmt = $this->pdo->prepare("
                SELECT oi.product_id, oi.quantity, p.name as product_name
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.product_id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();
            
            // Update product quantities
            foreach ($items as $item) {
                if ($item['product_id']) {
                    $this->updateStock($item['product_id'], $item['quantity']);
                }
            }
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE purchase_orders 
                SET status = 'received' 
                WHERE order_id = ?
            ");
            $stmt->execute([$order_id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getOrders($status = null) {
        $sql = "
            SELECT po.*, s.name as supplier_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.supplier_id
        ";
        
        $params = [];
        
        if ($status) {
            $sql .= " WHERE po.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY po.order_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getOrderItems($order_id) {
        $stmt = $this->pdo->prepare("
            SELECT oi.*, p.barcode
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    }
}
?>