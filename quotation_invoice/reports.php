<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Include DB connection
include $_SERVER['DOCUMENT_ROOT'] . '/quotation_invoice/server/conn.php';

// Fetch users and stats
$usersData = [];
$result = $conn->query("
    SELECT 
        u.id, 
        u.name,
        COUNT(DISTINCT b.id) AS business_count,
        COUNT(DISTINCT c.id) AS client_count,
        COUNT(DISTINCT q.id) AS quotation_count,
        COUNT(DISTINCT i.id) AS invoice_count
    FROM users u
    LEFT JOIN business b ON u.id = b.user_id
    LEFT JOIN clients c ON b.id = c.business_id
    LEFT JOIN quotations q ON u.id = q.user_id
    LEFT JOIN invoices i ON u.id = i.user_id
    GROUP BY u.id
");

if ($result) {
    $usersData = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f7f7f7; }
        .header { background-color: #333; color: white; padding: 15px; display: flex; justify-content: space-between; }
        .sidebar { width: 200px; background-color: #f4f4f4; height: calc(100vh - 50px); float: left; }
        .content { margin-left: 200px; padding: 20px; }
        .sidebar a { display: block; padding: 10px; text-decoration: none; color: #333; }
        .sidebar a:hover { background-color: #ddd; }
        .logout-btn { background: none; border: none; color: white; cursor: pointer; }

        .chart-container { 
            width: 80%; 
            margin: 30px auto; 
            background: white; 
            padding: 20px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            border-radius: 5px;
            display: none;
        }

        .user-select {
            width: 60%;
            margin: 20px auto;
        }

        .user-select label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select {
            width: 100%;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard</h2>
        <form action="logout.php" method="post">
            <span>Welcome, <?php echo $_SESSION['name']; ?>!</span>
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
        <h3>User Activity Reports</h3>

        <!-- User Select -->
        <div class="user-select">
            <label for="userDropdown">Select a User:</label>
            <select id="userDropdown">
                <option value="" disabled selected>Choose a user...</option>
                <?php foreach ($usersData as $user): ?>
                    <option value='<?php echo json_encode($user); ?>'>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Chart -->
        <div class="chart-container" id="chartContainer">
            <canvas id="userStatsChart"></canvas>
        </div>
    </div>

    <script>
        let userChart = null;

        $(document).ready(function () {
            $('#userDropdown').select2({
                placeholder: "Search or select a user",
                allowClear: true
            });

            $('#userDropdown').on('change', function () {
                const selectedUser = JSON.parse($(this).val());
                showUserChart(selectedUser);
            });
        });

        function showUserChart(user) {
            const container = document.getElementById("chartContainer");
            container.style.display = 'block';

            const ctx = document.getElementById('userStatsChart').getContext('2d');
            const data = {
                labels: ['Businesses', 'Clients', 'Quotations', 'Invoices'],
                datasets: [{
                    label: `${user.name}'s Statistics`,
                    data: [user.business_count, user.client_count, user.quotation_count, user.invoice_count],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            const config = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: `Activity Breakdown for ${user.name}`
                        },
                        legend: {
                            display: false
                        }
                    }
                }
            };

            if (userChart) {
                userChart.destroy();
            }

            userChart = new Chart(ctx, config);
        }
    </script>
</body>
</html>
