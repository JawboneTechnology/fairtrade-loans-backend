<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loan Application Submitted</title>
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
            font-size: 24px;
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
        .btn {
            background-color: #d4ff47;
            color: #333;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #c0e63d;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .loan-details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .loan-details-table th,
        .loan-details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        .loan-details-table th {
            background-color: #0fc0fc;
            color: white;
        }
        .loan-details-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .loan-details-table tr:hover {
            background-color: #f1f1f1;
        }
        .loan-details-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #f4f4f4;
        }
    </style>
</head>

<body>
<div class="email-container">
    <div class="logo-container">
        <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>Loan Application Submitted</h2>
    </div>
    <div class="email-content">
        <p>Hello {{ $applicantName }},</p>
        <p>Your loan application has been successfully submitted. Here are the details of your application:</p>

        <!-- Loan Details Table -->
        <table class="loan-details-table">
            <colgroup>
                <col style="width:50%;">
                <col style="width:50%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Detail</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Loan Number</strong></td>
                    <td>{{ $loan->loan_number }}</td>
                </tr>
                <tr>
                    <td><strong>Loan Type</strong></td>
                    <td>{{ $loanType }}</td>
                </tr>
                <tr>
                    <td><strong>Loan Amount</strong></td>
                    <td>KES {{ number_format($loan->loan_amount, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Loan Balance</strong></td>
                    <td>KES {{ number_format($loan->loan_balance, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Interest Rate</strong></td>
                    <td>{{ $loan->interest_rate }}%</td>
                </tr>
                <tr>
                    <td><strong>Next Due Date</strong></td>
                    <td>{{ $loan->next_due_date }}</td>
                </tr>
                <tr>
                    <td><strong>Tenure (Months)</strong></td>
                    <td>{{ $loan->tenure_months }}</td>
                </tr>
                <tr>
                    <td><strong>Monthly Installment</strong></td>
                    <td>KES {{ number_format($loan->monthly_installment, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td>{{ $loan->loan_status }}</td>
                </tr>
                <tr>
                    <td><strong>Guarantors</strong></td>
                    <td>{{ $guarantors }}</td>
                </tr>
            </tbody>
        </table>

        <p>You can track the status of your loan application by logging into your account:</p>
        <a href="{{ $applicantDashboardUrl }}" class="btn">View Application</a>

        <p>If you have any questions or need further assistance, please contact our support team.</p>
        <p>Best regards,<br>The Loan Management Team</p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
    </div>
</div>
</body>

</html>
