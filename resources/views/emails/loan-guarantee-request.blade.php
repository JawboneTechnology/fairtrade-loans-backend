<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loan Guarantee Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .email-header {
            background-color: #0fc0fc;
            color: #ffffff;
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
            color: #333333;
            line-height: 1.6;
        }
        .email-content p {
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-accept {
            background-color: #d4ff47;
            color: #000;
        }
        .btn-decline {
            background-color: #0fc0fc;
            color: #fff;
        }
        .email-footer {
            margin-top: 20px;
            font-size: 12px;
            color: #888888;
            text-align: center;
            border-top: 1px solid #f4f4f4;
            padding-top: 10px;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .note-section {
            background-color: #ebfaff;
            border: 1px solid #0fc0fc;
            border-radius: 5px;
            padding: 10px;
            margin: 15px 0;
            font-style: italic;
            color: #00a6df;
        }
        /* Responsive bordered table for Loan Details */
        .loan-details {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #ddd;
            margin: 20px 0;
        }
        .loan-details col {
            width: 50%;
        }
        .loan-details th,
        .loan-details td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        .loan-details th {
            background-color: #d4ff47;
        }
        @media screen and (max-width: 600px) {
            .loan-details, .loan-details th, .loan-details td {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Company Logo at the Top -->
        <div class="logo-container">
            <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo" style="max-height: 80px;">
        </div>
        <div class="email-header">
            <h2>Loan Guarantee Request</h2>
        </div>
        <div class="email-content">
            <p>Hello {{ $name }},</p>
            <p>
                You have been selected as a guarantor for a loan application. Please review the details below and choose whether to accept or decline this guarantee request.
            </p>

            <!-- Loan Details Section with Responsive Bordered Table -->
            <table class="loan-details">
                <colgroup>
                    <col style="width:50%;">
                    <col style="width:50%;">
                </colgroup>
                <tbody>
                <tr>
                    <td><strong>Loan Number:</strong></td>
                    <td>{{ $loan->loan_number }}</td>
                </tr>
                <tr>
                    <td><strong>Loan Type:</strong></td>
                    <td>{{ $loanName }}</td>
                </tr>
                <tr>
                    <td><strong>Your Guarantor ID:</strong></td>
                    <td>{{ $guarantorId }}</td>
                </tr>
                <tr>
                    <td><strong>Loan Amount:</strong></td>
                    <td>KES {{ number_format($loan->loan_amount, 2) }}</td>
                </tr>
                <tr>
                    <td><strong>Interest Rate:</strong></td>
                    <td>{{ $loan->interest_rate }}%</td>
                </tr>
                <tr>
                    <td><strong>Tenure (Months):</strong></td>
                    <td>{{ $loan->tenure_months }}</td>
                </tr>
                <tr>
                    <td><strong>Your Liability Amount:</strong></td>
                    <td>KES {{ number_format($guarantorLiabilityAmount, 2) }}</td>
                </tr>
                </tbody>
            </table>

            <div class="note-section">
                Note: This is the amount you will be responsible for repaying if the loan applicant defaults on their payments.
            </div>

            <p>
                To proceed, log in to your account and navigate to the loan guarantee section. You can accept or decline the request from there.
            </p>

            <!-- Example Buttons -->
            <p>
                <a href="{{ $acceptUrl }}" class="btn btn-accept">Accept</a>
                <a href="{{ $declineUrl }}" class="btn btn-decline">Decline</a>
            </p>

            <p>
                If you have any questions, please contact our support team for further assistance.
            </p>
            <p>
                Thank you,<br>
                Fairtrade Team
            </p>
        </div>
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} Fairtrade Loan Management System. All rights reserved.</p>
            <p>This email was sent automatically. Please do not reply directly.</p>
        </div>
    </div>
</body>
</html>
