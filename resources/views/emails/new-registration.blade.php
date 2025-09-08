<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Fairtrade Salary Advance Loans</title>
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
            background-color: #00bcd4;
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
        .btn {
            background-color: #00bcd4;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background-color: #019dad;
        }
        .login-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .login-details th, .login-details td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
        }
        .login-details th {
            background-color: #e7fcff;
            font-weight: bold;
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
        <h2>Welcome to Fairtrade Loans</h2>
    </div>
    <div class="email-content">
        <p>Hello, <strong>{{ $user->first_name . ' ' . $user->last_name }}</strong>,</p>
        <p>Thank you for joining Fairtrade Loans! We’re excited to help you take control of your finances. Please follow these steps to start using your account:</p>

        <p><strong>Step 1:</strong> Verify your email address by tapping the button below:</p>
        <a href="{{ $verificationUrl }}" class="btn">Verify Your Email</a>

        <p><strong>Step 2:</strong> Open the Salary Advance Loans mobile app and log in using your credentials:</p>
        <table class="login-details">
            <tr>
                <th>Email</th>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <th>Password</th>
                <td>{{ $password }}</td>
            </tr>
        </table>

        <p>If you haven't downloaded our app yet, you can get it here:</p>
        <a href="{{ $appStoreUrl }}" class="btn">Download for iOS</a>
        <a href="{{ $playStoreUrl }}" class="btn">Download for Android</a>

        <p>For your security, we recommend changing your password once you log in.</p>

        <p>If you didn’t register an account, please contact our support team immediately.</p>

        <p>Welcome to the Salary Advance Loans family!</p>
        <p>Best regards,<br>The Salary Advance Loans Team</p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>Need help? Contact us at <a href="mailto:support@fairtrade.com">support@fairtrade.com</a>.</p>
    </div>
</div>
</body>
</html>
