{{-- Daily Sales & Stock: parang isang pahina ng notebook ng botika (Branded | Generic | Grocery) --}}
@php
    $peso = fn ($n) => '₱' . number_format($n, 2);
    $cols = $d['columns'];
@endphp

{{-- ===== 1. Pahina ng logbook: bawat benta ay isang linya, hinati sa Branded | Generic | Grocery ===== --}}
<div class="mt-5 flex justify-end gap-2 print:hidden">
    <button type="button" onclick="window.print()" class="btn-outline btn-sm"><x-icon name="printer" class="h-4 w-4" /> Print this page</button>
</div>
<div id="logbook-page" class="mt-3 overflow-hidden rounded-xl border border-line bg-[#fffdf7] shadow-[0_4px_16px_rgba(0,0,0,.06)] print:border-0 print:shadow-none">
    <div class="border-b-2 border-[#e9b4b4] px-6 pb-3 pt-6 text-center">
        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">De Guzman Drugmart • Daily Sales</p>
        <h2 class="mt-1 font-display text-[30px] font-bold text-ink">{{ $d['day']->format('F j, Y') }}</h2>
    </div>

    <div class="grid {{ $cols->count() > 1 ? 'md:grid-cols-3' : '' }}" style="background-image: repeating-linear-gradient(to bottom, transparent 0 31px, #dbe7f3 31px 32px);">
        @foreach ($cols as $key => $c)
            <section class="flex min-h-[320px] flex-col {{ ! $loop->first ? 'md:border-l md:border-[#e9b4b4]' : '' }}">
                <h3 class="flex h-8 items-center justify-center font-display text-lg font-bold text-ink">{{ $c['label'] }}</h3>
                <ol class="flex-1">
                    @forelse ($c['entries'] as $e)
                        <li class="flex h-8 items-center justify-between gap-3 px-5 text-[15px]" title="{{ \Illuminate\Support\Carbon::parse($e->sold_at)->format('h:i A') }} • #{{ $e->invoice_no }}">
                            <span class="min-w-0 truncate text-ink">{{ $e->product_name }}@if ($e->qty > 1) <span class="text-sm text-muted">×{{ $e->qty }}</span>@endif</span>
                            <span class="shrink-0 font-semibold tabular-nums text-ink">{{ rtrim(rtrim(number_format($e->net, 2, '.', ','), '0'), '.') }}</span>
                        </li>
                    @empty
                        <li class="flex h-8 items-center justify-center px-5 text-sm text-muted">—</li>
                    @endforelse
                </ol>
            </section>
        @endforeach
    </div>

    {{-- Kabuuan sa ibaba, gaya ng sa papel --}}
    <div class="flex justify-end border-t-2 border-[#e9b4b4] px-6 py-5">
        <div class="min-w-[280px] font-display text-[17px]">
            @foreach ($cols as $c)
                <div class="flex justify-between gap-10 py-0.5"><span class="text-ink">{{ $c['label'] }} -</span><span class="font-semibold tabular-nums text-ink">{{ number_format($c['total'], 2) }}</span></div>
            @endforeach
            <div class="mt-1.5 flex justify-between gap-10 border-t-2 border-ink pt-1.5 text-xl font-bold"><span class="text-ink">Total</span><span class="tabular-nums text-brand">{{ $peso($d['total']) }}</span></div>
        </div>
    </div>
</div>

{{-- ===== 2. Stock ng lahat ng produkto sa araw na ito ===== --}}
<div class="card mt-5 overflow-hidden print:hidden">
    {{-- Petsa sa itaas, gaya ng sa papel --}}
    <div class="flex flex-wrap items-end justify-between gap-3 border-b-2 border-brand/30 px-6 pb-4 pt-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Stock • {{ $d['day']->format('l') }}</p>
            <h2 class="font-display text-[22px] font-bold leading-tight text-brand-dark">Stock for {{ $d['day']->format('F j, Y') }}</h2>
        </div>
        <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-brand/30 bg-mint-2 px-2.5 py-1 text-brand-dark"><span class="h-2 w-2 rounded-full bg-brand"></span> Sold today</span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-warn-line bg-warn-bg px-2.5 py-1 text-warn"><span class="h-2 w-2 rounded-full bg-warn"></span> Low stock / Expiring soon</span>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-danger-line bg-danger-bg px-2.5 py-1 text-danger"><span class="h-2 w-2 rounded-full bg-danger"></span> Expired</span>
        </div>
    </div>

    {{-- Tatlong column --}}
    <div class="grid divide-y divide-mint-2 {{ $cols->count() > 1 ? 'lg:grid-cols-3 lg:divide-x lg:divide-y-0' : '' }}">
        @foreach ($cols as $key => $c)
            <section class="flex flex-col">
                <div class="flex items-baseline justify-between px-5 pb-2 pt-5">
                    <h3 class="font-display text-lg font-bold text-ink underline decoration-brand/40 decoration-2 underline-offset-4">{{ $c['label'] }}</h3>
                    <span class="text-[11px] font-semibold text-muted">{{ $c['products']->where('sold', '>', 0)->count() }} sold • {{ $c['products']->count() }} items</span>
                </div>
                <div class="grid grid-cols-[1fr_auto_auto] gap-x-3 border-b border-ink/15 px-5 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-muted">
                    <span>Item</span><span class="w-12 text-center">Stock</span><span class="w-16 text-right">Sold</span>
                </div>

                <ul class="flex-1">
                    @forelse ($c['products'] as $r)
                        @php
                            $bad = $r->expired || $r->expires_today;
                            $cls = $bad ? 'bg-danger-bg/70 border-l-danger' : ($r->sold > 0 ? 'bg-mint-2/60 border-l-brand' : (($r->low || $r->expiring) ? 'bg-warn-bg/60 border-l-warn' : 'border-l-transparent'));
                        @endphp
                        <li class="grid grid-cols-[1fr_auto_auto] items-center gap-x-4 border-b border-l-4 border-b-mint-2 px-5 py-2 {{ $cls }}" title="Start of day: {{ $r->start }}{{ $r->added ? ' • Added ' . $r->added : '' }}{{ $r->removed ? ' • Removed ' . $r->removed : '' }}">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-snug text-ink">{{ $r->name }}</p>
                                <p class="text-[11px] leading-snug text-muted">
                                    {{ $peso($r->price) }} / {{ $r->unit }} •
                                    @if ($r->expired) <span class="font-bold text-danger">Expired {{ $r->expiration_date->format('M d, Y') }}</span>
                                    @elseif ($r->expires_today) <span class="font-bold text-danger">Expires today</span>
                                    @elseif ($r->expiring) <span class="font-semibold text-warn">Exp. {{ $r->expiration_date->format('M d, Y') }} ({{ $r->days_left }}d)</span>
                                    @else Exp. {{ $r->expiration_date->format('M d, Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="w-12 text-center leading-tight">
                                <span class="font-display text-base font-bold tabular-nums {{ $r->end === 0 ? 'text-danger' : ($r->low ? 'text-warn' : 'text-ink') }}">{{ $r->end }}</span>
                                @if ($r->sold > 0) <span class="block text-[10px] font-bold text-brand-dark">−{{ $r->sold }}</span>
                                @elseif ($r->low) <span class="block text-[10px] font-semibold text-warn">{{ $r->end === 0 ? 'out' : 'low' }}</span> @endif
                                @if ($r->added > 0) <span class="block text-[10px] font-semibold text-brand-dark">+{{ $r->added }}</span> @endif
                            </div>
                            <span class="w-16 text-right text-sm font-bold tabular-nums {{ $r->amount > 0 ? 'text-brand' : 'text-line' }}">{{ $r->amount > 0 ? number_format($r->amount, 2) : '—' }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-muted">No {{ strtolower($c['label']) }} products yet.</li>
                    @endforelse
                </ul>

                <div class="mt-auto flex items-center justify-between border-t-2 border-ink/70 bg-mint-3/60 px-5 py-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-body">{{ $c['label'] }}</span>
                    <span class="font-display text-lg font-bold text-brand-dark">{{ $peso($c['total']) }}</span>
                </div>
            </section>
        @endforeach
    </div>

    <p class="border-t border-mint-2 bg-mint-3/40 px-6 py-3 text-xs text-body">Every sale (Branded, Generic or Grocery) automatically lowers the stock here. <b>Stock</b> = what's left at the end of the day; <b>−</b> = sold that day.</p>
</div>
</div>
