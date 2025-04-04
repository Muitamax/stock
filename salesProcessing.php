<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'inventory.php';
require_once 'sales.php';

$auth = new Auth($pdo);
$auth->requireAuth();

$inventory = new Inventory($pdo);
$sales = new Sales($pdo);

// Process sale if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_sale'])) {
    $items = [];
    
    foreach ($_POST['product_id'] as $key => $product_id) {
        if ($product_id && $_POST['quantity'][$key] > 0) {
            $items[] = [
                'product_id' => $product_id,
                'quantity' => (int)$_POST['quantity'][$key]
            ];
        }
    }
    
    if (!empty($items)) {
        try {
            $sale_id = $sales->processSale(
                $_SESSION['user']['id'],
                $items,
                $_POST['payment_method']
            );
            
            $_SESSION['success'] = "Sale processed successfully! Sale ID: $sale_id";
            header("Location: sales.php");
            exit();
        } catch (Exception $e) {
            $error = "Error processing sale: " . $e->getMessage();
        }
    } else {
        $error = "No items selected for sale";
    }
}

// Get products for dropdown
$products = $inventory->getProducts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supermarket System - Sales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Process Sale</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" id="sale-form">
            <div id="sale-items">
                <div class="row mb-3 sale-item">
                    <div class="col-md-6">
                        <label class="form-label">Product</label>
                        <select class="form-select product-select" name="product_id[]" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['product_id'] ?>" 
                                        data-price="<?= $product['price'] ?>"
                                        data-stock="<?= $product['quantity'] ?>">
                                    <?= htmlspecialchars($product['name']) ?> - $<?= number_format($product['price'], 2) ?> (Stock: <?= $product['quantity'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control quantity" name="quantity[]" min="1" value="1" required>
                        <small class="text-muted stock-message"></small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Price</label>
                        <input type="text" class="form-control price" readonly>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger w-100 remove-item" style="display: none;">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="button" id="add-item" class="btn btn-secondary mb-3">
                <i class="bi bi-plus"></i> Add Item
            </button>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="mobile_payment">Mobile Payment</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Amount</label>
                    <input type="text" class="form-control" id="total-amount" value="$0.00" readonly>
                </div>
            </div>
            
            <button type="submit" name="process_sale" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Process Sale
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add new item row
            document.getElementById('add-item').addEventListener('click', function() {
                const itemRow = document.querySelector('.sale-item').cloneNode(true);
                const selects = itemRow.querySelectorAll('select');
                const inputs = itemRow.querySelectorAll('input');
                
                // Reset values
                selects.forEach(select => select.value = '');
                inputs.forEach(input => {
                    if (input.type !== 'button') input.value = input.type === 'number' ? '1' : '';
                });
                
                // Show remove button
                itemRow.querySelector('.remove-item').style.display = 'block';
                itemRow.querySelector('.stock-message').textContent = '';
                
                document.getElementById('sale-items').appendChild(itemRow);
                addEventListeners(itemRow);
                updateTotal();
            });
            
            // Add event listeners to initial row
            addEventListeners(document.querySelector('.sale-item'));
            
            // Function to add event listeners to a row
            function addEventListeners(row) {
                const productSelect = row.querySelector('.product-select');
                const quantityInput = row.querySelector('.quantity');
                const priceInput = row.querySelector('.price');
                const stockMessage = row.querySelector('.stock-message');
                const removeBtn = row.querySelector('.remove-item');
                
                // Product select change
                productSelect.addEventListener('change', function() {
                    if (this.value) {
                        const selectedOption = this.options[this.selectedIndex];
                        priceInput.value = '$' + parseFloat(selectedOption.dataset.price).toFixed(2);
                        stockMessage.textContent = 'In stock: ' + selectedOption.dataset.stock;
                        
                        // Set max quantity
                        quantityInput.max = selectedOption.dataset.stock;
                        if (parseInt(quantityInput.value) > parseInt(selectedOption.dataset.stock)) {
                            quantityInput.value = selectedOption.dataset.stock;
                        }
                    } else {
                        priceInput.value = '';
                        stockMessage.textContent = '';
                    }
                    updateTotal();
                });
                
                // Quantity input change
                quantityInput.addEventListener('input', function() {
                    updateTotal();
                });
                
                // Remove button click
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        row.remove();
                        updateTotal();
                    });
                }
            }
            
            // Update total amount
            function updateTotal() {
                let total = 0;
                document.querySelectorAll('.sale-item').forEach(row => {
                    const productSelect = row.querySelector('.product-select');
                    const quantityInput = row.querySelector('.quantity');
                    const priceInput = row.querySelector('.price');
                    
                    if (productSelect.value && quantityInput.value) {
                        const price = parseFloat(productSelect.options[productSelect.selectedIndex].dataset.price);
                        const quantity = parseInt(quantityInput.value);
                        total += price * quantity;
                    }
                });
                
                document.getElementById('total-amount').value = '$' + total.toFixed(2);
            }
        });
    </script>
</body>
</html>