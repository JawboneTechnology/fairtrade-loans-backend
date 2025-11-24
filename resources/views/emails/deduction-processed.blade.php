<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loan Deduction Processed</title>
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
        .success-badge {
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
        .deduction-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            table-layout: fixed;
        }
        .deduction-details col {
            width: 50%;
        }
        .deduction-details th, .deduction-details td {
            border: 1px solid #dddddd;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        .deduction-details th {
            background-color: #e0f7fa;
            font-weight: bold;
        }
        .deduction-details tr:nth-child(even) td {
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
            color: #0fc0fc;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .deduction-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin: 10px 0;
        }
        .badge-manual { background-color: #9C27B0; color: white; }
        .badge-automatic { background-color: #4CAF50; color: white; }
        .badge-bank { background-color: #0fc0fc; color: white; }
        .badge-mobile { background-color: #FF9800; color: white; }
        .badge-online { background-color: #00BCD4; color: white; }
        .badge-cheque { background-color: #795548; color: white; }
        .badge-cash { background-color: #4CAF50; color: white; }
        .badge-partial { background-color: #FFC107; color: white; }
        .badge-early { background-color: #8BC34A; color: white; }
        .badge-penalty { background-color: #F44336; color: white; }
        .badge-refund { background-color: #03A9F4; color: white; }
        .congratulations {
            background-color: #d4edda;
            padding: 15px;
            border-left: 4px solid #28a745;
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
        <h2>✓ Loan Deduction Processed</h2>
    </div>
    <div class="email-content">
        <p>Dear {{ $applicantName }},</p>
        
        <p>We are writing to confirm that a deduction has been successfully processed for your loan.</p>

        <div class="amount-highlight">
            KES {{ number_format($deduction->deduction_amount, 2) }}
        </div>

        <span class="success-badge">✓ Deduction Confirmed</span>

        @php
            $badgeClass = match($deductionType) {
                'Manual' => 'badge-manual',
                'Automatic' => 'badge-automatic',
                'Bank_Transfer' => 'badge-bank',
                'Mobile_Money' => 'badge-mobile',
                'Online_Payment' => 'badge-online',
                'Cheque' => 'badge-cheque',
                'Cash' => 'badge-cash',
                'Partial_Payments' => 'badge-partial',
                'Early_Repayments' => 'badge-early',
                'Penalty_Payments' => 'badge-penalty',
                'Refunds' => 'badge-refund',
                default => 'badge-manual'
            };

            $deductionTypeLabel = match($deductionType) {
                'Manual' => 'Manual Deduction',
                'Automatic' => 'Automatic Deduction',
                'Bank_Transfer' => 'Bank Transfer',
                'Mobile_Money' => 'Mobile Money Payment',
                'Online_Payment' => 'Online Payment',
                'Cheque' => 'Cheque Payment',
                'Cash' => 'Cash Payment',
                'Partial_Payments' => 'Partial Payment',
                'Early_Repayments' => 'Early Repayment',
                'Penalty_Payments' => 'Penalty Payment',
                'Refunds' => 'Refund',
                default => $deductionType
            };
        @endphp

        <span class="deduction-type-badge {{ $badgeClass }}">{{ $deductionTypeLabel }}</span>

                <h3 style="color: #0fc0fc; margin-top: 20px;">Deduction Details</h3>
        <table class="deduction-details">
            <colgroup>
                <col style="width:45%;">
                <col style="width:55%;">
            </colgroup>
            <tr>
                <th>Deduction Reference</th>
                <td><strong>{{ $deduction->id }}</strong></td>
            </tr>
            <tr>
                <th>Deduction Amount</th>
                <td>KES {{ number_format($deduction->deduction_amount, 2) }}</td>
            </tr>
            <tr>
                <th>Deduction Type</th>
                <td>{{ $deductionTypeLabel }}</td>
            </tr>
            <tr>
                <th>Deduction Date</th>
                <td>{{ $deduction->deduction_date ? \Carbon\Carbon::parse($deduction->deduction_date)->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}</td>
            </tr>
        </table>

        <h3 style="color: #333; margin-top: 30px;">Loan Information</h3>
        <table class="deduction-details">
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
        <div class="congratulations">
            <p style="margin: 0;"><strong>🎉 Congratulations!</strong> Your loan has been fully paid. Thank you for your timely payments!</p>
        </div>
        @elseif($deductionType === 'Early_Repayments')
        <div class="highlight">
            <p style="margin: 0;"><strong>Thank you for your early payment!</strong> Your new balance is KES {{ number_format($newLoanBalance ?? $loan->loan_balance, 2) }}. Your commitment to early repayment is appreciated!</p>
        </div>
        @elseif($deductionType === 'Partial_Payments')
        <div class="highlight">
            <p style="margin: 0;"><strong>Reminder:</strong> This was a partial payment. Your next full installment of KES {{ number_format($loan->monthly_installment, 2) }} is due on {{ \Carbon\Carbon::parse($loan->next_due_date)->format('d M Y') }}.</p>
        </div>
        @elseif($deductionType === 'Penalty_Payments')
        <div class="highlight">
            <p style="margin: 0;"><strong>Penalty Payment Received:</strong> This payment covers penalties. Please ensure timely payments going forward to avoid additional charges.</p>
        </div>
        @elseif($deductionType === 'Refunds')
        <div class="highlight">
            <p style="margin: 0;"><strong>Refund Processed:</strong> A refund has been applied to your loan account. Your new balance is KES {{ number_format($newLoanBalance ?? $loan->loan_balance, 2) }}.</p>
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

