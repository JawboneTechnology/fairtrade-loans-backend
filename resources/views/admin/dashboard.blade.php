
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loan App Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .dashboard {
            display: flex;
            min-height: 100vh;
        }
        .loan-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .loan-item {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border-left: 5px solid #d4ff47;
        }
        .sidebar {
            background-color: #0fc0fc;
            width: 250px;
            padding: 20px;
            color: #fff;
        }
        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar .logo img {
            max-width: 100%;
            height: auto;
        }
        .sidebar nav a {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .sidebar nav a:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        .header {
            background-color: #d4ff47;
            padding: 15px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .content {
            background-color: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .content h2 {
            margin-top: 0;
        }
    </style>
</head>
<body>
<div class="dashboard">
    <div class="sidebar">
        <!-- Company Logo -->
        <div class="logo">
            <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo">
        </div>
        <!-- Sidebar Navigation -->
        <nav>
            <a href="#">Dashboard</a>
            <a href="#">Loans</a>
            <a href="#">Guarantors</a>
            <a href="#">Settings</a>
            <a href="#">Reports</a>
        </nav>
    </div>
    <div class="main-content">
        <div class="header">Dashboard</div>
        <div class="content">
            <h2>Welcome, [User Name]</h2>
            <p>
                This is your loan management dashboard. Here you can view your current loan applications,
                manage guarantors, and check the status of disbursements.
            </p>
            <!-- Add further dashboard elements as needed -->
            <h2>Recent Loan Applications</h2>
            <div class="loan-list">
                <div class="loan-item">
                    <p>Loan #12345 - $5,000</p>
                    <p class="status">Approved</p>
                </div>
                <div class="loan-item">
                    <p>Loan #12346 - $3,000</p>
                    <p class="status">Pending</p>
                </div>
                <div class="loan-item">
                    <p>Loan #12347 - $7,000</p>
                    <p class="status">Declined</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
