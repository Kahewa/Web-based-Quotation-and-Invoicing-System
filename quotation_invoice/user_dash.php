<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: user_log.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo $_SESSION['name']; ?>!</h1>
    <p>Email: <?php echo $_SESSION['email']; ?></p>
    <a href="logout.php"><button>Logout</button></a>
</body>
</html>