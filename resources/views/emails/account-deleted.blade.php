<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Deletion Notification</title>
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
        .notice-badge {
            background-color: #dc3545;
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
        .info-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
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
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .next-steps {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .next-steps ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .contact-box {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="logo-container">
        <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>⚠️ Account Deletion Notice</h2>
    </div>
    <div class="email-content">
        <p>Dear {{ $userName }},</p>
        
        <p>We are writing to inform you that your account with Fairtrade Salary Advance Loans has been successfully deleted from our platform.</p>

        <span class="notice-badge">Account Removed</span>

        <div class="info-box">
            <p style="margin: 0;"><strong>🚫 Your account has been permanently removed</strong></p>
            <p style="margin: 10px 0 0 0;">This action was completed on {{ $deletionDate }}.</p>
        </div>

        <h3 style="color: #0fc0fc; margin-top: 20px;">Account Details</h3>
        <table class="info-table">
            <tr>
                <th>Employee ID</th>
                <td>{{ $employeeId }}</td>
            </tr>
            <tr>
                <th>Email Address</th>
                <td>{{ $email }}</td>
            </tr>
            <tr>
                <th>Deletion Date</th>
                <td>{{ $deletionDate }}</td>
            </tr>
            @if($deletedBy)
            <tr>
                <th>Deleted By</th>
                <td>{{ $deletedBy }}</td>
            </tr>
            @endif
        </table>

        <h3 style="color: #333; margin-top: 30px;">What This Means</h3>
        <div class="info-box">
            <ul style="margin: 10px 0;">
                <li>You no longer have access to your account</li>
                <li>Your personal data has been removed from our active systems</li>
                <li>You cannot log in to the platform</li>
                <li>All active sessions have been terminated</li>
                <li>Any saved preferences or settings have been deleted</li>
            </ul>
        </div>

        <h3 style="color: #ffc107; margin-top: 30px;">⚡ What To Do Next</h3>
        <div class="next-steps">
            <p style="margin: 0;"><strong>If you requested this deletion:</strong></p>
            <ul>
                <li>No further action is required</li>
                <li>Your account deletion is complete</li>
                <li>Thank you for using our services</li>
            </ul>

            <p style="margin: 15px 0 0 0;"><strong>If you did NOT request this deletion:</strong></p>
            <ul>
                <li><strong>Contact support immediately</strong></li>
                <li>Report this as unauthorized account deletion</li>
                <li>We will investigate and assist you</li>
            </ul>
        </div>

        <h3 style="color: #17a2b8; margin-top: 30px;">📞 Need Help?</h3>
        <div class="contact-box">
            <p style="margin: 0;"><strong>Contact Our Support Team:</strong></p>
            <table style="width: 100%; margin-top: 10px;">
                <tr>
                    <td style="padding: 5px 0;"><strong>Email:</strong></td>
                    <td>{{ $supportEmail }}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0;"><strong>Phone:</strong></td>
                    <td>{{ $supportPhone }}</td>
                </tr>
                <tr>
                    <td style="padding: 5px 0;"><strong>Hours:</strong></td>
                    <td>Monday - Friday, 8:00 AM - 5:00 PM</td>
                </tr>
            </table>
        </div>

        <h3 style="color: #333; margin-top: 30px;">🔄 Want to Return?</h3>
        <p>If you wish to use our services again in the future, you can create a new account at any time. Please note that:</p>
        <ul>
            <li>You will need to go through the registration process again</li>
            <li>Your previous account history will not be restored</li>
            <li>You may need to provide documentation for verification</li>
        </ul>

        <div style="background-color: #e7f3ff; padding: 15px; margin: 20px 0; border-radius: 4px; border-left: 4px solid #0fc0fc;">
            <p style="margin: 0;"><strong>📋 Data Retention Notice:</strong></p>
            <p style="margin: 10px 0 0 0;">In compliance with legal and regulatory requirements, some of your financial transaction records may be retained in our archives for the legally mandated period. These records are securely stored and will not be used for marketing purposes.</p>
        </div>

        <p style="margin-top: 20px;">Thank you for being part of the Fairtrade Salary Advance Loans community.</p>

        <p>Best regards,<br><strong>The Fairtrade Salary Advance Loans Team</strong></p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
        <p>For support, contact us at {{ $supportEmail }} or call {{ $supportPhone }}</p>
    </div>
</div>
</body>
</html>

