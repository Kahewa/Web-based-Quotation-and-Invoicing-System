<?php
include "server/conn.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Hash the password

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit();
    }
    
    $check_stmt->close();

    // Insert into users table
    $stmt = $conn->prepare("INSERT INTO users (name, role, email, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $role, $email, $password);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User registration successful! <a href="login.php">Login here</a>.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit(); // Important to prevent HTML rendering after AJAX call
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Registration</title>
    <!-- jQuery, Popper.js, Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; }
        form { display: flex; flex-direction: column; gap: 15px; margin-top: 20px; }
        input, select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
        #message { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
    </style>
</head>
<body>
    <h1>User Registration</h1>
    <button id="hide">Hide Form</button>
    <button id="show">Show Form</button>
    
    <form id="reg_form">
        <div>
            <label for="name"><b>Name</b></label>
            <input id="name" type="text" placeholder="Enter Name" name="name" required>
        </div>
        <div>
            <label for="role"><b>Role</b></label>
            <select id="role" name="role" required>
                <option value="">Select Role</option>
                <option value="admin">ADMIN</option>
                <option value="user">USER</option>
            </select>
        </div>
        <div>
            <label for="email"><b>Email</b></label>
            <input id="email" type="email" placeholder="Enter Email" name="email" required>
        </div>
        <div>
            <label for="password"><b>Password</b></label>
            <input id="password" type="password" placeholder="Enter Password" name="password" required minlength="6">
        </div>
        <button type="submit">Register</button>
    </form>
    
    <div id="message"></div>
    
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</body>
</html>

<script>
    $(document).ready(function() {
        // Hide/show form buttons
        $("#hide").click(function() {
            $("#reg_form").hide();
        });

        $("#show").click(function() {
            $("#reg_form").show();
        });

        // Form submission
        $("#reg_form").submit(function(e) {
            e.preventDefault();
            
            // Basic client-side validation
            if ($("#name").val().trim() === "") {
                showMessage("Please enter your name", "error");
                return;
            }
            
            if ($("#role").val() === "") {
                showMessage("Please select a role", "error");
                return;
            }
            
            if ($("#email").val().trim() === "") {
                showMessage("Please enter your email", "error");
                return;
            }
            
            if ($("#password").val().length < 6) {
                showMessage("Password must be at least 6 characters", "error");
                return;
            }

            $.ajax({
                url: "user_reg.php", 
                type: "POST",
                data: $(this).serialize(),
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        showMessage(response.message, "success");
                        $("#reg_form")[0].reset();
                    } else {
                        showMessage(response.message, "error");
                    }
                },
                error: function(xhr, status, error) {
                    showMessage("An error occurred: " + error, "error");
                }
            });
        });
        
        function showMessage(message, type) {
            const $message = $("#message");
            $message.removeClass("success error").addClass(type);
            $message.text(type === "success" ? message : message.replace(/^Error: /, ''));
            $message.show();
            
            if (type === "success") {
                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
        }
    });
</script>