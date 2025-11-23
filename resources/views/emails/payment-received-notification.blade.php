<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Received Notification</title>
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
        .success-badge {
            background-color: #4caf50;
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
        .payment-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            table-layout: fixed;
        }
        .payment-details col {
            width: 50%;
        }
        .payment-details th, .payment-details td {
            border: 1px solid #dddddd;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        .payment-details th {
            background-color: #d8fada;
            font-weight: bold;
        }
        .payment-details tr:nth-child(even) td {
            background-color: #f9f9f9;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
            border-radius: 4px;
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .amount-highlight {
            font-size: 24px;
            color: #4caf50;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="logo-container">
        <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>✓ Payment Received Successfully</h2>
    </div>
    <div class="email-content">
        <p>Dear {{ $applicantName }},</p>
        
        <p>We are pleased to confirm that your payment has been successfully received and processed.</p>

        <div class="amount-highlight">
            KES {{ number_format($transaction->amount, 2) }}
        </div>

        <span class="success-badge">✓ Payment Confirmed</span>

        <h3 style="color: #4caf50; margin-top: 20px;">Payment Details</h3>
        <table class="payment-details">
            <colgroup>
                <col style="width:45%;">
                <col style="width:55%;">
            </colgroup>
            <tr>
                <th>Transaction Reference</th>
                <td><strong>{{ $transaction->mpesa_receipt_number ?? $transaction->transaction_reference ?? $transaction->transaction_id ?? 'N/A' }}</strong></td>
            </tr>
            <tr>
                <th>Payment Amount</th>
                <td>KES {{ number_format($transaction->amount, 2) }}</td>
            </tr>
            <tr>
                <th>Payment Method</th>
                <td>{{ $paymentMethod ?? 'M-Pesa' }}</td>
            </tr>
            <tr>
                <th>Transaction Date</th>
                <td>{{ $transaction->transaction_date ? \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</td>
            </tr>
        </table>

        <h3 style="color: #333; margin-top: 30px;">Loan Information</h3>
        <table class="payment-details">
            <colgroup>
                <col style="width:45%;">
                <col style="width:55%;">
            </colgroup>
            <tr>
                <th>Loan Number</th>
                <td>{{ $loan->loan_number }}</td>
            </tr>
            <tr>
                <th>New Loan Balance</th>
                <td><strong>KES {{ number_format($newLoanBalance ?? $loan->loan_balance, 2) }}</strong></td>
            </tr>
            <tr>
                <th>Monthly Installment</th>
                <td>KES {{ number_format($loan->monthly_installment, 2) }}</td>
            </tr>
            @if($loan->next_due_date)
            <tr>
                <th>Next Due Date</th>
                <td>{{ \Carbon\Carbon::parse($loan->next_due_date)->format('d M Y') }}</td>
            </tr>
            @endif
            @if(($newLoanBalance ?? $loan->loan_balance) <= 0)
            <tr>
                <th>Loan Status</th>
                <td><span style="color: #4caf50; font-weight: bold;">✓ FULLY PAID</span></td>
            </tr>
            @endif
        </table>

        @if(($newLoanBalance ?? $loan->loan_balance) <= 0)
        <div class="highlight">
            <p style="margin: 0;"><strong>🎉 Congratulations!</strong> Your loan has been fully paid. Thank you for your timely payments!</p>
        </div>
        @else
        <div class="highlight">
            <p style="margin: 0;"><strong>Reminder:</strong> Your next installment of KES {{ number_format($loan->monthly_installment, 2) }} is due on {{ \Carbon\Carbon::parse($loan->next_due_date)->format('d M Y') }}.</p>
        </div>
        @endif

        <p style="margin-top: 20px;">Thank you for your payment. We appreciate your business!</p>

        <p>If you have any questions or need further assistance, please don't hesitate to contact our support team.</p>
        
        <p>Best regards,<br><strong>The Fairtrade Salary Advance Loans Team</strong></p>
    </div>
    <div class="email-footer">
        <p>&copy; {{ date('Y') }} Fairtrade Salary Advance Loans. All rights reserved.</p>
        <p>This email was sent automatically. Please do not reply directly to this email.</p>
        <p>For support, contact us through your dashboard or our support channels.</p>
    </div>
</div>
</body>
</html>
