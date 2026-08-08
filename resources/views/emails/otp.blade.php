<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>KP Kitchen - Password Reset OTP</title>
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
        .code-box {
            background-color: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 6px;
            text-align: center;
            padding: 16px;
            margin: 24px 0;
            color: #1f2937;
        }
        .text {
            font-size: 14px;
            line-height: 1.5;
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
        <div class="header">KP Kitchen</div>
        <p class="text">Hello,</p>
        <p class="text">We received a request to reset the password for your KP Kitchen Admin Panel account. Please use the following 6-digit One-Time Password (OTP) to complete the verification:</p>
        <div class="code-box">{{ $otp }}</div>
        <p class="text">This OTP code is valid for 10 minutes. If you did not make this request, you can safely ignore this email.</p>
        <div class="footer">
            © {{ date('Y') }} KP Kitchen. All rights reserved.
        </div>
    </div>
</body>
</html>
