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

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_business'])) {
        // Update business
        $id = $_POST['id'];
        $name = $_POST['name'];
        $address = $_POST['address'];
        $contact = $_POST['contact'];
        $user_id = $_POST['user_id'];
        
        $stmt = $conn->prepare("UPDATE business SET name = ?, address = ?, contact = ?, user_id = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $address, $contact, $user_id, $id);
        
        if ($stmt->execute()) {
            $message = "Business updated successfully";
            $message_type = "success";
        } else {
            $message = "Error updating business: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
} elseif (isset($_GET['delete'])) {
    // Delete business and all related data
    $id = $_GET['delete'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Delete invoices related to this business's quotations
        $stmt = $conn->prepare("DELETE i FROM invoices i
                              JOIN quotations q ON i.quotation_id = q.id
                              WHERE q.business_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // 2. Delete quotations for this business
        $stmt = $conn->prepare("DELETE FROM quotations WHERE business_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // 3. Delete clients for this business
        $stmt = $conn->prepare("DELETE FROM clients WHERE business_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // 4. Finally delete the business
        $stmt = $conn->prepare("DELETE FROM business WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $message = "Business and all related data deleted successfully";
        $message_type = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting business: " . $e->getMessage();
        $message_type = "error";
    }
}

// Get all businesses with their clients
$businesses = [];
$stmt = $conn->prepare("
    SELECT b.*, u.name as user_name, 
           GROUP_CONCAT(c.name SEPARATOR ', ') as client_names,
           COUNT(c.id) as client_count
    FROM business b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN clients c ON b.id = c.business_id
    GROUP BY b.id
    ORDER BY b.name
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}

// Get all users for dropdown
$users = [];
$stmt = $conn->prepare("SELECT id, name FROM users ORDER BY name");
$stmt->execute();
$user_result = $stmt->get_result();

while ($user = $user_result->fetch_assoc()) {
    $users[] = $user;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Businesses</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #333; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .business-list { margin-top: 20px; }
        .business-table { width: 100%; border-collapse: collapse; }
        .business-table th, .business-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .business-table th { background-color: #f2f2f2; }
        .business-table tr:nth-child(even) { background-color: #f9f9f9; }
        .business-actions a { 
            padding: 3px 6px; margin-right: 3px; text-decoration: none; border-radius: 3px; 
            display: inline-block; font-size: 0.9em;
        }
        .edit-btn { background-color: #2196F3; color: white; }
        .delete-btn { background-color: #f44336; color: white; }
        
        .business-form { max-width: 500px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .business-form input, .business-form select, .business-form textarea { 
            width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; 
        }
        .business-form button { 
            padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; 
        }
        .business-form button:hover { background-color: #45a049; }
        
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; 
                overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; 
                        width: 80%; max-width: 500px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        
        .client-list { margin-top: 5px; font-size: 0.9em; color: #555; }
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
        <h2>Manage Businesses</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="business-list">
            <table class="business-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Contact</th>
                        <th>Owner</th>
                        <th>Clients</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businesses as $business): ?>
                        <tr>
                            <td><?php echo $business['id']; ?></td>
                            <td><?php echo htmlspecialchars($business['name']); ?></td>
                            <td><?php echo htmlspecialchars($business['address']); ?></td>
                            <td><?php echo htmlspecialchars($business['contact']); ?></td>
                            <td><?php echo htmlspecialchars($business['user_name'] ?? 'None'); ?></td>
                            <td>
                                <?php echo $business['client_count']; ?>
                                <?php if ($business['client_names']): ?>
                                    <div class="client-list">
                                        <?php echo htmlspecialchars($business['client_names']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="business-actions">
                                <a href="#" onclick="showEditBusinessModal(
                                    <?php echo $business['id']; ?>,
                                    '<?php echo htmlspecialchars($business['name'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($business['address'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($business['contact'], ENT_QUOTES); ?>',
                                    <?php echo $business['user_id'] ?? 'null'; ?>
                                )" class="edit-btn">Edit</a>
                                <a href="manage_businesses.php?delete=<?php echo $business['id']; ?>" 
                                   onclick="return confirm('WARNING: This will delete the business, all its clients, quotations, and related invoices. Are you sure?')"
                                   class="delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Edit Business Modal -->
    <div id="edit-business-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('edit-business-modal')">&times;</span>
            <h3>Edit Business</h3>
            <form class="business-form" method="POST">
                <input type="hidden" id="edit-id" name="id">
                
                <label for="edit-name">Business Name:</label>
                <input type="text" id="edit-name" name="name" required>
                
                <label for="edit-address">Address:</label>
                <textarea id="edit-address" name="address" rows="3"></textarea>
                
                <label for="edit-contact">Contact Information:</label>
                <input type="text" id="edit-contact" name="contact">
                
                <label for="edit-user-id">Owner (User):</label>
                <select id="edit-user-id" name="user_id">
                    <option value="">-- No Owner --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" name="update_business">Update Business</button>
            </form>
        </div>
    </div>
    
    <script>
        // Show edit business modal with data
        function showEditBusinessModal(id, name, address, contact, userId) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-address').value = address;
            document.getElementById('edit-contact').value = contact;
            
            const userSelect = document.getElementById('edit-user-id');
            if (userId) {
                userSelect.value = userId;
            } else {
                userSelect.value = '';
            }
            
            document.getElementById('edit-business-modal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                document.getElementById('edit-business-modal').style.display = 'none';
            }
        };
    </script>
</body>
</html>