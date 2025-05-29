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

// Handle invoice generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_invoice'])) {
    $quotation_id = $_POST['quotation_id'];
    
    // Get quotation details
    $stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $quotation_id, $user_id);
    $stmt->execute();
    $quotation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($quotation) {
        // Check if invoice already exists
        $stmt = $conn->prepare("SELECT id FROM invoices WHERE quotation_id = ?");
        $stmt->bind_param("i", $quotation_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $message = "Invoice already exists for this quotation";
            $message_type = "error";
        } else {
            // Create invoice
            $stmt = $conn->prepare("INSERT INTO invoices 
                                  (quotation_id, user_id, client_id, business_id, amount, date_created, status) 
                                  VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending')");
            $stmt->bind_param("iiiid", $quotation_id, $user_id, $quotation['client_id'], 
                             $quotation['business_id'], $quotation['amount']);
            
            if ($stmt->execute()) {
                $message = "Invoice generated successfully!";
                $message_type = "success";
            } else {
                $message = "Error generating invoice: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    } else {
        $message = "Quotation not found";
        $message_type = "error";
    }
}

// Get user's quotations
$quotations = [];
$stmt = $conn->prepare("SELECT q.*, b.name as business_name, c.name as client_name 
                       FROM quotations q
                       JOIN business b ON q.business_id = b.id
                       JOIN clients c ON q.client_id = c.id
                       WHERE q.user_id = ?
                       ORDER BY q.date_created DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get items for each quotation
    $items_stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $items_stmt->bind_param("i", $row['id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $row['items'] = [];
    
    while ($item = $items_result->fetch_assoc()) {
        $row['items'][] = $item;
    }
    
    $items_stmt->close();
    $quotations[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Quotations</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .quotation-list { margin-top: 20px; }
        .quotation-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .quotation-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .quotation-title { font-weight: bold; font-size: 1.2em; }
        .quotation-details { margin-bottom: 10px; }
        .quotation-actions { margin-top: 10px; }
        .quotation-actions a, .quotation-actions button { 
            padding: 5px 10px; margin-right: 5px; text-decoration: none; border-radius: 3px; 
            display: inline-block;
        }
        .view-btn { background-color: #2196F3; color: white; border: none; cursor: pointer; }
        .download-btn { background-color: #4CAF50; color: white; }
        .invoice-btn { background-color: #9C27B0; color: white; border: none; cursor: pointer; }
        
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; 
                overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; 
                        width: 80%; max-width: 800px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        
        .invoice-preview { border: 1px solid #ddd; padding: 20px; margin-top: 20px; background-color: white; }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .invoice-title { font-size: 1.5em; font-weight: bold; }
        .invoice-details { margin-bottom: 20px; }
        .from-to { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .from, .to { width: 48%; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .invoice-table th { background-color: #f2f2f2; }
        .total-row { font-weight: bold; }
        .text-right { text-align: right; }
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
        <h2>My Quotations</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="quotation-list">
            <?php if (empty($quotations)): ?>
                <p>You haven't created any quotations yet.</p>
            <?php else: ?>
                <?php foreach ($quotations as $quotation): ?>
                    <div class="quotation-card">
                        <div class="quotation-header">
                            <div class="quotation-title">Quotation #<?php echo $quotation['id']; ?></div>
                            <div>Date: <?php echo $quotation['date_created']; ?></div>
                        </div>
                        
                        <div class="quotation-details">
                            <div><strong>Business:</strong> <?php echo htmlspecialchars($quotation['business_name']); ?></div>
                            <div><strong>Client:</strong> <?php echo htmlspecialchars($quotation['client_name']); ?></div>
                            <div><strong>Amount:</strong> <?php echo number_format($quotation['amount'], 2); ?></div>
                            <div><strong>Status:</strong> <?php echo ucfirst($quotation['status']); ?></div>
                        </div>
                        
                        <div class="quotation-actions">
                            <a href="#" onclick="viewQuotation(<?php echo $quotation['id']; ?>)" class="view-btn">View</a>
                            <a href="download_quotation.php?id=<?php echo $quotation['id']; ?>" class="download-btn">Download PDF</a>
                            <button onclick="showInvoiceModal(<?php echo $quotation['id']; ?>)" class="invoice-btn">Generate Invoice</button>
                        </div>
                    </div>
                    
                    <!-- Quotation View Modal -->
                    <div id="quotation-modal-<?php echo $quotation['id']; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="closeModal('quotation-modal-<?php echo $quotation['id']; ?>')">&times;</span>
                            <div class="quotation-preview">
                                <div class="invoice-header">
                                    <div class="invoice-title">QUOTATION</div>
                                    <div>
                                        <div><strong>Quotation ID:</strong> <?php echo $quotation['id']; ?></div>
                                        <div><strong>Date:</strong> <?php echo $quotation['date_created']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="from-to">
                                    <div class="from">
                                        <h4>From:</h4>
                                        <div><?php echo htmlspecialchars($quotation['business_name']); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['business_address'] ?? '-'); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['business_contact'] ?? '-'); ?></div>
                                    </div>
                                    
                                    <div class="to">
                                        <h4>To:</h4>
                                        <div><?php echo htmlspecialchars($quotation['client_name']); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['client_phone'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                
                                <table class="invoice-table">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th><?php echo $quotation['type'] == 'service' ? 'Service' : 'Product'; ?></th>
                                            <th>Description</th>
                                            <?php if ($quotation['type'] == 'product'): ?>
                                                <th>Qty</th>
                                            <?php endif; ?>
                                            <th>Price</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quotation['items'] as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <?php if ($quotation['type'] == 'product'): ?>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                <?php endif; ?>
                                                <td class="text-right"><?php echo number_format($item['price'], 2); ?></td>
                                                <td class="text-right">
                                                    <?php echo number_format($item['price'] * ($item['quantity'] ?? 1), 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="<?php echo $quotation['type'] == 'service' ? 4 : 5; ?>">Total</td>
                                            <td class="text-right"><?php echo number_format($quotation['amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Generation Modal -->
                    <div id="invoice-modal-<?php echo $quotation['id']; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="closeModal('invoice-modal-<?php echo $quotation['id']; ?>')">&times;</span>
                            <h3>Generate Invoice from Quotation #<?php echo $quotation['id']; ?></h3>
                            
                            <div class="invoice-preview">
                                <div class="invoice-header">
                                    <div class="invoice-title">INVOICE</div>
                                    <div>
                                        <div><strong>Invoice Date:</strong> <?php echo date('Y-m-d'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="from-to">
                                    <div class="from">
                                        <h4>From:</h4>
                                        <div><?php echo htmlspecialchars($quotation['business_name']); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['business_address'] ?? '-'); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['business_contact'] ?? '-'); ?></div>
                                    </div>
                                    
                                    <div class="to">
                                        <h4>To:</h4>
                                        <div><?php echo htmlspecialchars($quotation['client_name']); ?></div>
                                        <div><?php echo htmlspecialchars($quotation['client_phone'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="invoice-details">
                                    <div><strong>Reference:</strong> Quotation #<?php echo $quotation['id']; ?></div>
                                </div>
                                
                                <table class="invoice-table">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th><?php echo $quotation['type'] == 'service' ? 'Service' : 'Product'; ?></th>
                                            <th>Description</th>
                                            <?php if ($quotation['type'] == 'product'): ?>
                                                <th>Qty</th>
                                            <?php endif; ?>
                                            <th>Price</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quotation['items'] as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <?php if ($quotation['type'] == 'product'): ?>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                <?php endif; ?>
                                                <td class="text-right"><?php echo number_format($item['price'], 2); ?></td>
                                                <td class="text-right">
                                                    <?php echo number_format($item['price'] * ($item['quantity'] ?? 1), 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="<?php echo $quotation['type'] == 'service' ? 4 : 5; ?>">Total</td>
                                            <td class="text-right"><?php echo number_format($quotation['amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <form method="POST" style="margin-top: 20px;">
                                <input type="hidden" name="quotation_id" value="<?php echo $quotation['id']; ?>">
                                <button type="submit" name="generate_invoice" class="invoice-btn">Confirm Generate Invoice</button>
                                <button type="button" onclick="closeModal('invoice-modal-<?php echo $quotation['id']; ?>')" 
                                        style="background-color: #ccc; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">
                                    Cancel
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // View quotation modal
        function viewQuotation(id) {
            document.getElementById('quotation-modal-' + id).style.display = 'block';
        }
        
        // Show invoice generation modal
        function showInvoiceModal(id) {
            document.getElementById('invoice-modal-' + id).style.display = 'block';
        }
        
        // Close modal
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        };
    </script>
</body>
</html>