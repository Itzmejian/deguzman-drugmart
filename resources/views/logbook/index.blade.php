@extends('layouts.app')
@section('title', 'Daily Report')
@section('crumb-parent', 'Reports')
@section('crumb-parent-url', route('logbook.index'))
@section('crumb', 'Daily Report')

@php
    $peso = fn ($n) => '₱' . number_format($n, 2);
    $num = fn ($n) => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
    $day = $d['day'];
    $prev = $day->copy()->subDay();
    $typeCls = ['branded' => 'border-brand/30 bg-mint-2 text-brand-dark', 'generic' => 'border-[#2a78d6]/30 bg-[#eaf2fc] text-[#1c5cab]', 'grocery' => 'border-warn-line bg-warn-bg text-warn'];
@endphp

@section('content')
<div class="mx-auto max-w-[1100px]">
    {{-- Heading --}}
    <div class="flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <span class="pill-green font-mono text-xs uppercase tracking-wider">Reports</span>
            <h1 class="mt-2 font-display text-[32px] font-bold leading-tight text-ink">Daily Report</h1>
            <p class="mt-1 text-sm text-body">Branded, Generic and Grocery — what was sold and how much stock is left. Saved automatically every day.</p>
            @include('partials.report-tabs')
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="window.print()" class="btn-outline"><x-icon name="printer" class="h-4 w-4" /> Print</button>
            <a href="{{ route('logbook.export', ['day' => $day->toDateString()]) }}" class="btn-primary"><x-icon name="download" class="h-4 w-4" /> Export</a>
        </div>
    </div>

    {{-- Date bar --}}
    <form method="GET" action="{{ route('logbook.index') }}" class="card mt-6 flex flex-wrap items-center justify-between gap-4 p-4 print:hidden">
        <div class="flex items-center gap-2">
            <a href="{{ route('logbook.index', ['day' => $prev->toDateString()]) }}" class="btn-outline px-3!" title="Previous day">‹ <span class="hidden sm:inline">{{ $prev->format('M j') }}</span></a>
            <input name="day" type="date" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}" onchange="this.form.submit()" class="input w-auto py-2.5 text-sm" aria-label="Report date">
            @if ($d['isToday'])
                <span class="btn-outline cursor-not-allowed px-3! opacity-40">›</span>
            @else
                <a href="{{ route('logbook.index', ['day' => $day->copy()->addDay()->toDateString()]) }}" class="btn-outline px-3!" title="Next day">›</a>
                <a href="{{ route('logbook.index') }}" class="btn-soft btn-sm">Today</a>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
            @if ($d['isToday'])
                <span class="inline-flex items-center gap-1.5 rounded-full border border-brand/30 bg-mint-2 px-3 py-1.5 text-brand-dark"><span class="h-2 w-2 animate-pulse rounded-full bg-brand"></span> Live — updates with every sale</span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-mint-3 px-3 py-1.5 text-body"><x-icon name="lock" class="h-3.5 w-3.5" /> Saved record for {{ $day->format('M j, Y') }}</span>
            @endif
            <span class="rounded-full border border-line bg-white px-3 py-1.5 text-ink">{{ $d['orders'] }} {{ $d['orders'] === 1 ? 'sale' : 'sales' }} • {{ $peso($d['total']) }}</span>
        </div>
    </form>

    <div id="logbook-page">
    {{-- ===== 1. Buod ng benta: Branded / Generic / Grocery ===== --}}
    <p class="hidden print:block print:mb-3 print:text-center print:font-display print:text-xl print:font-bold">Daily Report — {{ $day->format('F j, Y') }}</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($d['columns'] as $key => $c)
            <div class="card p-5">
                <span class="rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $typeCls[$key] }}">{{ $c['label'] }}</span>
                <p class="mt-3 font-display text-[26px] font-bold leading-none text-ink">{{ $peso($c['total']) }}</p>
                <p class="mt-1.5 text-xs text-muted">{{ $c['qty'] }} {{ $c['qty'] === 1 ? 'item' : 'items' }} sold</p>
            </div>
        @endforeach
        <div class="card border-brand/30 p-5 ring-1 ring-brand/10">
            <span class="rounded-full bg-brand px-2.5 py-0.5 text-xs font-bold text-white">Total</span>
            <p class="mt-3 font-display text-[26px] font-bold leading-none text-brand">{{ $peso($d['total']) }}</p>
            <p class="mt-1.5 text-xs text-muted">{{ $d['orders'] }} {{ $d['orders'] === 1 ? 'sale' : 'sales' }}</p>
        </div>
    </div>

    {{-- ===== 2. Stock: hiwalay ang Branded, Generic at Grocery (gaya ng logbook) ===== --}}
    <div x-data="{ show: 'all' }">
        <div class="card mt-6 flex flex-wrap items-center justify-between gap-3 px-6 py-4 print:border-0 print:shadow-none">
            <div>
                <h2 class="font-display text-xl font-bold text-ink">Stock — {{ $day->format('F j, Y') }}</h2>
                <p class="text-xs text-muted">“Last stock” is what was left at the end of {{ $prev->format('M j') }}. Every sale lowers “Stock left”.</p>
            </div>
            <nav class="inline-flex flex-wrap gap-1 rounded-xl border border-mint-2 bg-mint-3 p-1 print:hidden" aria-label="Show type">
                <button type="button" @click="show = 'all'" :class="show === 'all' ? 'bg-white text-ink shadow' : 'text-body hover:bg-white/60'" class="rounded-lg px-3.5 py-1.5 text-sm font-semibold">All</button>
                @foreach ($d['columns'] as $key => $c)
                    <button type="button" @click="show = '{{ $key }}'" :class="show === '{{ $key }}' ? 'bg-white text-ink shadow' : 'text-body hover:bg-white/60'" class="rounded-lg px-3.5 py-1.5 text-sm font-semibold">{{ $c['label'] }}</button>
                @endforeach
            </nav>
                <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand/30 bg-mint-2 px-2.5 py-1 text-brand-dark"><span class="h-2 w-2 rounded-full bg-brand"></span> Sold ({{ $d['counts']['sold'] }})</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-warn-line bg-warn-bg px-2.5 py-1 text-warn"><span class="h-2 w-2 rounded-full bg-warn"></span> Low / Expiring ({{ $d['counts']['low'] + $d['counts']['expiring'] }})</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-danger-line bg-danger-bg px-2.5 py-1 text-danger"><span class="h-2 w-2 rounded-full bg-danger"></span> Expired ({{ $d['counts']['expired'] }})</span>
                </div>
        </div>

        @foreach ($d['columns'] as $key => $c)
            <section x-show="show === 'all' || show === '{{ $key }}'" class="card mt-5 overflow-hidden print:break-inside-avoid print:shadow-none">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-mint-2 px-6 py-4">
                    <h3 class="flex items-center gap-3 font-display text-lg font-bold text-ink">
                        <span class="rounded-full border px-3 py-1 text-sm font-bold {{ $typeCls[$key] }}">{{ $c['label'] }}</span>
                        <span class="text-sm font-medium text-muted">{{ $c['products']->count() }} {{ $c['products']->count() === 1 ? 'product' : 'products' }}</span>
                    </h3>
                    <p class="text-sm text-body">{{ $c['qty'] }} sold • <b class="font-display text-base text-ink">{{ $peso($c['total']) }}</b></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-mint-3">
                        <tr>
                            <th class="th px-6">Product</th>
                            <th class="th text-right">Price</th>
                            <th class="th">Expiration</th>
                            <th class="th text-center">Last stock<br><span class="font-medium normal-case tracking-normal">{{ $prev->format('M j') }}</span></th>
                            <th class="th text-center">Sold</th>
                            <th class="th text-center">Added / Removed</th>
                            <th class="th text-center">Stock left<br><span class="font-medium normal-case tracking-normal">{{ $day->format('M j') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mint-2">
                    @forelse ($c['products'] as $r)
                        @php
                            $cls = $r->expired ? 'bg-danger-bg/60 border-l-danger' : ($r->sold > 0 ? 'bg-mint-2/50 border-l-brand' : (($r->low || $r->expiring) ? 'bg-warn-bg/50 border-l-warn' : 'border-l-transparent'));
                        @endphp
                        <tr class="border-l-4 {{ $cls }}">
                            <td class="px-6 py-2.5">
                                <p class="font-semibold text-ink">{{ $r->name }} @if ($r->rx) <span class="ml-1 rounded border border-warn-line bg-warn-bg px-1.5 text-[10px] font-bold text-warn">Rx</span> @endif</p>
                                <p class="text-[11px] text-muted">per {{ $r->unit }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 text-right tabular-nums text-ink">{{ $peso($r->price) }}</td>
                            <td class="whitespace-nowrap px-4">
                                <span class="{{ $r->expiration_date ? 'text-ink' : 'text-muted' }}">{{ $r->expiration_date?->format('M d, Y') ?? 'No expiry' }}</span>
                                @if ($r->expired) <span class="block text-[11px] font-bold text-danger">Expired</span>
                                @elseif ($r->expiring) <span class="block text-[11px] font-semibold text-warn">{{ $r->days_left }} {{ $r->days_left === 1 ? 'day' : 'days' }} left</span> @endif
                            </td>
                            <td class="px-4 text-center tabular-nums text-body">{{ $r->opening }}</td>
                            <td class="px-4 text-center">
                                @if ($r->sold > 0) <span class="inline-block whitespace-nowrap rounded-full bg-brand px-2.5 py-0.5 text-xs font-bold text-white">−{{ $r->sold }}</span> @else <span class="text-muted">—</span> @endif
                            </td>
                            <td class="whitespace-nowrap px-4 text-center text-xs tabular-nums">
                                @if ($r->added) <span class="font-semibold text-brand-dark">+{{ $r->added }}</span> @endif
                                @if ($r->removed) <span class="font-semibold text-danger">−{{ $r->removed }}</span> @endif
                                @if (! $r->added && ! $r->removed) <span class="text-muted">—</span> @endif
                            </td>
                            <td class="px-4 text-center">
                                <span class="font-display text-lg font-bold tabular-nums {{ $r->closing === 0 ? 'text-danger' : ($r->low ? 'text-warn' : 'text-ink') }}">{{ $r->closing }}</span>
                                @if ($r->low) <span class="block text-[10px] font-semibold text-warn">{{ $r->closing === 0 ? 'Out of stock' : 'Low stock' }}</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-4 text-sm text-muted">No {{ strtolower($c['label']) }} products yet.</td></tr>
                    @endforelse
                    </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
    </div>
</div>
@endsection
