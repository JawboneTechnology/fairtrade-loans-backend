<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fairtrade Guarantor Response</title>
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
            color: #4caf50;
            letter-spacing: 0;
        }
        p {
            font-size: 18px;
            color: #333;
            line-height: 0.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="{{ asset('fairtrade-logo.png') }}" alt="Fairtrade Logo" style="max-height: 80px;">
        <h1>Success!</h1>
        <p>You have successfully accepted the guarantee request.</p>
        <p>Thank you for your response.</p>
    </div>
</body>
</html>
