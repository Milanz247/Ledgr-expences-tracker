<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f4f4f5;
            padding: 40px 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #000000;
            padding: 40px 30px;
            text-align: center;
        }
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            font-size: 28px;
            font-weight: bold;
        }
        .logo-icon {
            width: 32px;
            height: 32px;
            background-color: #ffffff;
            border-radius: 8px;
            display: inline-block;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #18181b;
            margin-bottom: 16px;
        }
        .message {
            font-size: 16px;
            color: #52525b;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .otp-container {
            background-color: #f4f4f5;
            border: 2px solid #e4e4e7;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            margin-bottom: 32px;
        }
        .otp-label {
            font-size: 14px;
            font-weight: 600;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .otp-code {
            font-size: 48px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
        }
        .otp-expiry {
            font-size: 14px;
            color: #71717a;
            margin-top: 12px;
        }
        .warning {
            background-color: #fef3c7;
            border-left: 4px solid: #f59e0b;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .warning-text {
            font-size: 14px;
            color: #92400e;
            line-height: 1.5;
        }
        .footer {
            background-color: #f4f4f5;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e4e4e7;
        }
        .footer-text {
            font-size: 14px;
            color: #71717a;
            line-height: 1.6;
        }
        .footer-brand {
            font-weight: 600;
            color: #18181b;
        }
        .divider {
            height: 1px;
            background-color: #e4e4e7;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <span class="logo-icon"></span>
                <span>Ledgr</span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <h1 class="greeting">Hello {{ $userName }},</h1>
            
            <p class="message">
                We received a request to reset your password for your Ledgr account. 
                Use the verification code below to complete your password reset.
            </p>

            <!-- OTP Box -->
            <div class="otp-container">
                <div class="otp-label">Your Verification Code</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">This code will expire in 10 minutes</div>
            </div>

            <!-- Warning -->
            <div class="warning">
                <p class="warning-text">
                    <strong>Security Notice:</strong> If you didn't request this password reset, 
                    please ignore this email or contact our support team immediately. 
                    Your account security is important to us.
                </p>
            </div>

            <div class="divider"></div>

            <p class="message">
                For your security, this code can only be used once and will expire in 10 minutes.
                After 5 failed attempts, you'll need to request a new code.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                This email was sent by <span class="footer-brand">Ledgr</span><br>
                Professional Financial Management Platform<br>
                <br>
                © 2026 SHA CDL Solution. All rights reserved.<br>
                Developed by Milan Madusanka
            </p>
        </div>
    </div>
</body>
</html>
