<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/server/conn.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get all users who have invoices
$users = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN invoices i ON u.id = i.user_id
    ORDER BY u.name
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get invoice count and total amount for each user
    $sum_stmt = $conn->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total 
        FROM invoices 
        WHERE user_id = ?
    ");
    $sum_stmt->bind_param("i", $row['id']);
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result()->fetch_assoc();
    $row['invoice_count'] = $sum_result['count'];
    $row['total_amount'] = $sum_result['total'];
    $sum_stmt->close();
    
    $users[] = $row;
}
$stmt->close();

// Get invoices for a specific user if selected
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$invoices = [];
$selected_user = null;
$user_total = 0;

if ($selected_user_id) {
    // Get user details
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $selected_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get invoices for this user
    $stmt = $conn->prepare("
        SELECT i.*, b.name as business_name, c.name as client_name,
               q.id as quotation_id, q.date_created as quotation_date
        FROM invoices i
        LEFT JOIN business b ON i.business_id = b.id
        LEFT JOIN clients c ON i.client_id = c.id
        LEFT JOIN quotations q ON i.quotation_id = q.id
        WHERE i.user_id = ?
        ORDER BY i.date_created DESC
    ");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
        $user_total += $row['amount'];
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View All Invoices</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #333; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .user-list { margin-top: 20px; }
        .user-card { 
            border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; 
            cursor: pointer; transition: background-color 0.3s;
        }
        .user-card:hover { background-color: #f9f9f9; }
        .user-card.active { background-color: #e6f7ff; border-left: 4px solid #2196F3; }
        .user-name { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .user-email { color: #666; margin-bottom: 5px; }
        .invoice-count { color: #4CAF50; font-weight: bold; }
        .invoice-total { color: #9C27B0; font-weight: bold; }
        
        .invoice-list { margin-top: 30px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .invoice-table th { background-color: #f2f2f2; }
        .invoice-table tr:nth-child(even) { background-color: #f9f9f9; }
        
        .back-link { display: inline-block; margin-bottom: 15px; color: #2196F3; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        .section-title { margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        
        .total-amount { 
            margin-top: 20px; padding: 10px; 
            background-color: #f8f9fa; border-radius: 4px;
            font-size: 1.2em; font-weight: bold;
        }
        .total-amount span { color: #9C27B0; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard</h2>
        <form action="../logout.php" method="post">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>
    
    <div class="sidebar">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="manage_users.php">Manage Users</a>
        <a href="manage_businesses.php">Manage Businesses</a>
        <a href="view_quotations.php">View All Quotations</a>
        <a href="view_invoices.php">View All Invoices</a>
        <a href="reports.php">Reports</a>
    </div>
    
    <div class="content">
        <h2>View All Invoices</h2>
        
        <?php if ($selected_user_id): ?>
            <a href="view_invoices.php" class="back-link">&larr; Back to all users</a>
            <h3 class="section-title">Invoices by <?php echo htmlspecialchars($selected_user['name']); ?></h3>
            
            <?php if (empty($invoices)): ?>
                <p>No invoices found for this user.</p>
            <?php else: ?>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Date Created</th>
                            <th>Business</th>
                            <th>Client</th>
                            <th>Quotation Ref</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?php echo $invoice['id']; ?></td>
                                <td><?php echo $invoice['date_created']; ?></td>
                                <td><?php echo htmlspecialchars($invoice['business_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($invoice['client_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($invoice['quotation_id']): ?>
                                        <a href="view_quotations.php?user_id=<?php echo $selected_user_id; ?>#quotation-<?php echo $invoice['quotation_id']; ?>">
                                            #<?php echo $invoice['quotation_id']; ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo ucfirst($invoice['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="total-amount">
                    Total Amount for <?php echo htmlspecialchars($selected_user['name']); ?>: 
                    <span><?php echo number_format($user_total, 2); ?></span>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="user-list">
                <h3 class="section-title">Users with Invoices</h3>
                
                <?php if (empty($users)): ?>
                    <p>No users with invoices found.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-card" onclick="window.location.href='view_invoices.php?user_id=<?php echo $user['id']; ?>'">
                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="invoice-count"><?php echo $user['invoice_count']; ?> invoice(s)</div>
                            <div class="invoice-total">Total: <?php echo number_format($user['total_amount'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>