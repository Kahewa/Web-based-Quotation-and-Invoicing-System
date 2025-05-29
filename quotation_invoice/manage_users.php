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
    if (isset($_POST['add_user'])) {
        // Add new user
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role = $_POST['role'];
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);
        
        if ($stmt->execute()) {
            $message = "User added successfully";
            $message_type = "success";
        } else {
            $message = "Error adding user: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
        
    } elseif (isset($_POST['update_user'])) {
        // Update existing user
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        
        // Check if password was provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $password, $role, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $role, $id);
        }
        
        if ($stmt->execute()) {
            $message = "User updated successfully";
            $message_type = "success";
        } else {
            $message = "Error updating user: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
} elseif (isset($_GET['delete'])) {
    // Delete user
    $id = $_GET['delete'];
    
    // First check if user is not deleting themselves
    if ($id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "User deleted successfully";
            $message_type = "success";
        } else {
            $message = "Error deleting user: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all users with their business and client counts
$users = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           COUNT(DISTINCT b.id) as business_count,
           COUNT(DISTINCT c.id) as client_count
    FROM users u
    LEFT JOIN business b ON u.id = b.user_id
    LEFT JOIN clients c ON b.id = c.business_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #333; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .user-list { margin-top: 20px; }
        .user-table { width: 100%; border-collapse: collapse; }
        .user-table th, .user-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .user-table th { background-color: #f2f2f2; }
        .user-table tr:nth-child(even) { background-color: #f9f9f9; }
        .user-actions a { 
            padding: 3px 6px; margin-right: 3px; text-decoration: none; border-radius: 3px; 
            display: inline-block; font-size: 0.9em;
        }
        .edit-btn { background-color: #2196F3; color: white; }
        .delete-btn { background-color: #f44336; color: white; }
        
        .user-form { max-width: 500px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .user-form input, .user-form select { 
            width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; 
        }
        .user-form button { 
            padding: 10px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; 
        }
        .user-form button:hover { background-color: #45a049; }
        
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; 
                overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; 
                        width: 80%; max-width: 500px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
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
        <h2>Manage Users</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <button onclick="showAddUserModal()" style="margin-bottom: 20px;">+ Add New User</button>
        
        <div class="user-list">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Businesses</th>
                        <th>Clients</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst($user['role']); ?></td>
                            <td><?php echo $user['business_count']; ?></td>
                            <td><?php echo $user['client_count']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td class="user-actions">
                                <a href="#" onclick="showEditUserModal(
                                    <?php echo $user['id']; ?>,
                                    '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>',
                                    '<?php echo $user['role']; ?>'
                                )" class="edit-btn">Edit</a>
                                <a href="manage_users.php?delete=<?php echo $user['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this user?')"
                                   class="delete-btn">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div id="add-user-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('add-user-modal')">&times;</span>
            <h3>Add New User</h3>
            <form class="user-form" method="POST">
                <label for="add-name">Name:</label>
                <input type="text" id="add-name" name="name" required>
                
                <label for="add-email">Email:</label>
                <input type="email" id="add-email" name="email" required>
                
                <label for="add-password">Password:</label>
                <input type="password" id="add-password" name="password" required>
                
                <label for="add-role">Role:</label>
                <select id="add-role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                
                <button type="submit" name="add_user">Add User</button>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="edit-user-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('edit-user-modal')">&times;</span>
            <h3>Edit User</h3>
            <form class="user-form" method="POST">
                <input type="hidden" id="edit-id" name="id">
                
                <label for="edit-name">Name:</label>
                <input type="text" id="edit-name" name="name" required>
                
                <label for="edit-email">Email:</label>
                <input type="email" id="edit-email" name="email" required>
                
                <label for="edit-password">New Password (leave blank to keep current):</label>
                <input type="password" id="edit-password" name="password">
                
                <label for="edit-role">Role:</label>
                <select id="edit-role" name="role" required>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                
                <button type="submit" name="update_user">Update User</button>
            </form>
        </div>
    </div>
    
    <script>
        // Show add user modal
        function showAddUserModal() {
            document.getElementById('add-user-modal').style.display = 'block';
        }
        
        // Show edit user modal with data
        function showEditUserModal(id, name, email, role) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            document.getElementById('edit-user-modal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                const modals = document.getElementsByClassName('modal');
                for (let i = 0; i < modals.length; i++) {
                    modals[i].style.display = 'none';
                }
            }
        };
    </script>
</body>
</html>