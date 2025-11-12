<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        /* Embedded CSS for email layout */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .email-header {
            background-color: #0fc0fc;
            color: #ffffff;
            text-align: center;
            padding: 20px;
            font-size: 24px;
            font-weight: bold;
        }
        .logo-container {
            text-align: center;
            padding: 20px;
        }
        .logo-container img {
            max-height: 80px;
        }
        .email-content {
            width: 100%;
            padding: 20px;
            color: #333333;
            font-size: 16px;
            line-height: 1.5;
        }
        .email-content p {
            margin: 0 0 15px;
        }
        .reset-code {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .email-footer {
            background-color: #f4f4f4;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #888888;
        }
    </style>
</head>
<body>
<table class="email-container" cellpadding="0" cellspacing="0" width="600">
    <tr>
        <td class="logo-container">
            <img src="{{ asset('fairtrade-logo.png') }}" alt="Company Logo" style="max-height: 80px;">
        </td>
    </tr>
    <tr>
        <td class="email-header">
            Login OTP
        </td>
    </tr>
    <tr>
        <td class="email-content">
            <p>Hello {{ $userName ?? 'User' }},</p>
            <p>We received a request to log in to your account using your phone number. To proceed, please use the OTP code provided below to verify your identity:</p>
            <p class="reset-code">{{ $otpCode ?? '000000' }}</p>
            <p>Please note that this code will expire in 30 minutes.</p>
            <p>If you did not request this login, please ignore this email or contact our support team immediately.</p>
            <p>Thank you,<br>Fairtrade Team</p>
        </td>
    </tr>
    <tr>
        <td class="email-footer">
            &copy; {{ date('Y') }} Fairtrade Loan Management System. All rights reserved.
        </td>
    </tr>
</table>
</body>
</html>
