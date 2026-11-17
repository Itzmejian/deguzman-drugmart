@extends('layouts.app')
@section('title', 'Analytics · Reports')
@section('crumb-parent', 'Overview')
@section('crumb-parent-url', route('dashboard'))
@section('crumb', 'Reports & Analytics')

@php
    $peso = fn ($n) => '₱' . number_format($n, 2);
    $colors = ['branded' => '#00945c', 'generic' => '#2a78d6', 'grocery' => '#eb6834'];
    $prevLabel = ['today' => 'yesterday', 'week' => 'previous 7 days', 'month' => 'last month', 'year' => 'last year'][$period];
    $delta = function ($now, $before) {
        if ($before <= 0) return $now > 0 ? ['new', 'up'] : ['—', 'flat'];
        $pct = ($now - $before) / $before * 100;
        return [($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%', $pct > 0.05 ? 'up' : ($pct < -0.05 ? 'down' : 'flat')];
    };
    $range = match ($period) {
        'today' => $from->format('l, F j, Y'),
        'year'  => 'January – December ' . $from->year,
        'month' => $from->format('F Y'),
        default => $from->format('M j') . ' – ' . $to->format('M j, Y'),
    };
@endphp

@section('content')
<div class="mx-auto max-w-[1100px]" style="--s-branded: {{ $colors['branded'] }}; --s-generic: {{ $colors['generic'] }}; --s-grocery: {{ $colors['grocery'] }};">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <span class="pill-green font-mono text-xs uppercase tracking-wider">Financial Summary</span>
            <h1 class="mt-2 font-display text-[32px] font-bold leading-tight text-ink">Reports</h1>
            <p class="mt-1 text-sm text-body">How Branded, Generic and Grocery are selling — {{ $range }}.</p>
            @include('reports._tabs')
        </div>

        {{-- Period: Today / This Week / This Month / This Year --}}
        <nav class="inline-flex rounded-xl border border-mint-2 bg-white p-1 shadow-sm" aria-label="Period">
            @foreach ($periods as $k => $label)
                <a href="{{ route('reports.analytics', ['period' => $k]) }}" @if($period === $k) aria-current="page" @endif
                   class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $period === $k ? 'bg-brand text-white shadow' : 'text-body hover:bg-mint' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Sagot agad: alin ang pinakamabenta --}}
    @if ($grand > 0)
        @php arsort($totals); $top1 = array_key_first($totals); $share1 = $totals[$top1] / $grand * 100; @endphp
        <div class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-brand/25 bg-white px-5 py-4 shadow-sm">
            <span class="grid h-10 w-10 place-items-center rounded-full text-white" style="background: var(--s-{{ $top1 }})"><x-icon name="trending" class="h-5 w-5" /></span>
            <p class="text-[15px] text-ink"><b>{{ $types[$top1] }}</b> sells the most {{ strtolower($periods[$period]) }} —
                <b>{{ number_format($share1, 0) }}%</b> of all sales ({{ $peso($totals[$top1]) }}).</p>
        </div>
        @php ksort($totals); $totals = array_replace(array_flip(array_keys($types)), $totals); @endphp
    @endif

    {{-- KPI tiles --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php [$d, $dir] = $delta($grand, $prevGrand); @endphp
        <div class="card p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-muted">Total Sales</p>
            <p class="mt-2 font-display text-[28px] font-bold leading-none text-ink">{{ $peso($grand) }}</p>
            <p class="mt-3 flex items-center gap-2 text-xs text-body">
                <span class="rounded-full px-2 py-0.5 font-semibold {{ $dir === 'up' ? 'bg-mint-2 text-brand-dark' : ($dir === 'down' ? 'bg-danger-bg text-danger' : 'bg-mint-3 text-muted') }}">{{ $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '') }} {{ $d }}</span>
                vs {{ $prevLabel }} • {{ $orders }} {{ $orders === 1 ? 'order' : 'orders' }}
            </p>
        </div>
        @foreach ($types as $k => $label)
            @php [$d, $dir] = $delta($totals[$k], $prevTotals[$k]); $share = $grand > 0 ? $totals[$k] / $grand * 100 : 0; @endphp
            <div class="card p-5">
                <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted">
                    <span class="h-2.5 w-2.5 rounded-sm" style="background: var(--s-{{ $k }})"></span> {{ $label }}
                </p>
                <p class="mt-2 font-display text-[24px] font-bold leading-none text-ink">{{ $peso($totals[$k]) }}</p>
                <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-body">
                    <span class="font-semibold text-ink">{{ number_format($share, 0) }}% of sales</span> •
                    {{ number_format($qty[$k]) }} sold •
                    <span class="font-semibold {{ $dir === 'up' ? 'text-brand-dark' : ($dir === 'down' ? 'text-danger' : 'text-muted') }}">{{ $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '') }} {{ $d }}</span>
                </p>
            </div>
        @endforeach
    </div>

    {{-- Main chart: stacked columns over time --}}
    <div class="card mt-5 p-6" x-data="{ table: false }">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-lg font-bold text-ink">Sales over time</h2>
                <p class="text-xs text-muted">{{ ['today' => 'Per hour', 'week' => 'Per day, last 7 days', 'month' => 'Per day', 'year' => 'Per month'][$period] }} • net of Senior/PWD discount</p>
            </div>
            <div class="flex items-center gap-4">
                <ul class="flex flex-wrap items-center gap-4 text-xs font-semibold text-body" aria-label="Legend">
                    @foreach ($types as $k => $label)
                        <li class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" style="background: var(--s-{{ $k }})"></span> {{ $label }}</li>
                    @endforeach
                </ul>
                <button type="button" @click="table = !table" class="btn-outline btn-sm" x-text="table ? 'Show chart' : 'Show table'"></button>
            </div>
        </div>

        <div x-show="!table" class="relative mt-4">
            <div id="salesChart" class="h-[320px] w-full" role="img" aria-label="Stacked column chart of Branded, Generic and Grocery sales"></div>
            <div id="chartTip" class="pointer-events-none absolute z-10 hidden min-w-[170px] rounded-lg border border-mint-2 bg-white px-3 py-2.5 text-xs shadow-lg"></div>
            @if ($grand <= 0)
                <div class="absolute inset-0 grid place-items-center">
                    <p class="rounded-lg bg-white/90 px-4 py-2 text-sm text-muted">No sales yet for {{ strtolower($periods[$period]) }}.</p>
                </div>
            @endif
        </div>

        <div x-show="table" x-cloak class="mt-4 max-h-[360px] overflow-auto rounded-lg border border-mint-2">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-mint-3"><tr>
                    <th class="th">{{ ['today' => 'Hour', 'year' => 'Month'][$period] ?? 'Day' }}</th>
                    @foreach ($types as $label) <th class="th text-right">{{ $label }}</th> @endforeach
                    <th class="th text-right">Total</th>
                </tr></thead>
                <tbody class="divide-y divide-mint-2">
                    @foreach ($series as $row)
                        @php $t = $row['branded'] + $row['generic'] + $row['grocery']; @endphp
                        <tr class="{{ $t > 0 ? '' : 'text-muted' }}">
                            <td class="px-4 py-2">{{ $row['label'] }}</td>
                            @foreach ($types as $k => $label) <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row[$k], 2) }}</td> @endforeach
                            <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ number_format($t, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_1.2fr]">
        {{-- Share of sales: tall segmented bar (mas madaling basahin kaysa pie) --}}
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-ink">Share of sales</h2>
            <p class="text-xs text-muted">Which type brings in the most money</p>
            @if ($grand > 0)
                <div class="mt-5 flex h-14 w-full gap-[2px] overflow-hidden rounded-lg">
                    @foreach ($types as $k => $label)
                        @php $share = $totals[$k] / $grand * 100; @endphp
                        @if ($share > 0)
                            <div class="flex items-center justify-center text-sm font-bold text-white" style="width: {{ $share }}%; background: var(--s-{{ $k }})" title="{{ $label }}: {{ $peso($totals[$k]) }} ({{ number_format($share, 1) }}%)">
                                @if ($share >= 9) {{ number_format($share, 0) }}% @endif
                            </div>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-5 space-y-2.5">
                    @foreach ($types as $k => $label)
                        <li class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-ink"><span class="h-3 w-3 rounded-sm" style="background: var(--s-{{ $k }})"></span> {{ $label }}</span>
                            <span class="tabular-nums text-body"><span class="font-semibold text-ink">{{ $peso($totals[$k]) }}</span> • {{ number_format($totals[$k] / $grand * 100, 1) }}%</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-8 text-center text-sm text-muted">No sales yet for {{ strtolower($periods[$period]) }}.</p>
            @endif
        </div>

        {{-- Top 5 products: ranked bars --}}
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold text-ink">Top 5 best sellers</h2>
            <p class="text-xs text-muted">By sales amount, {{ strtolower($periods[$period]) }}</p>
            @php $max = $top->max('net') ?: 1; @endphp
            <ol class="mt-5 space-y-3.5">
                @forelse ($top as $i => $p)
                    <li>
                        <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate text-ink"><span class="mr-1.5 font-bold text-muted">{{ $i + 1 }}.</span>{{ $p['name'] }}
                                <span class="ml-1 text-[11px] text-muted">{{ $types[$p['type']] }} • {{ $p['qty'] }} sold</span></span>
                            <span class="shrink-0 font-semibold tabular-nums text-ink">{{ $peso($p['net']) }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-mint-3"><div class="h-2.5 rounded-full bg-brand" style="width: {{ max(2, $p['net'] / $max * 100) }}%"></div></div>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-muted">No sales yet for {{ strtolower($periods[$period]) }}.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>

<script>
(function () {
    const data = @json($series);
    const keys = ['branded', 'generic', 'grocery'];
    const names = { branded: 'Branded', generic: 'Generic', grocery: 'Grocery' };
    const color = @json($colors);
    const el = document.getElementById('salesChart');
    const tip = document.getElementById('chartTip');
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const short = (n) => n >= 1000 ? '₱' + (n / 1000).toFixed(n >= 10000 ? 0 : 1).replace(/\.0$/, '') + 'k' : '₱' + Math.round(n);
    const NS = 'http://www.w3.org/2000/svg';
    const mk = (tag, attrs) => { const n = document.createElementNS(NS, tag); for (const k in attrs) n.setAttribute(k, attrs[k]); return n; };

    function niceMax(v) {
        if (v <= 0) return 100;
        const p = Math.pow(10, Math.floor(Math.log10(v)));
        for (const m of [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10]) if (m * p >= v) return m * p;
        return 10 * p;
    }

    function draw() {
        const W = el.clientWidth, H = el.clientHeight;
        if (!W) return;
        el.innerHTML = '';
        const m = { t: 10, r: 8, b: 30, l: 58 };
        const iw = W - m.l - m.r, ih = H - m.t - m.b;
        const totals = data.map(d => keys.reduce((s, k) => s + d[k], 0));
        const max = niceMax(Math.max(...totals));
        const y = (v) => m.t + ih - (v / max) * ih;
        const svg = mk('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}` });

        // grid + y labels (recessive)
        for (let i = 0; i <= 4; i++) {
            const v = max / 4 * i, yy = y(v);
            svg.appendChild(mk('line', { x1: m.l, x2: W - m.r, y1: yy, y2: yy, stroke: i === 0 ? '#bccabe' : '#e5efe8', 'stroke-width': 1 }));
            const t = mk('text', { x: m.l - 8, y: yy + 4, 'text-anchor': 'end', 'font-size': 11, fill: '#6d7a70' }); t.textContent = short(v); svg.appendChild(t);
        }

        const step = iw / data.length;
        const bw = Math.max(4, Math.min(44, step * 0.62));
        const every = data.length > 20 ? 5 : (data.length > 12 ? 2 : 1);

        data.forEach((d, i) => {
            const cx = m.l + step * i + step / 2, x = cx - bw / 2;
            let acc = 0;
            const segs = keys.filter(k => d[k] > 0);
            segs.forEach((k, j) => {
                const y0 = y(acc), y1 = y(acc + d[k]);
                const top = j === segs.length - 1;
                const h = Math.max(0, y0 - y1 - (j > 0 ? 2 : 0)); // 2px surface gap between segments
                const yy = y1;
                const r = top ? Math.min(4, bw / 2, h) : 0;
                const path = r > 0
                    ? `M${x},${yy + h} V${yy + r} Q${x},${yy} ${x + r},${yy} H${x + bw - r} Q${x + bw},${yy} ${x + bw},${yy + r} V${yy + h} Z`
                    : `M${x},${yy + h} V${yy} H${x + bw} V${yy + h} Z`;
                svg.appendChild(mk('path', { d: path, fill: color[k] }));
                acc += d[k];
            });

            if (i % every === 0 || i === data.length - 1) {
                const t = mk('text', { x: cx, y: H - 10, 'text-anchor': 'middle', 'font-size': 11, fill: '#525f57' }); t.textContent = d.label; svg.appendChild(t);
            }

            // hit target: buong column (mas malaki sa mark)
            const hit = mk('rect', { x: m.l + step * i, y: m.t, width: step, height: ih, fill: 'transparent', style: 'cursor:pointer' });
            hit.addEventListener('mouseenter', () => {
                const tot = totals[i];
                tip.innerHTML = `<p class="mb-1.5 font-bold text-ink">${d.label}</p>` +
                    keys.map(k => `<p class="flex items-center justify-between gap-4 py-0.5"><span class="flex items-center gap-1.5 text-body"><span style="background:${color[k]}" class="inline-block h-2.5 w-2.5 rounded-sm"></span>${names[k]}</span><span class="font-semibold tabular-nums text-ink">${peso(d[k])}</span></p>`).join('') +
                    `<p class="mt-1.5 flex justify-between gap-4 border-t border-mint-2 pt-1.5 font-bold text-ink"><span>Total</span><span class="tabular-nums">${peso(tot)}</span></p>`;
                tip.classList.remove('hidden');
                const left = Math.min(W - tip.offsetWidth, Math.max(0, cx + 14 + (cx > W * 0.65 ? -tip.offsetWidth - 28 : 0)));
                tip.style.left = left + 'px';
                tip.style.top = Math.max(0, y(tot) - 20) + 'px';
                hit.setAttribute('fill', 'rgba(0,148,92,0.06)');
            });
            hit.addEventListener('mouseleave', () => { tip.classList.add('hidden'); hit.setAttribute('fill', 'transparent'); });
            svg.appendChild(hit);
        });

        el.appendChild(svg);
    }

    draw();
    new ResizeObserver(draw).observe(el);
})();
</script>
@endsection
