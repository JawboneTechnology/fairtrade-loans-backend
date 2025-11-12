<!DOCTYPE html>
<html>
    <head>
        <title>Welcome to Our Platform</title>
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
                margin: 50px auto;
            }
            .logo-container {
                text-align: center;
                padding: 20px;
            }
            .email-header {
                background-color: #00bcd4;
                color: white;
                text-align: center;
                padding: 10px 0;
            }
            .email-content {
                padding: 20px;
                text-align: left;
            }
            table.login-details {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            table.login-details th,
            table.login-details td {
                border: 1px solid #dddddd;
                padding: 8px;
                text-align: left;
            }
            table.login-details th {
                background-color: #f4f4f4;
            }
            .email-footer {
                margin-top: 20px;
                font-size: 12px;
                color: #888;
                text-align: center;
                border-top: 1px solid #f4f4f4;
                padding-top: 10px;
            }
            .btn {
                background-color: #00bcd4;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                display: inline-block;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="logo-container">
                <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
            </div>
            <div class="email-header">
                <h2>Welcome to Salary Advance Loans</h2>
            </div>
            <div class="email-content">
                <p>Hello, {{ $userName }}</p>
                <p>Thank you for registering with us! Here are your login details:</p>
                <table class="login-details">
                    <tr>
                        <th>Email</th>
                        <td>{{ $userEmail }}</td>
                    </tr>
                    <tr>
                        <th>Password</th>
                        <td>{{ $userPassword }}</td>
                    </tr>
                </table>
                <p>Before you can access your account, you need to verify your email. Please click the button below to verify your account:</p>

                <a href="{{ $verificationUrl }}" class="btn">Verify Your Account</a>

                <p>Once verified, you can log in to your account We recommend changing your password after logging in.</p>
                <p>We recommend changing your password after logging in. If you didn’t create an account, please contact our support team immediately.</p>
            </div>
            <div class="email-footer">
                <p>&copy; {{ date('Y') }} Fairtrade Loan Management APIs. All rights reserved.</p>
            </div>
        </div>
    </body>
</html>
