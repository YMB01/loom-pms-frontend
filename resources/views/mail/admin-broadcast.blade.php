<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $adminMessage->title }}</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #0d1117; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="margin: 0 0 16px; font-size: 18px; font-weight: 600;">{{ $adminMessage->title }}</p>
    <div style="white-space: pre-wrap; font-size: 14px;">{{ $adminMessage->body }}</div>
    <p style="margin-top: 24px; font-size: 12px; color: #64748b;">This message was sent from Loom PMS system administration.</p>
</body>
</html>
