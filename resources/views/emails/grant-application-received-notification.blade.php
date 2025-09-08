
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grant Application Received</title>
    <style>
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
        .application-details {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #dddddd;
            margin: 20px 0;
        }
        .application-details col {
            width: 50%;
        }
        .application-details td, .application-details th {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .application-details th {
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
        .status-pending {
            color: #e67e22;
            font-weight: bold;
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
            Grant Application Received
        </td>
    </tr>
    <tr>
        <td class="email-content">
            <p>Dear {{ $applicant->first_name . ' ' . $applicant->last_name }},</p>

            <p>Thank you for submitting your grant application to Fairtrade. We're pleased to confirm that we've received your application and it's now <span class="status-pending">pending administrative approval</span>.</p>

            <table class="application-details">
                <colgroup>
                    <col style="width:50%;">
                    <col style="width:50%;">
                </colgroup>
                <tr>
                    <th>Application Reference</th>
                    <td>{{ $grant->id }}</td>
                </tr>
                <tr>
                    <th>Grant Type</th>
                    <td>{{ $grantType->name }}</td>
                </tr>
                <tr>
                    <th>Requested Amount</th>
                    <td>KES {{ number_format($grant->amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Application Date</th>
                    <td>{{ $grant->created_at->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <th>Current Status</th>
                    <td><span class="status-pending">Pending Approval</span></td>
                </tr>
                @if($grantType->requires_dependent && $dependent)
                    <tr>
                        <th>Beneficiary</th>
                        <td>{{ $dependent->name }} ({{ $dependent->relationship }})</td>
                    </tr>
                @endif
            </table>

            <p><strong>What happens next?</strong></p>
            <ul>
                <li>Your application is now in our review queue</li>
                <li>Our grants committee will evaluate your request</li>
                <li>You'll receive another notification once a decision is made</li>
                <li>Typical processing time is 5-7 business days</li>
            </ul>

            <p>If you need to make any changes to your application or have questions, please contact our grants office at <a href="mailto:grants@fairtrade.org">grants@fairtrade.org</a> or call (123) 456-7890.</p>

            <center>
                <a href="{{"http://127.0.0.1:8000/api/v1/dashboard" . $grant->id}}" class="button">View Your Application</a>
            </center>

            <p>We appreciate your patience during this process.</p>

            <p>Best regards,<br>
                <strong>The Grants Team</strong><br>
                Fairtrade</p>
        </td>
    </tr>
    <tr>
        <td class="email-footer">
            &copy; {{ date('Y') }} Fairtrade Grant Management System. All rights reserved.<br>
            This is an automated message - please do not reply directly to this email.
        </td>
    </tr>
</table>
</body>
</html>
