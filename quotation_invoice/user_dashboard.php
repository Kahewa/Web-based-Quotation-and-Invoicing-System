<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        .header { background-color: #4CAF50; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        .logout-btn { background: none; border: none; color: white; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h2>User Dashboard</h2>
        <form action="logout.php" method="post">
            <span>Welcome, <?php echo $_SESSION['name']; ?>!</span>
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
        <h3>Welcome to Your Dashboard</h3>
        <p>This is your personal dashboard where you can manage your business activities.</p>
        <!-- Add user-specific content here -->
    </div>
</body>
</html>