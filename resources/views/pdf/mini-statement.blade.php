<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mini Statement</title>
        <style>
            body {
                font-family: Arial, sans-serif;
            }
            .loan-details, .transactions, .deductions {
                margin-bottom: 20px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f4f4f4;
            }
        </style>
    </head>
    <body>
        <h1>Mini Statement</h1>

        <div class="loan-details">
            <h2>Loan Details</h2>
            <table>
                <tr>
                    <th>Loan Number</th>
                    <td>{{ $miniStatement['loan_details']['loan_number'] }}</td>
                </tr>
                <tr>
                    <th>Loan Amount</th>
                    <td>KES {{ number_format($miniStatement['loan_details']['loan_amount'], 2) }}</td>
                </tr>
                <tr>
                    <th>Loan Balance</th>
                    <td>KES {{ number_format($miniStatement['loan_details']['loan_balance'], 2) }}</td>
                </tr>
                <tr>
                    <th>Interest Rate</th>
                    <td>{{ $miniStatement['loan_details']['interest_rate'] }}%</td>
                </tr>
                <tr>
                    <th>Tenure (Months)</th>
                    <td>{{ $miniStatement['loan_details']['tenure_months'] }}</td>
                </tr>
                <tr>
                    <th>Monthly Installment</th>
                    <td>KES {{ number_format($miniStatement['loan_details']['monthly_installment'], 2) }}</td>
                </tr>
                <tr>
                    <th>Next Due Date</th>
                    <td>{{ $miniStatement['loan_details']['next_due_date'] }}</td>
                </tr>
                <tr>
                    <th>Loan Status</th>
                    <td>{{ $miniStatement['loan_details']['loan_status'] }}</td>
                </tr>
            </table>
        </div>

        <div class="transactions">
            <h2>Transaction History</h2>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Reference</th>
                </tr>
                </thead>
                <tbody>
                    @foreach ($miniStatement['transactions'] as $transaction)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($transaction['transaction_date'])->format('F j, Y') }}</td>
                            <td>KES {{ number_format($transaction['amount'], 2) }}</td>
                            <td>{{ $transaction['payment_type'] }}</td>
                            <td>{{ $transaction['transaction_reference'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="deductions">
            <h2>Deductions</h2>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Type</th>
                </tr>
                </thead>
                <tbody>
                    @foreach ($miniStatement['deductions'] as $deduction)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($deduction['deduction_date'])->format('F j, Y, g:i a') }}</td>
                            <td>KES {{ number_format($deduction['amount'], 2) }}</td>
                            <td>{{ $deduction['deduction_type'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </body>
</html>
