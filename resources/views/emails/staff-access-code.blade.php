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
        <h1 style="margin:12px 0 8px;font-size:22px;color:#0f1f17;">{{ $user->isPending() ? 'Your verification code' : 'Your new password code' }}</h1>
        <p style="margin:0;font-size:15px;line-height:22px;color:#3d4a41;">Hi {{ $user->name }}, @if ($user->isPending())welcome to De Guzman Drugmart! Here is your verification code. This code is also your <strong>password</strong>:@else you asked for a new code. This is now your <strong>password</strong> (your old code no longer works):@endif</p>
      </td></tr>
      <tr><td align="center" style="padding:20px 32px;">
        <div style="display:inline-block;background:#e5f8eb;border:1px solid #bccabe;border-radius:10px;padding:16px 28px;font-size:30px;font-weight:bold;letter-spacing:6px;color:#006a41;font-family:'Courier New',monospace;">{{ $code }}</div>
      </td></tr>
      <tr><td align="center" style="padding:4px 32px 16px;">
        <a href="{{ $user->isPending() ? route('staff.verify', ['email' => $user->email]) : route('login') }}" style="display:inline-block;background:#00945c;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:13px 28px;border-radius:8px;">Open De Guzman Drugmart</a>
      </td></tr>
      <tr><td style="padding:0 32px 28px;">
        @if ($user->isPending())<p style="margin:0 0 10px;font-size:14px;line-height:21px;color:#3d4a41;">Tap the button, enter the code, and you'll go straight to the dashboard.</p>@endif
        <p style="margin:0 0 10px;font-size:14px;line-height:21px;color:#3d4a41;">Next time, log in with <strong>{{ $user->email }}</strong> and this same code. Need another one? Go to Settings › Email me a new code.</p>
        <p style="margin:0;font-size:13px;line-height:20px;color:#6d7a70;">@if ($user->isPending())The code expires in {{ \App\Http\Controllers\Auth\StaffRequestController::CODE_HOURS }} hours if not used. @endif Never share it. {{ $user->isPending() ? "If you didn't create this account, ignore this email." : "If you didn't ask for a new code, tell your administrator." }}</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
