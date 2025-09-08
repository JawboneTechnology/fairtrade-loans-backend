<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mini Statement</title>
        <style>
            /* Embedded CSS for email layout */
            body {
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
                font-family: Arial, sans-serif;
            }
            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                padding: 20px;
            }
            .logo-container {
                text-align: center;
                padding: 20px;
            }
            .logo-container img {
                max-height: 80px;
            }
            .email-header {
                background-color: #0fc0fc;
                color: #ffffff;
                text-align: center;
                padding: 20px;
                font-size: 24px;
                font-weight: bold;
            }
            .email-content {
                padding: 20px;
                color: #333333;
                font-size: 16px;
                line-height: 1.5;
            }
            .mini-statement-section {
                margin: 20px 0;
            }
            .mini-statement-section h2 {
                margin-bottom: 10px;
                font-size: 18px;
            }
            /* Table styles for Loan Details only */
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #dddddd;
                padding: 8px;
                text-align: left;
                font-size: 14px;
            }
            th {
                background-color: #dff7ff;
                font-weight: bold;
            }
            /* Div layout for Transactions and Deductions */
            .transactions, .deductions {
                border: 1px solid #dddddd;
            }
            .transaction-row, .deduction-row {
                display: flex;
                border-bottom: 1px solid #dddddd;
            }
            .transaction-row.header, .deduction-row.header {
                background-color: #dff7ff;
                font-weight: bold;
            }
            .transaction-col, .deduction-col {
                flex: 1;
                padding: 8px;
                border-right: 1px solid #dddddd;
                font-size: 14px;
            }
            .transaction-col:last-child, .deduction-col:last-child {
                border-right: none;
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
        <div class="email-container">
            <div class="logo-container">
                <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo">
            </div>
            <div class="email-header">
                Loan Mini Statement
            </div>
            <div class="email-content">
                <p>Hello {{ $userName }},</p>
                <p>Your mini statement is attached to this email as a PDF. Please find the details below:</p>

                <p>If you have any questions, feel free to contact us.</p>
                <p>Best regards,<br>The Fairtrade Team</p>
            </div>
            <div class="email-footer">
                &copy; {{ date('Y') }} Fairtrade Loan Management System. All rights reserved.
            </div>
        </div>
    </body>
</html>
