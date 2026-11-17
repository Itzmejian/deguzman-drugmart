{{-- Reports: Daily Report (logbook) | Analytics --}}
<nav class="mt-4 inline-flex gap-1 rounded-xl border border-mint-2 bg-white p-1 shadow-sm print:hidden" aria-label="Reports">
    @foreach ([['logbook.index', 'Daily Report', 'file'], ['analytics', 'Analytics', 'chart']] as [$r, $label, $icon])
        @php $on = request()->routeIs($r); @endphp
        <a href="{{ route($r) }}" @if($on) aria-current="page" @endif
           class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition {{ $on ? 'bg-ink text-white shadow' : 'text-body hover:bg-mint' }}">
            <x-icon :name="$icon" class="h-4 w-4" /> {{ $label }}
        </a>
    @endforeach
</nav>
