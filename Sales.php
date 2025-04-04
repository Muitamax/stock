<?php
require_once 'config.php';
require_once 'inventory.php';

class Sales {
    private $pdo;
    private $inventory;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->inventory = new Inventory($pdo);
    }
    
    public function processSale($user_id, $items, $payment_method = null) {
        $this->pdo->beginTransaction();
        
        try {
            // Calculate total amount
            $total = 0;
            foreach ($items as $item) {
                $product = $this->inventory->getProduct($item['product_id']);
                $total += $item['quantity'] * $product['price'];
            }
            
            // Create sale record
            $stmt = $this->pdo->prepare("
                INSERT INTO sales (user_id, total_amount, payment_method)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user_id, $total, $payment_method]);
            $sale_id = $this->pdo->lastInsertId();
            
            // Add sale items and update inventory
            foreach ($items as $item) {
                $product = $this->inventory->getProduct($item['product_id']);
                
                $stmt = $this->pdo->prepare("
                    INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sale_id,
                    $item['product_id'],
                    $item['quantity'],
                    $product['price'],
                    $item['quantity'] * $product['price']
                ]);
                
                // Update inventory
                $this->inventory->updateStock($item['product_id'], -$item['quantity']);
            }
            
            $this->pdo->commit();
            return $sale_id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getSalesReport($start_date = null, $end_date = null) {
        $sql = "
            SELECT s.*, u.full_name as cashier_name,
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) as item_count
            FROM sales s
            JOIN users u ON s.user_id = u.user_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($start_date) {
            $sql .= " AND s.sale_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $sql .= " AND s.sale_date <= ?";
            $params[] = $end_date;
        }
        
        $sql .= " ORDER BY s.sale_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
        
        // Calculate totals
        $total_sales = 0;
        $total_items = 0;
        $total_profit = 0;
        
        foreach ($sales as &$sale) {
            $total_sales += $sale['total_amount'];
            $total_items += $sale['item_count'];
            
            // Get sale items for profit calculation
            $stmt = $this->pdo->prepare("
                SELECT si.*, p.cost_price
                FROM sale_items si
                JOIN products p ON si.product_id = p.product_id
                WHERE si.sale_id = ?
            ");
            $stmt->execute([$sale['sale_id']]);
            $items = $stmt->fetchAll();
            
            $sale_profit = 0;
            foreach ($items as $item) {
                $sale_profit += ($item['unit_price'] - $item['cost_price']) * $item['quantity'];
            }
            
            $sale['profit'] = $sale_profit;
            $total_profit += $sale_profit;
        }
        
        // Get best selling products
        $sql = "
            SELECT p.product_id, p.name, SUM(si.quantity) as total_quantity, 
                   SUM(si.total_price) as total_revenue,
                   SUM((si.unit_price - p.cost_price) * si.quantity) as total_profit
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($start_date) {
            $sql .= " AND s.sale_date >= ?";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $sql .= " AND s.sale_date <= ?";
            $params[] = $end_date;
        }
        
        $sql .= " GROUP BY p.product_id ORDER BY total_quantity DESC LIMIT 10";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $best_sellers = $stmt->fetchAll();
        
        return [
            'sales' => $sales,
            'total_sales' => $total_sales,
            'total_items' => $total_items,
            'total_profit' => $total_profit,
            'best_sellers' => $best_sellers
        ];
    }
    
    public function getSaleDetails($sale_id) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.full_name as cashier_name
            FROM sales s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT si.*, p.name as product_name, p.barcode
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            WHERE si.sale_id = ?
        ");
        $stmt->execute([$sale_id]);
        $items = $stmt->fetchAll();
        
        $sale['items'] = $items;
        return $sale;
    }
}
?>