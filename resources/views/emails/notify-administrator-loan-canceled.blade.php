<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loan Cancellation Notification</title>
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
            background-color: #4caf50;
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
            background-color: #4caf50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .loan-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            table-layout: fixed;
        }
        .loan-details col {
            width: 50%;
        }
        .loan-details th, .loan-details td {
            border: 1px solid #dddddd;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .loan-details th {
            background-color: #d8fada;
            font-weight: bold;
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
        <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>Loan Cancellation</h2>
    </div>
    <div class="email-content">
        <p>Hello {{ $adminName }},</p>
        <p>A loan cancellation has been initiated on the platform. Please review the details of the canceled loan below:</p>

        <table class="loan-details">
            <colgroup>
                <col style="width:50%;">
                <col style="width:50%;">
            </colgroup>
            <tr>
                <th>Loan Number</th>
                <td>{{ $loan->loan_number }}</td>
            </tr>
            <tr>
                <th>Applicant Name</th>
                <td>{{ $applicantName }}</td>
            </tr>
            <tr>
                <th>Loan Type</th>
                <td>{{ $loanType }}</td>
            </tr>
            <tr>
                <th>Loan Amount</th>
                <td>KES {{ number_format($loan->loan_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Loan Balance</th>
                <td>KES {{ number_format($loan->loan_balance, 2) }}</td>
            </tr>
            <tr>
                <th>Interest Rate</th>
                <td>{{ $loan->interest_rate }}%</td>
            </tr>
            <tr>
                <th>Next Due Date</th>
                <td>{{ $loan->next_due_date }}</td>
            </tr>
            <tr>
                <th>Tenure (Months)</th>
                <td>{{ $loan->tenure_months }}</td>
            </tr>
            <tr>
                <th>Monthly Installment</th>
                <td>KES {{ number_format($loan->monthly_installment, 2) }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $loan->loan_status }}</td>
            </tr>
            @php
                $qualifications = is_array($loan->qualifications)
                    ? $loan->qualifications
                    : json_decode($loan->qualifications, true);
            @endphp
            <tr>
                <th>Qualifications</th>
                <td>
                    <p><strong>Total Loans:</strong> {{ $qualifications['total_loans'] ?? 'N/A' }}</p>
                    <p><strong>Defaults:</strong> {{ $qualifications['defaults'] ?? 'N/A' }}</p>
                    <p><strong>Successful Repayments:</strong> {{ $qualifications['successfulRepayments'] ?? 'N/A' }}</p>
                    <p><strong>Credit Score:</strong> {{ $qualifications['credit_score'] ?? 'N/A' }}</p>
                </td>
            </tr>
            <tr>
                <th>Guarantors</th>
                <td>{{ $guarantors }}</td>
            </tr>
            <tr>
                <th>Cancellation Date</th>
                <td>{{ \Carbon\Carbon::parse($loan->updated_at)->format('F j, Y, g:i a') }}</td>
            </tr>
        </table>

        <p>You can review the complete details of the canceled loan by logging into the admin dashboard:</p>
        <a href="{{ $adminDashboardUrl }}" class="btn">View Loan Details</a>

        <p>If you require further action or need to contact the applicant, please refer to the information provided above.</p>
        <p>Best regards,<br>The System Notification Team</p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
    </div>
</div>
</body>
</html>
