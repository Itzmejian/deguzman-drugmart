<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#ebfef1;font-family:Arial,Helvetica,sans-serif;color:#0f1f17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ebfef1;padding:32px 12px;">
  <tr><td align="center">
    <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;width:100%;background:#ffffff;border:1px solid #bccabe;border-radius:12px;overflow:hidden;">
      <tr><td style="background:#006a41;height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr><td style="padding:28px 32px 8px;">
        <p style="margin:0;font-size:13px;font-weight:bold;letter-spacing:1px;color:#006a41;">DE GUZMAN DRUGMART</p>
        <h1 style="margin:12px 0 8px;font-size:22px;color:#0f1f17;">New staff account</h1>
        <p style="margin:0;font-size:15px;line-height:22px;color:#3d4a41;">Hi {{ $admin->name }}, a new staff (cashier) account was created:</p>
      </td></tr>
      <tr><td style="padding:16px 32px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e5f8eb;border:1px solid #bccabe;border-radius:10px;">
          <tr><td style="padding:14px 18px;font-size:14px;line-height:22px;color:#0f1f17;">
            <strong>Name:</strong> {{ $staff->name }}<br>
            <strong>Email:</strong> {{ $staff->email }}<br>
            <strong>Created:</strong> {{ $staff->created_at->format('M d, Y • h:i A') }}
          </td></tr>
        </table>
      </td></tr>
      <tr><td align="center" style="padding:8px 32px 12px;">
        <a href="{{ route('users.index') }}" style="display:inline-block;background:#00945c;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 26px;border-radius:8px;">Open Users</a>
      </td></tr>
      <tr><td style="padding:0 32px 28px;">
        <p style="margin:0;font-size:13px;line-height:20px;color:#6d7a70;">They get in after entering the code sent to their own email. If you don't know this person, open <strong>Users</strong> and remove the account.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
