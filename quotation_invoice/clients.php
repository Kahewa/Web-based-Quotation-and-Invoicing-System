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
    
    // Verify the client belongs to the user before deleting
    $stmt = $conn->prepare("DELETE clients FROM clients 
                          JOIN business ON clients.business_id = business.id
                          WHERE clients.id = ? AND business.user_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Client deleted successfully";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting client: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    
    $stmt->close();
    header("Location: clients.php");
    exit();
}

// Handle form submissions for add/update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_client'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $business_id = $_POST['business_id'];
        
        // Verify the business belongs to the user
        $check_stmt = $conn->prepare("SELECT id FROM business WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $business_id, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO clients (business_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $business_id, $name, $email, $phone, $address);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Client added successfully";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding client: " . $stmt->error;
                $_SESSION['message_type'] = "error";
            }
            
            $stmt->close();
        } else {
            $_SESSION['message'] = "Invalid business selected";
            $_SESSION['message_type'] = "error";
        }
        
        $check_stmt->close();
        header("Location: clients.php");
        exit();
        
    } elseif (isset($_POST['update_client'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $business_id = $_POST['business_id'];
        
        // Verify the client belongs to the user before updating
        $stmt = $conn->prepare("UPDATE clients 
                              JOIN business ON clients.business_id = business.id
                              SET clients.business_id = ?, clients.name = ?, clients.email = ?, 
                                  clients.phone = ?, clients.address = ?
                              WHERE clients.id = ? AND business.user_id = ?");
        $stmt->bind_param("issssii", $business_id, $name, $email, $phone, $address, $id, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Client updated successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating client: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        
        $stmt->close();
        header("Location: clients.php");
        exit();
    }
}

// Get user's businesses for dropdown
$businesses = [];
$stmt = $conn->prepare("SELECT id, name FROM business WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $businesses[] = $row;
}
$stmt->close();

// Get user's clients
$clients = [];
$stmt = $conn->prepare("SELECT clients.*, business.name as business_name 
                       FROM clients 
                       JOIN business ON clients.business_id = business.id
                       WHERE business.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Clients</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        
        .client-form { max-width: 500px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .client-form input, .client-form textarea, .client-form select { 
            width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 4px; 
        }
        .client-form button { 
            background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; 
        }
        .client-form button:hover { background-color: #45a049; }
        
        .client-list { margin-top: 30px; }
        .client-card { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .client-card h3 { margin-top: 0; }
        .client-actions { margin-top: 10px; }
        .client-actions a { 
            padding: 5px 10px; margin-right: 5px; text-decoration: none; border-radius: 3px; 
            display: inline-block; /* Added for better button alignment */
        }
        .edit-btn { background-color: #2196F3; color: white; }
        .delete-btn { background-color: #f44336; color: white; }
        
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
        <h2>My Clients</h2>
        
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
        
        <!-- Add/Edit Client Form -->
        <div class="client-form">
            <h3><?php echo isset($_GET['edit']) ? 'Edit Client' : 'Add New Client'; ?></h3>
            <form method="POST">
                <?php if (isset($_GET['edit'])): 
                    $edit_id = $_GET['edit'];
                    $edit_client = null;
                    foreach ($clients as $client) {
                        if ($client['id'] == $edit_id) {
                            $edit_client = $client;
                            break;
                        }
                    }
                    if ($edit_client): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_client['id']; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <label for="business_id">Business:</label>
                <select id="business_id" name="business_id" required>
                    <option value="">Select Business</option>
                    <?php foreach ($businesses as $business): ?>
                        <option value="<?php echo $business['id']; ?>"
                            <?php if (isset($edit_client) && $edit_client['business_id'] == $business['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($business['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="name">Client Name:</label>
                <input type="text" id="name" name="name" required 
                    value="<?php echo isset($edit_client) ? htmlspecialchars($edit_client['name']) : ''; ?>">
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" 
                    value="<?php echo isset($edit_client) ? htmlspecialchars($edit_client['email']) : ''; ?>">
                
                <label for="phone">Phone:</label>
                <input type="text" id="phone" name="phone" 
                    value="<?php echo isset($edit_client) ? htmlspecialchars($edit_client['phone']) : ''; ?>">
                
                <label for="address">Address:</label>
                <textarea id="address" name="address" rows="3"><?php 
                    echo isset($edit_client) ? htmlspecialchars($edit_client['address']) : ''; 
                ?></textarea>
                
                <button type="submit" name="<?php echo isset($_GET['edit']) ? 'update_client' : 'add_client'; ?>">
                    <?php echo isset($_GET['edit']) ? 'Update Client' : 'Add Client'; ?>
                </button>
                
                <?php if (isset($_GET['edit'])): ?>
                    <a href="clients.php" style="margin-left: 10px;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Client List -->
        <div class="client-list">
            <h3>Your Clients</h3>
            
            <?php if (empty($clients)): ?>
                <p>You haven't added any clients yet.</p>
            <?php else: ?>
                <?php foreach ($clients as $client): ?>
                    <div class="client-card">
                        <h3><?php echo htmlspecialchars($client['name']); ?></h3>
                        <p><strong>For:</strong> <?php echo htmlspecialchars($client['business_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                        <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($client['address'])); ?></p>
                        
                        <div class="client-actions">
                            <a href="clients.php?edit=<?php echo $client['id']; ?>" class="edit-btn">Edit</a>
                            <a href="#" onclick="confirmDelete(<?php echo $client['id']; ?>)" class="delete-btn">Delete</a>
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
            <p>Are you sure you want to delete this client?</p>
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
            deleteLink.href = 'clients.php?delete=' + id;
            
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