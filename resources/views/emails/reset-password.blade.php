<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — BeetleOps HRMS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: none;
            color: #1f2937;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 15px;
        }
        .email-card {
            max-width: 540px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            border: 1px solid #e5e7eb;
        }
        .email-header {
            background-color: #ffffff;
            padding: 36px 30px 24px 30px;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }
        .email-title {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin: 0;
            letter-spacing: -0.02em;
        }
        .email-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
            margin-bottom: 0;
        }
        .email-body {
            padding: 32px 30px;
            font-size: 15px;
            line-height: 1.6;
            color: #374151;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0;
        }
        .btn-reset {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }
        .btn-reset:hover {
            background-color: #1d4ed8;
        }
        .security-badge {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 4px;
            font-size: 13px;
            color: #065f46;
            margin-top: 24px;
        }
        .email-footer {
            padding: 24px 30px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.5;
        }
        .email-footer a {
            color: #6b7280;
            text-decoration: underline;
        }
        .fallback-box {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-card">
            <!-- Header with HRMS Heading -->
            <div class="email-header">
                <h1 class="email-title">BeetleOps HRMS</h1>
                <p class="email-subtitle">Human Resource Management System</p>
            </div>

            <!-- Body -->
            <div class="email-body">
                <p style="margin-top: 0;">Hello <strong>{{ $userName ?? 'there' }}</strong>,</p>
                <p>We received a request to reset your password for your <strong>BeetleOps HRMS</strong> account. Click the button below to proceed:</p>

                <div class="btn-container">
                    <a href="{{ $resetUrl }}" class="btn-reset" target="_blank">Reset My Password</a>
                </div>

                <div class="security-badge">
                    <strong>Note:</strong> This password reset link is valid for <strong>{{ $expireMinutes ?? 15 }} minutes</strong> and can only be used once.
                </div>

                <p style="margin-top: 20px; font-size: 13.5px; color: #6b7280;">
                    If you did not request a password reset, you can safely ignore this email. Your password will not change and your account remains completely secure.
                </p>

                <!-- Fallback URL -->
                <div class="fallback-box">
                    If you are having trouble clicking the "Reset My Password" button, use the URL below into your web browser:<br>
                    <a href="{{ $resetUrl }}" style="color: #2563eb;">{{ $resetUrl }}</a>
                </div>
            </div>

            <!-- Footer with Dynamic Year -->
            <div class="email-footer">
                &copy; {{ date('Y') }} BeetleOps. All rights reserved.<br>
            </div>
        </div>
    </div>
</body>
</html>
