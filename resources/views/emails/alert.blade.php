<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KP's Kitchen Notification</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            padding: 24px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .header {
            font-size: 24px;
            font-weight: 700;
            color: #ff5722;
            margin-bottom: 24px;
            text-align: center;
        }
        .text {
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
        }
        .footer {
            font-size: 12px;
            color: #9ca3af;
            text-align: center;
            margin-top: 32px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">KP's Kitchen</div>
        <p class="text">{{ $messageBody }}</p>
        <div class="footer">
            © {{ date('Y') }} KP's Kitchen. All rights reserved.
        </div>
    </div>
</body>
</html>
