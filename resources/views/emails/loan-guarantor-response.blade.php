<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guarantor Response Notification</title>
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
        /* Responsive bordered table for Response Details */
        .response-details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .response-details th,
        .response-details td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        .response-details th {
            background-color: #dff7ff;
        }
        @media screen and (max-width: 600px) {
            .response-details, .response-details th, .response-details td {
                font-size: 14px;
            }
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
    </style>
</head>
<body>
<div class="email-container">
    <!-- Company Logo -->
    <div class="logo-container">
{{-- <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">--}}
        <img src="http://localhost:8000/fairtrade-logo.png" alt="Fairtrade Logo" style="max-height: 80px;">
    </div>
    <div class="email-header">
        <h2>Guarantor Response Notification</h2>
    </div>
    <div class="email-content">
        <p>Hello {{ $applicantName }},</p>
        <p>
            We are writing to inform you that your guarantor has responded to your loan guarantee request. Please find the details of the response below:
        </p>
        <!-- Response Details Table -->
        <table class="response-details">
            <tbody>
            <tr>
                <th>Loan Number</th>
                <td>{{ $loan->loan_number }}</td>
            </tr>
            <tr>
                <th>Guarantor Name</th>
                <td>{{ $guarantorName }}</td>
            </tr>
            <tr>
                <th>Response</th>
                <td style="background: {{ $response == 'accepted' ? '#cefcce' : '#fcd9ce' }};">
                    {{ ucfirst($response) }}
                </td>
            </tr>
            <tr>
                <th>Response Date</th>
                <td>{{ \Carbon\Carbon::parse($responseDate)->format('F j, Y, g:i a') }}</td>
            </tr>
            </tbody>
        </table>
        <p>
            If you have any questions regarding this response, please do not hesitate to contact our support team.
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
