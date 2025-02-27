<?php
include "server/conn.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch admin from the database
    $stmt = $conn->prepare("SELECT admin_id, name, email, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($admin_id, $name, $db_email, $password); //password not hashed for login purposes since admin is hardcoded

    if ($stmt->fetch() && ($password)) {
        // Start a session and store admin data
        session_start();
        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $db_email;
        header("Location: admin_dash.php"); // Redirect to admin dashboard
        exit();
    } else {
        echo "Invalid email or password.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
</head>
<body>
    <h1>Admin Login</h1>
    <form action="admin_log.php" method="post">
        <div>
            <label for="email"><b>Email</b></label>
            <input type="email" placeholder="Enter Email" name="email" required>
        </div>
        <div>
            <label for="password"><b>Password</b></label>
            <input type="password" placeholder="Enter Password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</body>
</html>