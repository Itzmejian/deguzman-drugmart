{{-- Isang Reports page: Sales & Stock | Analytics --}}
<nav class="mt-5 inline-flex print:hidden rounded-xl border border-mint-2 bg-white p-1 shadow-sm" aria-label="Report sections">
    @foreach ([['reports.analytics', 'Analytics', 'chart'], ['reports.index', 'Sales Report', 'file']] as [$r, $label, $icon])
        @php $on = request()->routeIs($r); @endphp
        <a href="{{ route($r) }}" @if($on) aria-current="page" @endif
           class="inline-flex items-center gap-2 rounded-lg px-5 py-2 text-sm font-semibold transition {{ $on ? 'bg-brand text-white shadow' : 'text-body hover:bg-mint' }}">
            <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
        </a>
    @endforeach
</nav>
