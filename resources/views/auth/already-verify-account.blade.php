<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verified Successfully</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #ff7f50;
            margin-bottom: 20px;
        }
        p {
            font-size: 18px;
            color: #333;
            line-height: 1.5;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
    <h1>Already Verified!</h1>
    <p>Your account is already verified. You can now log in and start enjoying our services. For your security, please keep your username and password confidential.</p>
    <p>Thank you for choosing Fairtrade. Welcome aboard!</p>
</div>
</body>
</html>
