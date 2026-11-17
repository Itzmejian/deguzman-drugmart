@extends('layouts.app')
@section('title', 'Dashboard')
@section('crumb-parent', 'Dashboard')
@section('crumb-parent-url', route('dashboard'))
@section('crumb', 'Overview')

@section('content')
<div class="mx-auto max-w-[1100px]">
    {{-- Heading --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="font-display text-[32px] font-bold leading-tight text-ink">Dashboard</h1>
            <p class="mt-1 text-sm text-body">Here's how your dispensary and inventory are performing today.</p>
        </div>
        <div class="flex items-center gap-2 rounded-lg border border-mint-2 bg-white px-3.5 py-2 text-sm shadow-sm">
            <x-icon name="calendar" class="h-4 w-4 text-brand" />
            <span>Today: <b class="text-ink">{{ now()->format('M d, Y') }}</b> ({{ now()->hour < 18 ? 'Day' : 'Night' }} Shift)</span>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('products.index') }}" class="card group block p-5 transition hover:-translate-y-0.5 hover:border-brand/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-brand" title="View all products">
            <div class="flex items-center justify-between">
                <span class="text-sm text-body">Total Products</span>
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-mint-2 text-brand-dark"><x-icon name="archive" /></span>
            </div>
            <p class="mt-4 font-display text-[38px] font-bold leading-none text-ink">{{ number_format($totalProducts) }}</p>
            <span class="pill-green mt-4"><x-icon name="circle-check" class="h-3 w-3" /> In catalog</span>
            <span class="mt-3 flex items-center gap-1 text-xs font-semibold text-brand-dark opacity-70 transition group-hover:opacity-100">View products <x-icon name="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
        </a>

        <a href="{{ route('products.index', ['filter' => 'low']) }}" class="card group block p-5 transition hover:-translate-y-0.5 hover:border-brand/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-brand" title="View low stock products">
            <div class="flex items-center justify-between">
                <span class="text-sm text-body">Low Stock Alerts</span>
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-warn-bg text-warn"><x-icon name="alert" /></span>
            </div>
            <p class="mt-4 font-display text-[38px] font-bold leading-none text-warn">{{ $lowStockCount }}</p>
            <span class="pill-warn mt-4">Requires Restock</span>
            <span class="mt-3 flex items-center gap-1 text-xs font-semibold text-brand-dark opacity-70 transition group-hover:opacity-100">View low stock <x-icon name="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
        </a>

        <a href="{{ route('products.index', ['filter' => 'expiring']) }}" class="card group block p-5 transition hover:-translate-y-0.5 hover:border-brand/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-brand" title="View products expiring soon">
            <div class="flex items-center justify-between">
                <span class="text-sm text-body">Expiring Soon</span>
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-danger-bg text-danger"><x-icon name="calendar-x" /></span>
            </div>
            <p class="mt-4 font-display text-[38px] font-bold leading-none text-ink">{{ $expiringCount }}</p>
            <span class="pill-red mt-4">Within {{ \App\Models\Setting::get('expiry_warn_days') }} Days</span>
            @if ($expiredCount > 0) <span class="pill-red mt-2 border-danger bg-danger text-white">{{ $expiredCount }} already expired</span> @endif
            <span class="mt-3 flex items-center gap-1 text-xs font-semibold text-brand-dark opacity-70 transition group-hover:opacity-100">View expiring <x-icon name="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
        </a>

        <a href="{{ route('logbook.index') }}" class="card group block border-brand/30 bg-white p-5 ring-1 ring-brand/10 transition hover:-translate-y-0.5 hover:border-brand/50 hover:shadow-md focus-visible:outline-2 focus-visible:outline-brand" title="View today's Daily Report">
            <div class="flex items-center justify-between">
                <span class="text-sm text-body">Today's Sales</span>
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-mint-2 text-brand-dark"><x-icon name="cash" /></span>
            </div>
            <p class="mt-4 font-display text-[38px] font-bold leading-none {{ ($delta ?? 0) < 0 ? 'text-danger' : 'text-brand' }}">₱{{ number_format($todayTotal, 0) }}</p>
            @if (! is_null($delta))
                <span class="{{ $delta < 0 ? 'pill-red' : 'pill-green' }} mt-4"><x-icon name="{{ $delta < 0 ? 'trending-down' : 'trending' }}" class="h-3 w-3" /> {{ $delta > 0 ? '+' : '' }}{{ $delta }}% vs yesterday</span>
            @else
                <span class="pill-green mt-4">{{ $todayCount }} sale{{ $todayCount === 1 ? '' : 's' }} today</span>
            @endif
            <span class="mt-3 flex items-center gap-1 text-xs font-semibold text-brand-dark opacity-70 transition group-hover:opacity-100">View Daily Report <x-icon name="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" /></span>
        </a>
    </div>

    {{-- Quick actions --}}
    <div class="mt-9 flex items-center justify-between">
        <h2 class="font-display text-lg font-bold text-ink">Quick Actions</h2>
        <span class="hidden text-xs text-muted sm:block">Frequently used dispensary actions</span>
    </div>
    <div class="mt-3 grid gap-4 {{ auth()->user()->isAdmin() ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
        @if (auth()->user()->isAdmin())
            <a href="{{ route('products.create') }}" class="btn-primary py-3.5 text-[15px] shadow-md"><x-icon name="plus" class="h-5 w-5" /> Add Product</a>
        @endif
        <a href="{{ route('sales.create') }}" class="btn-outline py-3.5 text-[15px]"><x-icon name="receipt" class="h-5 w-5 text-brand-dark" /> Record Sale (POS)</a>
        <a href="{{ route('logbook.index') }}" class="btn-outline py-3.5 text-[15px]"><x-icon name="file" class="h-5 w-5 text-brand-dark" /> Today's Report</a>
    </div>

    {{-- Ngayong araw ayon sa type + Alerts --}}
    <div class="mt-7 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
        @php
            $colors = ['branded' => '#00945c', 'generic' => '#2a78d6', 'grocery' => '#eb6834'];
            $sumType = array_sum(array_column($byType, 'total'));
            $topType = $sumType > 0 ? collect($byType)->sortByDesc('total')->keys()->first() : null;
        @endphp
        <section class="card min-w-0 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2.5 font-display text-lg font-bold text-ink"><x-icon name="chart" class="h-5 w-5 text-brand-dark" /> Today's Sales by Type</h2>
                    <p class="mt-0.5 text-xs text-muted">Branded, Generic and Grocery — same totals as today's Daily Report.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('logbook.index') }}" class="btn-outline btn-sm"><x-icon name="file" class="h-4 w-4" /> Daily Report</a>
                    <a href="{{ route('analytics') }}" class="btn-soft btn-sm"><x-icon name="chart" class="h-4 w-4" /> Analytics</a>
                </div>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach ($byType as $key => $t)
                    <div class="rounded-xl border border-mint-2 bg-mint-3/40 p-4">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-sm font-semibold text-ink"><span class="h-3 w-3 rounded-sm" style="background: {{ $colors[$key] }}"></span> {{ $t['label'] }}</span>
                            @if ($topType === $key) <span class="rounded-full bg-brand px-2 py-0.5 text-[10px] font-bold uppercase text-white">Top</span> @endif
                        </div>
                        <p class="mt-3 font-display text-[26px] font-bold leading-none text-ink">₱{{ number_format($t['total'], 2) }}</p>
                        <p class="mt-1.5 text-xs text-muted">{{ $t['qty'] }} {{ $t['qty'] === 1 ? 'item' : 'items' }} sold</p>
                    </div>
                @endforeach
            </div>

            {{-- Hati ng benta --}}
            <div class="mt-5">
                <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full bg-mint-2" role="img"
                     aria-label="Share of today's sales: @foreach ($byType as $t){{ $t['label'] }} {{ $sumType > 0 ? round($t['total'] / $sumType * 100) : 0 }}%@if(!$loop->last), @endif @endforeach">
                    @if ($sumType > 0)
                        @foreach ($byType as $key => $t)
                            @if ($t['total'] > 0) <span style="width: {{ $t['total'] / $sumType * 100 }}%; background: {{ $colors[$key] }}"></span> @endif
                        @endforeach
                    @endif
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="text-muted">
                        @if ($sumType > 0)
                            @foreach ($byType as $t){{ $t['label'] }} {{ round($t['total'] / $sumType * 100) }}%@if(!$loop->last) • @endif @endforeach
                        @else
                            No sales yet today. <a href="{{ route('sales.create') }}" class="font-semibold text-brand-dark hover:underline">Open the POS</a> to record one.
                        @endif
                    </span>
                    <span class="font-semibold text-brand-dark">{{ $todayCount }} {{ $todayCount === 1 ? 'sale' : 'sales' }} • ₱{{ number_format($todayTotal, 2) }}</span>
                </div>
            </div>
        </section>

        <aside class="card min-w-0 p-5">
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 font-display text-base font-bold text-ink"><x-icon name="bell" class="h-4 w-4 text-warn" /> Urgent Alerts</h2>
                <span class="pill-warn">{{ $criticalCount }} Critical</span>
            </div>

            <div class="mt-4 max-h-[480px] space-y-3 overflow-y-auto pr-1">
                @foreach ($lowStockProducts as $p)
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-warn-line bg-warn-bg p-3">
                        <div class="flex min-w-0 gap-2.5">
                            <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0 text-danger" />
                            <div>
                                <p class="break-words text-sm font-semibold leading-snug text-ink [overflow-wrap:anywhere]">{{ $p->name }}</p>
                                <p class="mt-0.5 text-[11px] text-muted">{{ $p->stock }} unit{{ $p->stock === 1 ? '' : 's' }} remain (Reorder at: {{ \App\Models\Setting::get('low_stock_below') }})</p>
                            </div>
                        </div>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('products.edit', $p) }}" class="btn-outline btn-sm border-danger-line! text-danger">Restock</a>
                        @endif
                    </div>
                @endforeach

                @foreach ($expiringProducts as $p)
                    @php $days = (int) now()->startOfDay()->diffInDays($p->expiration_date, false); @endphp
                    <div class="flex items-start justify-between gap-3 rounded-lg border border-danger-line bg-danger-bg p-3">
                        <div class="flex min-w-0 gap-2.5">
                            <x-icon name="calendar-x" class="mt-0.5 h-4 w-4 shrink-0 text-danger" />
                            <div>
                                <p class="break-words text-sm font-semibold leading-snug text-ink [overflow-wrap:anywhere]">{{ $p->name }}</p>
                                <p class="mt-0.5 text-[11px] font-medium text-danger">{{ $days < 0 ? 'Expired ' . abs($days) . (abs($days) === 1 ? ' day ago' : ' days ago') : ($days === 0 ? 'Expires today' : "Expires in {$days} " . ($days === 1 ? 'day' : 'days')) }} ({{ $p->stock }} units)</p>
                            </div>
                        </div>
                        <a href="{{ route('products.show', $p) }}" class="btn-outline btn-sm border-danger-line! text-danger">Review</a>
                    </div>
                @endforeach

                @if ($criticalCount === 0)
                    <p class="rounded-lg border border-mint-2 bg-mint-3 p-4 text-center text-sm text-muted">All clear — no urgent alerts.</p>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
