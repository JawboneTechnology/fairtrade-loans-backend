<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            width: 100%;
            padding: 20px;
            background-color: #ffffff;
            max-width: 600px;
            margin: 20px auto;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background-color: #4caf50;
            color: white;
            text-align: center;
            padding: 20px 0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .email-header h2 {
            margin: 0;
        }
        .email-content {
            padding: 20px;
            text-align: left;
            line-height: 1.6;
            color: #333;
        }
        .email-content p {
            margin: 10px 0;
        }
        .applicant-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .applicant-details th, .applicant-details td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
        }
        .applicant-details th {
            background-color: #e7ffe8;
            font-weight: bold;
        }
        .btn {
            background-color: #4caf50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .email-footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
            text-align: center;
            border-top: 1px solid #f4f4f4;
            padding-top: 10px;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="logo-container">
        <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>New User Registration</h2>
    </div>
    <div class="email-content">
        <p>Hello {{ $adminName  }},</p>
        <p>A new user has just registered on the platform. Here are the details:</p>

        <table class="applicant-details">
            <tr>
                <th>Full Name</th>
                <td>{{ $user->first_name . ' ' . $user->last_name }}</td>
            </tr>
            <tr>
                <th>Email</th>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <th>Phone Number</th>
                <td>{{ $user->phone_number }}</td>
            </tr>
            <tr>
                <th>Registration Date</th>
                <td>{{ \Carbon\Carbon::parse($user->created_at)->format('F j, Y, g:i a') }}</td>
            </tr>
        </table>

        <p>You can view this user’s account details by logging into the admin dashboard:</p>
        <a href="{{ $adminDashboardUrl }}" class="btn">Go to Dashboard</a>

        <p>If you need to take immediate action or contact the user, you can use the details provided above.</p>

        <p>Best regards,<br>The System Notification Team</p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
    </div>
</div>
</body>
</html>
