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

// Get all users who have created quotations
$users = [];
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN quotations q ON u.id = q.user_id
    ORDER BY u.name
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get quotation count for each user
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM quotations WHERE user_id = ?");
    $count_stmt->bind_param("i", $row['id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $row['quotation_count'] = $count_result->fetch_assoc()['count'];
    $count_stmt->close();
    
    $users[] = $row;
}
$stmt->close();

// Get quotations for a specific user if selected
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$quotations = [];
$selected_user = null;

if ($selected_user_id) {
    // Get user details
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $selected_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get quotations for this user
    $stmt = $conn->prepare("
        SELECT q.*, b.name as business_name, c.name as client_name
        FROM quotations q
        JOIN business b ON q.business_id = b.id
        JOIN clients c ON q.client_id = c.id
        WHERE q.user_id = ?
        ORDER BY q.date_created DESC
    ");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $quotations[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View All Quotations</title>
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
        .quotation-count { color: #2196F3; font-weight: bold; }
        
        .quotation-list { margin-top: 30px; }
        .quotation-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .quotation-table th, .quotation-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .quotation-table th { background-color: #f2f2f2; }
        .quotation-table tr:nth-child(even) { background-color: #f9f9f9; }
        
        .back-link { display: inline-block; margin-bottom: 15px; color: #2196F3; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        .section-title { margin-top: 30px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
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
        <h2>View All Quotations</h2>
        
        <?php if ($selected_user_id): ?>
            <a href="view_quotations.php" class="back-link">&larr; Back to all users</a>
            <h3 class="section-title">Quotations by <?php echo htmlspecialchars($selected_user['name']); ?></h3>
            
            <?php if (empty($quotations)): ?>
                <p>No quotations found for this user.</p>
            <?php else: ?>
                <table class="quotation-table">
                    <thead>
                        <tr>
                            <th>Quotation ID</th>
                            <th>Date Created</th>
                            <th>Business</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotations as $quotation): ?>
                            <tr>
                                <td><?php echo $quotation['id']; ?></td>
                                <td><?php echo $quotation['date_created']; ?></td>
                                <td><?php echo htmlspecialchars($quotation['business_name']); ?></td>
                                <td><?php echo htmlspecialchars($quotation['client_name']); ?></td>
                                <td><?php echo number_format($quotation['amount'], 2); ?></td>
                                <td><?php echo ucfirst($quotation['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="user-list">
                <h3 class="section-title">Users with Quotations</h3>
                
                <?php if (empty($users)): ?>
                    <p>No users with quotations found.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <div class="user-card" onclick="window.location.href='view_quotations.php?user_id=<?php echo $user['id']; ?>'">
                            <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="quotation-count"><?php echo $user['quotation_count']; ?> quotation(s)</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>