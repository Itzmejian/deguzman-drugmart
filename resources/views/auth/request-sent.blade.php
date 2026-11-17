@extends('layouts.guest')
@section('title', 'Request Sent')

@section('content')
<div class="overflow-hidden rounded-xl border border-line bg-white shadow-[0_8px_24px_rgba(0,106,65,.08)]">
    <div class="h-1.5 bg-brand"></div>
    <div class="px-8 pb-7 pt-8 text-center">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-mint-2 text-brand-dark"><x-icon name="send" class="h-8 w-8" /></span>
        <h1 class="mt-5 font-display text-[26px] font-bold text-ink">Request sent!</h1>
        <p class="mt-2 text-[15px] leading-6 text-body">We emailed your <b>access code</b> to <b class="text-ink">{{ $email }}</b>.</p>

        <div class="mt-5 flex items-start gap-3 rounded-lg border border-warn-line bg-warn-bg px-4 py-3 text-left text-sm text-ink">
            <x-icon name="hourglass" class="mt-0.5 h-5 w-5 shrink-0 text-warn" />
            <span><b>Waiting for approval.</b> You can log in only after the administrator approves your account. You'll get another email once it's approved.</span>
        </div>

        @if ($demoCode)
            <div class="mt-5 rounded-lg border border-dashed border-brand/40 bg-mint-3 px-4 py-3">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Demo mode — email is not sent (MAIL_MAILER=log)</p>
                <p class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-brand-dark">{{ $demoCode }}</p>
            </div>
        @endif

        <a href="{{ route('login') }}" class="btn-primary mt-6 w-full py-3 text-base"><x-icon name="arrow-left" class="h-4 w-4" /> Back to Login</a>
    </div>
</div>
@endsection

@section('footer')
    @include('partials.auth-footer')
@endsection
