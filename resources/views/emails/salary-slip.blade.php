<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #111111;
        }
    </style>
</head>
<body>
    <p>Dear <strong>{{ $userName }}</strong>,</p>

    <p>I hope you are doing well.</p>

    <p>Please find attached your <strong>Salary Slip and Hours Summary for {{ $monthName }} {{ $year }}.</strong> This document includes details of your salary along with your recorded working hours for the mentioned period.</p>

    <p>If you notice any discrepancies or have any questions regarding the attached information, please feel free to reach out to the HR department.</p>

    <p style="margin-top: 20px;">
        Best regards,<br><br>
        <strong>Mahrukh Shaukat</strong><br>
        <strong>HR Manager</strong><br>
        <strong>BeetleOPS</strong>
    </p>
</body>
</html>
