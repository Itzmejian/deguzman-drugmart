@extends('layouts.guest')
@section('title', 'Create Account for Staff')

@section('content')
<div class="overflow-hidden rounded-xl border border-line bg-white shadow-[0_8px_24px_rgba(0,106,65,.08)]">
    <div class="h-1.5 bg-brand"></div>
    <div class="px-8 pb-7 pt-7">
        <div class="flex items-center justify-between">
            <span class="pill-green font-mono text-[11px] uppercase tracking-wider"><x-icon name="user" class="h-3 w-3" /> Staff Access</span>
            <span class="font-mono text-xs text-muted">No password needed</span>
        </div>

        <h1 class="mt-6 font-display text-[28px] font-bold text-ink">Create Account for Staff</h1>
        <p class="mt-2 text-[15px] leading-6 text-body">Enter your name and email. We'll email you a <b>verification code</b> — that code is also your password.</p>

        <form method="POST" action="{{ route('staff.request') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="label">Full Name <span class="text-danger">*</span></label>
                <div class="relative">
                    <x-icon name="user" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-body" />
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="e.g. Maria Santos" class="input input-icon">
                </div>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="email" class="label">Email Address <span class="text-danger">*</span></label>
                <div class="relative">
                    <x-icon name="mail" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-body" />
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="e.g. yourname@gmail.com" class="input input-icon">
                </div>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
                <p class="mt-2 flex items-center gap-1.5 text-xs text-muted"><x-icon name="info" class="h-3.5 w-3.5" /> Use an email you can open now. The code will be sent here.</p>
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-base">Create Account <x-icon name="send" class="h-4 w-4" /></button>
            <a href="{{ route('login') }}" class="btn-outline w-full py-3 text-base text-body"><x-icon name="arrow-left" class="h-4 w-4" /> Back to Login</a>
        </form>

        <ol class="mt-6 grid gap-2 rounded-lg border border-line bg-mint-3 p-4 text-[13px] leading-5 text-body">
            <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand text-[11px] font-bold text-white">1</span> Enter your name and email, then tap Create Account.</li>
            <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand text-[11px] font-bold text-white">2</span> Open the email from De Guzman Drugmart. It has your code and a link to the system.</li>
            <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand text-[11px] font-bold text-white">3</span> Enter the code. You're in! Next time, log in with your email and the same code.</li>
        </ol>
    </div>
</div>
@endsection

@section('footer')
    @include('partials.auth-footer')
@endsection
