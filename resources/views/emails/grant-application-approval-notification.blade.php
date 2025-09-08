
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grant Application Approved</title>
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
        .grant-details {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #dddddd;
            margin: 20px 0;
        }
        .grant-details col {
            width: 50%;
        }
        .grant-details td, .grant-details th {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .grant-details th {
            background-color: #dff7ff;
            font-weight: bold;
        }
        .email-footer {
            background-color: #f4f4f4;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #888888;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0fc0fc;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
<table class="email-container" cellpadding="0" cellspacing="0" width="600">
    <tr>
        <td class="logo-container">
            <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo" style="max-height: 80px;">
        </td>
    </tr>
    <tr>
        <td class="email-header">
            Grant Application Approved
        </td>
    </tr>
    <tr>
        <td class="email-content">
            <p>Dear {{ $applicant->first_name . ' ' . $applicant->last_name }},</p>
            <p>We are pleased to inform you that your grant application has been approved!</p>

            <table class="grant-details">
                <colgroup>
                    <col style="width:50%;">
                    <col style="width:50%;">
                </colgroup>
                <tr>
                    <th>Grant Type</th>
                    <td>{{ $grantType->name }}</td>
                </tr>
                <tr>
                    <th>Application ID</th>
                    <td>{{ $grant->id }}</td>
                </tr>
                <tr>
                    <th>Approved Amount</th>
                    <td>KES {{ number_format($grant->amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Approval Date</th>
                    <td>{{ $grant->approval_date->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <th>Expected Disbursement</th>
                    <td>{{ now()->addDays(3)->format('F j, Y') }}</td>
                </tr>
                @if($grantType->requires_dependent && $dependent)
                    <tr>
                        <th>Beneficiary</th>
                        <td>{{ $dependent->first_name . ' ' . $dependent->last_name }} ({{ $dependent->relationship }})</td>
                    </tr>
                @endif
            </table>

            <p><strong>Next Steps:</strong><br>
                The approved amount will be processed and disbursed according to our payment schedule. Please allow 5-7 business days for the funds to reflect in your account.</p>

            <p>If you have any questions, please contact our grants office at grants@fairtrade.org or call (123) 456-7890.</p>

            <center>
                <a href="{{"http://127.0.0.1:8000/api/v1/dashboard" . $grant->id}}" class="button">View Application Status</a>
            </center>

            <p>Thank you for being part of our community.</p>
            <p>Best regards,<br><strong>Grants Committee</strong><br>Fairtrade</p>
        </td>
    </tr>
    <tr>
        <td class="email-footer">
            &copy; {{ date('Y') }} Fairtrade Grant Management System. All rights reserved.
        </td>
    </tr>
</table>
</body>
</html>
