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

// Get user's invoices
$invoices = [];
$stmt = $conn->prepare("SELECT i.*, b.name as business_name, c.name as client_name, q.id as quotation_id
                       FROM invoices i
                       JOIN business b ON i.business_id = b.id
                       JOIN clients c ON i.client_id = c.id
                       LEFT JOIN quotations q ON i.quotation_id = q.id
                       WHERE i.user_id = ?
                       ORDER BY i.date_created DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get items for each invoice (from quotation if exists)
    if ($row['quotation_id']) {
        $items_stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
        $items_stmt->bind_param("i", $row['quotation_id']);
    } else {
        // If you have direct invoice items, you would query them here
        // For now we'll just use empty array
        $row['items'] = [];
        $invoices[] = $row;
        continue;
    }
    
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $row['items'] = [];
    
    while ($item = $items_result->fetch_assoc()) {
        $row['items'][] = $item;
    }
    
    $items_stmt->close();
    $invoices[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .invoice-list { margin-top: 20px; }
        .invoice-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .invoice-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .invoice-title { font-weight: bold; font-size: 1.2em; }
        .invoice-details { margin-bottom: 10px; }
        .invoice-actions { margin-top: 10px; }
        .invoice-actions a { 
            padding: 5px 10px; margin-right: 5px; text-decoration: none; border-radius: 3px; 
            display: inline-block;
        }
        .view-btn { background-color: #2196F3; color: white; }
        .download-btn { background-color: #4CAF50; color: white; }
        
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; 
                overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; 
                        width: 80%; max-width: 800px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .invoice-preview { border: 1px solid #ddd; padding: 20px; margin-top: 20px; background-color: white; }
        .invoice-header-preview { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .invoice-title-preview { font-size: 1.5em; font-weight: bold; }
        .invoice-details-preview { margin-bottom: 20px; }
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
        <h2>My Invoices</h2>
        
        <div class="invoice-list">
            <?php if (empty($invoices)): ?>
                <p>You haven't generated any invoices yet.</p>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <div class="invoice-card">
                        <div class="invoice-header">
                            <div class="invoice-title">Invoice #<?php echo $invoice['id']; ?></div>
                            <div>Date: <?php echo $invoice['date_created']; ?></div>
                        </div>
                        
                        <div class="invoice-details">
                            <div><strong>Business:</strong> <?php echo htmlspecialchars($invoice['business_name']); ?></div>
                            <div><strong>Client:</strong> <?php echo htmlspecialchars($invoice['client_name']); ?></div>
                            <div><strong>Amount:</strong> <?php echo number_format($invoice['amount'], 2); ?></div>
                            <div><strong>Status:</strong> <?php echo ucfirst($invoice['status']); ?></div>
                            <?php if ($invoice['quotation_id']): ?>
                                <div><strong>From Quotation:</strong> #<?php echo $invoice['quotation_id']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="invoice-actions">
                            <a href="#" onclick="viewInvoice(<?php echo $invoice['id']; ?>)" class="view-btn">View</a>
                            <a href="download_invoice.php?id=<?php echo $invoice['id']; ?>" class="download-btn">Download PDF</a>
                        </div>
                    </div>
                    
                    <!-- Invoice View Modal -->
                    <div id="invoice-modal-<?php echo $invoice['id']; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" onclick="closeModal('invoice-modal-<?php echo $invoice['id']; ?>')">&times;</span>
                            <div class="invoice-preview">
                                <div class="invoice-header-preview">
                                    <div class="invoice-title-preview">INVOICE</div>
                                    <div>
                                        <div><strong>Invoice ID:</strong> <?php echo $invoice['id']; ?></div>
                                        <div><strong>Date:</strong> <?php echo $invoice['date_created']; ?></div>
                                        <?php if ($invoice['quotation_id']): ?>
                                            <div><strong>Quotation Reference:</strong> #<?php echo $invoice['quotation_id']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="from-to">
                                    <div class="from">
                                        <h4>From:</h4>
                                        <div><?php echo htmlspecialchars($invoice['business_name']); ?></div>
                                        <div><?php echo htmlspecialchars($invoice['business_address'] ?? '-'); ?></div>
                                        <div><?php echo htmlspecialchars($invoice['business_contact'] ?? '-'); ?></div>
                                    </div>
                                    
                                    <div class="to">
                                        <h4>To:</h4>
                                        <div><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                                        <div><?php echo htmlspecialchars($invoice['client_phone'] ?? '-'); ?></div>
                                    </div>
                                </div>
                                
                                <table class="invoice-table">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Item</th>
                                            <th>Description</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice['items'] as $index => $item): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <td><?php echo $item['quantity'] ?? 1; ?></td>
                                                <td class="text-right"><?php echo number_format($item['price'], 2); ?></td>
                                                <td class="text-right">
                                                    <?php echo number_format($item['price'] * ($item['quantity'] ?? 1), 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="total-row">
                                            <td colspan="5">Total</td>
                                            <td class="text-right"><?php echo number_format($invoice['amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                
                                <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;">
                                    <p><strong>Status:</strong> <?php echo ucfirst($invoice['status']); ?></p>
                                    <?php if ($invoice['status'] == 'pending'): ?>
                                        <p>Please make payment by [due date] to the account below:</p>
                                        <p>Bank: [Your Bank Name]<br>
                                        Account Name: [Your Business Name]<br>
                                        Account Number: [Your Account Number]</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // View invoice modal
        function viewInvoice(id) {
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