@extends('layouts.guest')
@section('title', 'Enter Verification Code')

@section('content')
<div class="overflow-hidden rounded-xl border border-line bg-white shadow-[0_8px_24px_rgba(0,106,65,.08)]">
    <div class="h-1.5 bg-brand"></div>
    <div class="px-8 pb-7 pt-7">
        <div class="flex items-center justify-between">
            <span class="pill-green font-mono text-[11px] uppercase tracking-wider"><x-icon name="mail" class="h-3 w-3" /> Step 2 of 2</span>
            <span class="font-mono text-xs text-muted">Staff Account</span>
        </div>

        <h1 class="mt-6 font-display text-[28px] font-bold text-ink">Enter your code</h1>
        <p class="mt-2 text-[15px] leading-6 text-body">Check your email @if($email)<b class="text-ink">{{ $email }}</b>@endif for the 8-character code from De Guzman Drugmart. This code is also your password.</p>

        @if (session('status'))
            <div class="mt-5 rounded-lg border border-brand/25 bg-mint-3 px-4 py-3 text-sm font-medium text-brand-dark">{{ session('status') }}</div>
        @endif

        @if (session('demo_code'))
            <div class="mt-4 rounded-lg border border-dashed border-brand/40 bg-mint-3 px-4 py-3 text-center">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted">Demo mode — email is not sent (MAIL_MAILER=log)</p>
                <p class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-brand-dark">{{ session('demo_code') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('staff.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email Address</label>
                <div class="relative">
                    <x-icon name="mail" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-body" />
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email" placeholder="yourname@gmail.com" class="input input-icon" @if($email) readonly @endif>
                </div>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="code" class="label">Verification Code <span class="text-danger">*</span></label>
                <input id="code" name="code" value="{{ old('code') }}" required autofocus autocomplete="one-time-code" maxlength="9" placeholder="XXXX-XXXX"
                       class="input text-center font-mono text-2xl font-bold uppercase tracking-[0.3em]">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primary w-full py-3 text-base">Verify &amp; Open Dashboard <x-icon name="arrow-right" class="h-4 w-4" /></button>
        </form>

        <form method="POST" action="{{ route('staff.resend') }}" class="mt-4 text-center text-sm text-body">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            Didn't get it? Check Spam, or <button class="font-semibold text-brand-dark hover:underline">send a new code</button>.
        </form>
        <p class="mt-3 text-center text-sm"><a href="{{ route('login') }}" class="text-muted hover:text-ink">Back to Login</a></p>
    </div>
</div>
@endsection

@section('footer')
    @include('partials.auth-footer')
@endsection
