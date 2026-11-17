@php
    $peso = fn ($n) => '₱' . number_format($n, 2);
    $colors = ['branded' => '#00945c', 'generic' => '#2a78d6', 'grocery' => '#eb6834'];
    $alerts = $d['counts']['low'] + $d['counts']['expiring'] + $d['counts']['expired'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#ebfef1;font-family:Arial,Helvetica,sans-serif;color:#0f1f17;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ebfef1;padding:32px 12px;">
  <tr><td align="center">
    <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="max-width:520px;width:100%;background:#ffffff;border:1px solid #bccabe;border-radius:12px;overflow:hidden;">
      <tr><td style="background:#006a41;height:6px;font-size:0;line-height:0;">&nbsp;</td></tr>
      <tr><td style="padding:28px 32px 8px;">
        <p style="margin:0;font-size:13px;font-weight:bold;letter-spacing:1px;color:#006a41;">DE GUZMAN DRUGMART • DAILY SALES</p>
        <h1 style="margin:10px 0 4px;font-size:22px;color:#0f1f17;">{{ $day->format('l, F j, Y') }}</h1>
        <p style="margin:0;font-size:14px;color:#3d4a41;">Hi {{ $user->name }}, here's today's summary.</p>
      </td></tr>

      <tr><td style="padding:16px 32px 4px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e5f8eb;border:1px solid #bccabe;border-radius:10px;">
          <tr>
            <td style="padding:16px 18px;">
              <p style="margin:0;font-size:12px;font-weight:bold;color:#3d4a41;text-transform:uppercase;letter-spacing:1px;">Total sales</p>
              <p style="margin:4px 0 0;font-size:28px;font-weight:bold;color:#006a41;">{{ $peso($d['total']) }}</p>
            </td>
            <td align="right" style="padding:16px 18px;font-size:14px;color:#3d4a41;">{{ $d['orders'] }} {{ $d['orders'] === 1 ? 'sale' : 'sales' }}</td>
          </tr>
        </table>
      </td></tr>

      @foreach ($d['columns'] as $key => $c)
      @php $sold = $c['products']->where('sold', '>', 0)->sortByDesc('amount'); @endphp
      <tr><td style="padding:16px 32px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dfe9e2;border-radius:10px;border-collapse:separate;overflow:hidden;font-size:13px;">
          <tr>
            <td colspan="3" style="padding:11px 14px;background:#f5fbf7;border-bottom:1px solid #dfe9e2;">
              <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $colors[$key] }};margin-right:8px;"></span><strong style="font-size:14px;">{{ $c['label'] }}</strong>
              <span style="color:#6d7a70;"> • {{ $c['qty'] }} sold</span>
            </td>
            <td align="right" style="padding:11px 14px;background:#f5fbf7;border-bottom:1px solid #dfe9e2;font-weight:bold;font-size:14px;">{{ $peso($c['total']) }}</td>
          </tr>
          @if ($sold->isNotEmpty())
            <tr style="color:#6d7a70;font-size:11px;text-transform:uppercase;letter-spacing:.5px;">
              <td style="padding:8px 14px 4px;">Item</td>
              <td align="center" style="padding:8px 6px 4px;">Sold</td>
              <td align="center" style="padding:8px 6px 4px;">Stock left</td>
              <td align="right" style="padding:8px 14px 4px;">Amount</td>
            </tr>
            @foreach ($sold as $r)
              <tr>
                <td style="padding:7px 14px;border-top:1px solid #eef4f0;">{{ $r->name }}</td>
                <td align="center" style="padding:7px 6px;border-top:1px solid #eef4f0;">{{ $r->sold }}</td>
                <td align="center" style="padding:7px 6px;border-top:1px solid #eef4f0;{{ $r->low ? 'color:#b45309;font-weight:bold;' : '' }}">{{ $r->closing }}</td>
                <td align="right" style="padding:7px 14px;border-top:1px solid #eef4f0;font-weight:bold;">{{ $peso($r->amount) }}</td>
              </tr>
            @endforeach
          @else
            <tr><td colspan="4" style="padding:10px 14px;color:#6d7a70;">No {{ strtolower($c['label']) }} sales today.</td></tr>
          @endif
        </table>
      </td></tr>
      @endforeach

      @if ($alerts > 0)
      <tr><td style="padding:16px 32px 4px;">
        <p style="margin:0;padding:12px 14px;background:#fff7e6;border:1px solid #f2d7a6;border-radius:8px;font-size:13px;color:#8a5a00;">
          <strong>Stock alerts:</strong>
          {{ $d['counts']['low'] }} low stock • {{ $d['counts']['expiring'] }} expiring soon • {{ $d['counts']['expired'] }} expired
        </p>
      </td></tr>
      @endif

      <tr><td align="center" style="padding:20px 32px 12px;">
        <a href="{{ route('logbook.index', ['day' => $day->toDateString()]) }}" style="display:inline-block;background:#00945c;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:12px 26px;border-radius:8px;">Open Daily Report</a>
      </td></tr>
      <tr><td style="padding:0 32px 26px;">
        <p style="margin:0;font-size:12px;line-height:18px;color:#6d7a70;">Sent automatically at 10:00 PM. Turn it off in Settings › Email notification digest.</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
