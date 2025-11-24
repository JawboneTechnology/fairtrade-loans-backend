<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Password Changed Notification</title>
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
            background-color: #0fc0fc;
            color: white;
            text-align: center;
            padding: 20px 0;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .email-header h2 {
            margin: 0;
        }
        .warning-badge {
            background-color: #0fc0fc;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin: 10px 0;
            font-size: 14px;
            font-weight: bold;
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
        .credentials-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .password-display {
            background-color: #f8f9fa;
            border: 2px dashed #6c757d;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            border-radius: 4px;
            color: #495057;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table th, .info-table td {
            border: 1px solid #dddddd;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        .info-table th {
            background-color: #e0f7fa;
            font-weight: bold;
        }
        .btn-login {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .security-notice {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-notice strong {
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="logo-container">
        <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>🔐 Password Changed</h2>
    </div>
    <div class="email-content">
        <p>Dear {{ $employeeName }},</p>
        
        <p>This is to inform you that your account password has been changed by {{ $adminName }}.</p>

        <span class="warning-badge">⚠ Important Security Notice</span>

        <div class="credentials-box">
            <p style="margin: 0;"><strong>Your New Login Credentials</strong></p>
            <p style="margin: 10px 0 5px 0;">Please use the following credentials to access your account:</p>
        </div>

        <table class="info-table">
            <tr>
                <th>Email / Username</th>
                <td><strong>{{ $employee->email }}</strong></td>
            </tr>
            <tr>
                <th>Employee ID</th>
                <td>{{ $employee->employee_id }}</td>
            </tr>
        </table>

        <p style="margin-top: 20px;"><strong>Your New Password:</strong></p>
        <div class="password-display">
            {{ $newPassword }}
        </div>

        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" class="btn-login">Login to Your Account</a>
        </div>

        <div class="security-notice">
            <p style="margin: 0;"><strong>🔒 Security Recommendations:</strong></p>
            <ul style="margin: 10px 0;">
                <li>Change this password after your first login</li>
                <li>Use a strong, unique password</li>
                <li>Never share your password with anyone</li>
                <li>If you didn't request this change, contact support immediately</li>
            </ul>
        </div>

        <h3 style="color: #0fc0fc; margin-top: 30px;">Change Details</h3>
        <table class="info-table">
            <tr>
                <th>Changed By</th>
                <td>{{ $adminName }}</td>
            </tr>
            <tr>
                <th>Date & Time</th>
                <td>{{ now()->format('d M Y, h:i A') }}</td>
            </tr>
            <tr>
                <th>Action Required</th>
                <td><strong>Please login and change your password</strong></td>
            </tr>
        </table>

        <p style="margin-top: 20px;">If you did not request this password change or have any concerns about your account security, please contact our support team immediately.</p>
        
        <p>Best regards,<br><strong>The Fairtrade Salary Advance Loans Team</strong></p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
        <p>For support, contact us through your dashboard or our support channels.</p>
        <p style="margin-top: 10px; color: #dc3545;"><strong>Security Notice:</strong> This email contains sensitive information. Please delete it after changing your password.</p>
    </div>
</div>
</body>
</html>

