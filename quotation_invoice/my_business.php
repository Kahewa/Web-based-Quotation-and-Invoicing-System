<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use absolute path for connection file
include $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/server/conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle delete action
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Verify the business belongs to the user before deleting
    $stmt = $conn->prepare("DELETE FROM business WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Business deleted successfully";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting business: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    
    $stmt->close();
    header("Location: my_business.php");
    exit();
}

// Handle form submissions for add/update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_business'])) {
        $name = $_POST['name'];
        $address = $_POST['address'];
        $contact = $_POST['contact'];
        
        $stmt = $conn->prepare("INSERT INTO business (name, address, contact, user_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $address, $contact, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Business added successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding business: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: my_business.php");
        exit();
        
    } elseif (isset($_POST['update_business'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $address = $_POST['address'];
        $contact = $_POST['contact'];
        
        $stmt = $conn->prepare("UPDATE business SET name = ?, address = ?, contact = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssii", $name, $address, $contact, $id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Business updated successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating business: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: my_business.php");
        exit();
    }
}

// Get user's businesses
$businesses = [];
$stmt = $conn->prepare("SELECT * FROM business WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Business</title>
    <style>
        /* Your existing styles remain the same */
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .business-form { max-width: 500px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .business-form input, .business-form textarea { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; }
        .business-form button { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .business-form button:hover { background-color: #45a049; }
        
        .business-list { margin-top: 30px; }
        .business-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .business-card h3 { margin-top: 0; }
        .business-actions { margin-top: 10px; }
        .business-actions a, .business-actions button { padding: 5px 10px; margin-right: 5px; text-decoration: none; border-radius: 3px; }
        .edit-btn { background-color: #2196F3; color: white; border: none; cursor: pointer; }
        .delete-btn { background-color: #f44336; color: white; border: none; cursor: pointer; }
        
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
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
        <h2>My Business</h2>
        
        <!-- Display messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Business Form -->
        <div class="business-form">
            <h3><?php echo isset($_GET['edit']) ? 'Edit Business' : 'Add New Business'; ?></h3>
            <form method="POST">
                <?php if (isset($_GET['edit'])): 
                    $edit_id = $_GET['edit'];
                    $edit_business = null;
                    foreach ($businesses as $business) {
                        if ($business['id'] == $edit_id) {
                            $edit_business = $business;
                            break;
                        }
                    }
                    if ($edit_business): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_business['id']; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <label for="name">Business Name:</label>
                <input type="text" id="name" name="name" required 
                    value="<?php echo isset($edit_business) ? htmlspecialchars($edit_business['name']) : ''; ?>">
                
                <label for="address">Business Address:</label>
                <textarea id="address" name="address" rows="3"><?php 
                    echo isset($edit_business) ? htmlspecialchars($edit_business['address']) : ''; 
                ?></textarea>
                
                <label for="contact">Contact Information:</label>
                <input type="text" id="contact" name="contact" 
                    value="<?php echo isset($edit_business) ? htmlspecialchars($edit_business['contact']) : ''; ?>">
                
                <button type="submit" name="<?php echo isset($_GET['edit']) ? 'update_business' : 'add_business'; ?>">
                    <?php echo isset($_GET['edit']) ? 'Update Business' : 'Add Business'; ?>
                </button>
                
                <?php if (isset($_GET['edit'])): ?>
                    <a href="my_business.php" class="cancel-btn">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Business List -->
        <div class="business-list">
            <h3>Your Businesses</h3>
            
            <?php if (empty($businesses)): ?>
                <p>You haven't added any businesses yet.</p>
            <?php else: ?>
                <?php foreach ($businesses as $business): ?>
                    <div class="business-card">
                        <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                        <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($business['address'])); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($business['contact']); ?></p>
                        
                        <div class="business-actions">
                            <a href="my_business.php?edit=<?php echo $business['id']; ?>" class="edit-btn">Edit</a>
                            <a href="#" onclick="confirmDelete(<?php echo $business['id']; ?>)" class="delete-btn">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this business?</p>
            <div style="margin-top: 20px;">
                <button onclick="closeModal()" style="background-color: #ccc; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <a id="confirmDeleteLink" href="#" style="background-color: #f44336; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block;">Delete</a>
            </div>
        </div>
    </div>
    
    <script>
        // Delete confirmation
        function confirmDelete(id) {
            const modal = document.getElementById('deleteModal');
            const deleteLink = document.getElementById('confirmDeleteLink');
            
            // Set the delete link
            deleteLink.href = 'my_business.php?delete=' + id;
            
            // Show the modal
            modal.style.display = 'block';
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            };
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>
</body>
</html>