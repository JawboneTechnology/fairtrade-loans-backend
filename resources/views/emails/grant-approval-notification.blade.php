<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Grant Applied - Approval & Disbursement Required</title>
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
            .grant-details {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                border: 1px solid #dddddd;
                margin: 20px 0;
            }
            .grant-details col {
                width: 50%;
            }
            .grant-details td, .grant-details th {
                border: 1px solid #dddddd;
                padding: 8px;
                text-align: left;
                font-size: 14px;
            }
            .grant-details th {
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
            .button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #0fc0fc;
                color: #ffffff;
                text-decoration: none;
                border-radius: 4px;
                margin: 15px 0;
            }
            .urgent {
                color: #e74c3c;
                font-weight: bold;
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
                    Grant Applied - Approved & Disbursement Required
                </td>
            </tr>
            <tr>
                <td class="email-content">
                    <p>Dear Grants Administrator,</p>
                    <p>A grant application has been applied and requires approved and disbursement processing.</p>

                    <table class="grant-details">
                        <colgroup>
                            <col style="width:50%;">
                            <col style="width:50%;">
                        </colgroup>
                        <tr>
                            <th>Application ID</th>
                            <td>{{ $grant->id }}</td>
                        </tr>
                        <tr>
                            <th>Applicant Name</th>
                            <td>{{ $applicant->first_name . ' ' . $applicant->last_name }}</td>
                        </tr>
                        <tr>
                            <th>Applicant Email</th>
                            <td>{{ $applicant->email }}</td>
                        </tr>
                        <tr>
                            <th>Grant Type</th>
                            <td>{{ $grantType->name }} ({{ $grantType->grant_code }})</td>
                        </tr>
                        <tr>
                            <th>Approved Amount</th>
                            <td>KES {{ number_format($grant->amount, 2) }}</td>
                        </tr>
                        <tr>
                            <th>Reason</th>
                            <td>{{ $grant->reason }}</td>
                        </tr>
                        @if($grantType->requires_dependent && $dependent)
                            <tr>
                                <th>Beneficiary Details</th>
                                <td>
                                    {{ $dependent->first_name . ' ' . $dependent->last_name }}<br>
                                    Relationship: {{ $dependent->relationship }}<br>
                                    DOB: {{ $dependent->date_of_birth->format('m/d/Y') }}
                                </td>
                            </tr>
                       @endif
                        <tr>
                            <th>Admin Notes</th>
                            <td>{{ $grant->admin_notes ?? 'None' }}</td>
                        </tr>
                    </table>

                    <p><strong class="urgent">Disbursement Instructions:</strong></p>
                    <ol>
                        <li>Verify the application details in the system</li>
                        <li>Process payment through the standard disbursement channel</li>
                        <li>Mark as paid in the system after completion</li>
                    </ol>

                    <p class="urgent">Processing Deadline: {{ now()->addDays(3)->format('F j, Y') }}</p>

                    <center>
                        <a href={{"http://127.0.0.1:8000/api/v1/dashboard" . $grant->id}} class="button">View Full Application</a>
                    </center>

                    <p>Please confirm when disbursement is complete by updating the application status.</p>
                    <p>Thank you,<br><strong>Grants Management System</strong><br>Fairtrade</p>
                </td>
            </tr>
            <tr>
                <td class="email-footer">
                    &copy; {{ date('Y') }} Fairtrade Grant Management System. All rights reserved.
                </td>
            </tr>
        </table>
    </body>
</html>
