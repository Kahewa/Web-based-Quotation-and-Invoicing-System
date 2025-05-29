<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/server/conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get user's businesses and clients
$businesses = [];
$clients = [];

// Get businesses
$stmt = $conn->prepare("SELECT * FROM business WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
$stmt->close();

// Get clients
$stmt = $conn->prepare("SELECT c.*, b.name as business_name 
                       FROM clients c
                       JOIN business b ON c.business_id = b.id
                       WHERE b.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_quotation'])) {
    $business_id = $_POST['business_id'];
    $client_id = $_POST['client_id'];
    $type = $_POST['type'];
    $items = json_decode($_POST['items'], true);
    
    // Validate business belongs to user
    $valid_business = false;
    foreach ($businesses as $business) {
        if ($business['id'] == $business_id) {
            $valid_business = true;
            break;
        }
    }
    
    // Validate client belongs to user
    $valid_client = false;
    foreach ($clients as $client) {
        if ($client['id'] == $client_id) {
            $valid_client = true;
            break;
        }
    }
    
    if (!$valid_business || !$valid_client) {
        $message = "Invalid business or client selected";
        $message_type = "error";
    } elseif (empty($items)) {
        $message = "Please add at least one item to the quotation";
        $message_type = "error";
    } else {
        // Calculate total amount
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['price'] * (isset($item['quantity']) ? $item['quantity'] : 1);
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert quotation
            $stmt = $conn->prepare("INSERT INTO quotations (user_id, client_id, business_id, type, amount, date_created, status) 
                                  VALUES (?, ?, ?, ?, ?, NOW(), 'draft')");
            $stmt->bind_param("iiisd", $user_id, $client_id, $business_id, $type, $total_amount);
            $stmt->execute();
            $quotation_id = $conn->insert_id;
            $stmt->close();
            
            // Insert quotation items
            foreach ($items as $item) {
                $stmt = $conn->prepare("INSERT INTO quotation_items (quotation_id, item_type, description, quantity, price) 
                                      VALUES (?, ?, ?, ?, ?)");
                $item_type = $type;
                $item_name = $item['name'];
                $description = $item['description'] ?? '';
                $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                $price = $item['price'];
                
                $stmt->bind_param("issid", $quotation_id, $item_type, $description, $quantity, $price);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            $message = "Quotation #$quotation_id created successfully!";
            $message_type = "success";
            
            // Clear form (optional)
            echo '<script>document.getElementById("quotation-form").reset();</script>';
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error creating quotation: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quotation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .form-container { max-width: 900px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #45a049; }
        
        .toggle-container { display: flex; margin: 15px 0; }
        .toggle-btn { flex: 1; padding: 10px; text-align: center; cursor: pointer; }
        .toggle-btn.active { background-color: #4CAF50; color: white; }
        
        .items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        
        .add-item-btn { margin-bottom: 15px; }
        .total-amount { font-size: 1.2em; font-weight: bold; text-align: right; margin-top: 10px; }
        
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        
        .quotation-preview { border: 1px solid #ddd; padding: 20px; margin-top: 20px; background-color: white; }
        .quotation-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .quotation-title { font-size: 1.5em; font-weight: bold; }
        .quotation-details { margin-bottom: 20px; }
        .from-to { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .from, .to { width: 48%; }
    </style>
</head>
<body>
    <div class="header">
        <h2>User Dashboard</h2>
        <form action="logout.php" method="post">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
    
    <div class="sidebar">
        <a href="user_dashboard.php">Dashboard</a>
        <a href="my_business.php">My Business</a>
        <a href="clients.php">Clients</a>
        <a href="create_quotation.php">Create Quotation</a>
        <a href="my_quotations.php">My Quotations</a>
        <a href="my_invoices.php">My Invoices</a>
    </div>
    
    <div class="content">
        <div class="form-container">
            <h2>Create New Quotation</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <form id="quotation-form" method="POST">
                <div class="form-group">
                    <label for="business_id">Your Business:</label>
                    <select id="business_id" name="business_id" required>
                        <option value="">Select Your Business</option>
                        <?php foreach ($businesses as $business): ?>
                            <option value="<?php echo $business['id']; ?>"><?php echo htmlspecialchars($business['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="client_id">Client:</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="toggle-container">
                    <div id="service-toggle" class="toggle-btn active" onclick="setType('service')">Service</div>
                    <div id="product-toggle" class="toggle-btn" onclick="setType('product')">Product</div>
                    <input type="hidden" id="type" name="type" value="service">
                </div>
                
                <div id="service-section">
                    <h3>Services</h3>
                    <button type="button" class="add-item-btn" onclick="addServiceItem()">+ Add Service</button>
                    <table class="items-table" id="service-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Service</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <div id="product-section" style="display: none;">
                    <h3>Products</h3>
                    <button type="button" class="add-item-btn" onclick="addProductItem()">+ Add Product</button>
                    <table class="items-table" id="product-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Product</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <div class="total-amount">
                    Total Amount: <span id="total-amount">0.00</span>
                </div>
                
                <input type="hidden" id="items" name="items" value="[]">
                <button type="submit" name="create_quotation">Create Quotation</button>
            </form>
            
            <div class="quotation-preview" id="quotation-preview">
                <div class="quotation-header">
                    <div class="quotation-title">QUOTATION</div>
                    <div>
                        <div><strong>Quotation ID:</strong> <span id="preview-quotation-id">-</span></div>
                        <div><strong>Date:</strong> <span id="preview-date"><?php echo date('Y-m-d'); ?></span></div>
                    </div>
                </div>
                
                <div class="from-to">
                    <div class="from">
                        <h4>From:</h4>
                        <div id="preview-business-name">-</div>
                        <div id="preview-business-address">-</div>
                        <div id="preview-business-contact">-</div>
                    </div>
                    
                    <div class="to">
                        <h4>To:</h4>
                        <div id="preview-client-name">-</div>
                        <div id="preview-client-phone">-</div>
                    </div>
                </div>
                
                <div id="preview-items">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background-color: #f2f2f2;">
                                <th style="padding: 8px; border: 1px solid #ddd;">No.</th>
                                <th style="padding: 8px; border: 1px solid #ddd;">Item</th>
                                <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                                <th style="padding: 8px; border: 1px solid #ddd;">Qty</th>
                                <th style="padding: 8px; border: 1px solid #ddd;">Price</th>
                                <th style="padding: 8px; border: 1px solid #ddd;">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="preview-items-body"></tbody>
                    </table>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <div style="font-weight: bold;">Total Amount: <span id="preview-total-amount">0.00</span></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables
        let items = [];
        let nextId = 1;
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event listeners
            document.getElementById('business_id').addEventListener('change', updatePreview);
            document.getElementById('client_id').addEventListener('change', updatePreview);
            
            // Add first empty item
            addServiceItem();
        });
        
        // Set quotation type (service/product)
        function setType(type) {
            document.getElementById('type').value = type;
            
            if (type === 'service') {
                document.getElementById('service-toggle').classList.add('active');
                document.getElementById('product-toggle').classList.remove('active');
                document.getElementById('service-section').style.display = 'block';
                document.getElementById('product-section').style.display = 'none';
            } else {
                document.getElementById('service-toggle').classList.remove('active');
                document.getElementById('product-toggle').classList.add('active');
                document.getElementById('service-section').style.display = 'none';
                document.getElementById('product-section').style.display = 'block';
            }
            
            // Clear items when switching types
            items = [];
            nextId = 1;
            updateItemsTable();
            updatePreview();
        }
        
        // Add a new service item
        function addServiceItem() {
            const item = {
                id: nextId++,
                type: 'service',
                name: '',
                description: '',
                price: 0
            };
            items.push(item);
            updateItemsTable();
        }
        
        // Add a new product item
        function addProductItem() {
            const item = {
                id: nextId++,
                type: 'product',
                name: '',
                description: '',
                quantity: 1,
                price: 0
            };
            items.push(item);
            updateItemsTable();
        }
        
        // Update item in the array
        function updateItem(id, field, value) {
            const item = items.find(item => item.id == id);
            if (item) {
                item[field] = field === 'price' || field === 'quantity' ? parseFloat(value) : value;
                updateItemsTable();
                updatePreview();
            }
        }
        
        // Remove item from the array
        function removeItem(id) {
            items = items.filter(item => item.id != id);
            updateItemsTable();
            updatePreview();
        }
        
        // Update the items table display
        function updateItemsTable() {
            const type = document.getElementById('type').value;
            const tableBody = type === 'service' 
                ? document.querySelector('#service-table tbody')
                : document.querySelector('#product-table tbody');
            
            // Clear table
            tableBody.innerHTML = '';
            
            // Calculate total
            let total = 0;
            
            // Add rows for each item
            items.forEach((item, index) => {
                if ((type === 'service' && item.type === 'service') || 
                    (type === 'product' && item.type === 'product')) {
                    
                    const row = document.createElement('tr');
                    
                    // Number column
                    const numCell = document.createElement('td');
                    numCell.textContent = index + 1;
                    row.appendChild(numCell);
                    
                    // Name column
                    const nameCell = document.createElement('td');
                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.value = item.name;
                    nameInput.onchange = (e) => updateItem(item.id, 'name', e.target.value);
                    nameCell.appendChild(nameInput);
                    row.appendChild(nameCell);
                    
                    // Description column
                    const descCell = document.createElement('td');
                    const descInput = document.createElement('input');
                    descInput.type = 'text';
                    descInput.value = item.description;
                    descInput.onchange = (e) => updateItem(item.id, 'description', e.target.value);
                    descCell.appendChild(descInput);
                    row.appendChild(descCell);
                    
                    // Quantity column (for products only)
                    if (type === 'product') {
                        const qtyCell = document.createElement('td');
                        const qtyInput = document.createElement('input');
                        qtyInput.type = 'number';
                        qtyInput.min = '1';
                        qtyInput.value = item.quantity;
                        qtyInput.style.width = '60px';
                        qtyInput.onchange = (e) => updateItem(item.id, 'quantity', e.target.value);
                        qtyCell.appendChild(qtyInput);
                        row.appendChild(qtyCell);
                    }
                    
                    // Price column
                    const priceCell = document.createElement('td');
                    const priceInput = document.createElement('input');
                    priceInput.type = 'number';
                    priceInput.min = '0';
                    priceInput.step = '0.01';
                    priceInput.value = item.price;
                    priceInput.style.width = '80px';
                    priceInput.onchange = (e) => updateItem(item.id, 'price', e.target.value);
                    priceCell.appendChild(priceInput);
                    row.appendChild(priceCell);
                    
                    // Action column
                    const actionCell = document.createElement('td');
                    const removeBtn = document.createElement('button');
                    removeBtn.textContent = 'Remove';
                    removeBtn.style.backgroundColor = '#f44336';
                    removeBtn.onclick = () => removeItem(item.id);
                    actionCell.appendChild(removeBtn);
                    row.appendChild(actionCell);
                    
                    tableBody.appendChild(row);
                    
                    // Add to total
                    total += item.price * (item.quantity || 1);
                }
            });
            
            // Update total amount
            document.getElementById('total-amount').textContent = total.toFixed(2);
            
            // Update hidden field with JSON data
            document.getElementById('items').value = JSON.stringify(items);
        }
        
        // Update the preview section
        function updatePreview() {
            const businessId = document.getElementById('business_id').value;
            const clientId = document.getElementById('client_id').value;
            const type = document.getElementById('type').value;
            
            // Find selected business
            const business = <?php echo json_encode($businesses); ?>.find(b => b.id == businessId);
            if (business) {
                document.getElementById('preview-business-name').textContent = business.name;
                document.getElementById('preview-business-address').textContent = business.address || '-';
                document.getElementById('preview-business-contact').textContent = business.contact || '-';
            } else {
                document.getElementById('preview-business-name').textContent = '-';
                document.getElementById('preview-business-address').textContent = '-';
                document.getElementById('preview-business-contact').textContent = '-';
            }
            
            // Find selected client
            const client = <?php echo json_encode($clients); ?>.find(c => c.id == clientId);
            if (client) {
                document.getElementById('preview-client-name').textContent = client.name;
                document.getElementById('preview-client-phone').textContent = client.phone || '-';
            } else {
                document.getElementById('preview-client-name').textContent = '-';
                document.getElementById('preview-client-phone').textContent = '-';
            }
            
            // Update items preview
            const previewBody = document.getElementById('preview-items-body');
            previewBody.innerHTML = '';
            
            let total = 0;
            
            items.forEach((item, index) => {
                if ((type === 'service' && item.type === 'service') || 
                    (type === 'product' && item.type === 'product')) {
                    
                    const row = document.createElement('tr');
                    
                    // Number
                    const numCell = document.createElement('td');
                    numCell.textContent = index + 1;
                    numCell.style.padding = '8px';
                    numCell.style.border = '1px solid #ddd';
                    row.appendChild(numCell);
                    
                    // Name
                    const nameCell = document.createElement('td');
                    nameCell.textContent = item.name || '-';
                    nameCell.style.padding = '8px';
                    nameCell.style.border = '1px solid #ddd';
                    row.appendChild(nameCell);
                    
                    // Description
                    const descCell = document.createElement('td');
                    descCell.textContent = item.description || '-';
                    descCell.style.padding = '8px';
                    descCell.style.border = '1px solid #ddd';
                    row.appendChild(descCell);
                    
                    // Quantity (for products only)
                    const qtyCell = document.createElement('td');
                    qtyCell.textContent = type === 'product' ? (item.quantity || 1) : '-';
                    qtyCell.style.padding = '8px';
                    qtyCell.style.border = '1px solid #ddd';
                    qtyCell.style.textAlign = 'center';
                    row.appendChild(qtyCell);
                    
                    // Price
                    const priceCell = document.createElement('td');
                    priceCell.textContent = item.price ? item.price.toFixed(2) : '0.00';
                    priceCell.style.padding = '8px';
                    priceCell.style.border = '1px solid #ddd';
                    priceCell.style.textAlign = 'right';
                    row.appendChild(priceCell);
                    
                    // Amount
                    const amount = item.price * (item.quantity || 1);
                    const amountCell = document.createElement('td');
                    amountCell.textContent = amount.toFixed(2);
                    amountCell.style.padding = '8px';
                    amountCell.style.border = '1px solid #ddd';
                    amountCell.style.textAlign = 'right';
                    row.appendChild(amountCell);
                    
                    previewBody.appendChild(row);
                    
                    total += amount;
                }
            });
            
            // Update total
            document.getElementById('preview-total-amount').textContent = total.toFixed(2);
        }
    </script>
</body>
</html>