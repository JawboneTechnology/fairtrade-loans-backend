<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Application Approved</title>
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
        .loan-details {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #dddddd; /* Outside border */
            margin: 20px 0;
        }
        .loan-details col {
            width: 50%;
        }
        .loan-details td, .loan-details th {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .loan-details th {
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
            Loan Application Approval
        </td>
    </tr>
    <tr>
        <td class="email-content">
            <p>Hello Administrator,</p>
            <p>
                The loan application submitted by <strong>{{ $applicantName ?? 'Applicant Name' }}</strong> has been approved by all guarantors and is now ready for disbursement.
            </p>
            <table class="loan-details">
                <colgroup>
                    <col style="width:50%;">
                    <col style="width:50%;">
                </colgroup>
                <tr>
                    <th>Loan Number</th>
                    <td>{{ $loan->loan_number ?? "" }}</td>
                </tr>
                <tr>
                    <th>Loan Type</th>
                    <td>{{ $loanType ?? "" }}</td>
                </tr>
                <tr>
                    <th>Applicant Name</th>
                    <td>{{ $applicantName ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Loan Amount</th>
                    <td>KES {{ number_format($loan->loan_amount, 2) }}</td>
                </tr>
                <tr>
                    <th>Loan Remarks</th>
                    <td>{{ $loan->remarks ?? 'No Remarks!' }}</td>
                </tr>
                <tr>
                    <th>Cancellation Date</th>
                    <td>{{ \Carbon\Carbon::parse($loan->updated_at)->format('F j, Y, g:i a') ?? "" }}</td>
                </tr>
            </table>
            <p>Please proceed with processing the requested loan amount.</p>
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
