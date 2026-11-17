@extends('layouts.app')
@section('title', 'Users')
@section('crumb-parent', 'Settings')
@section('crumb-parent-url', route('settings.edit'))
@section('crumb', 'Users')

@section('content')
@php $staffCount = $users->where('role', '!=', 'admin')->count(); @endphp
<div class="mx-auto max-w-[1000px]">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-[32px] font-bold leading-tight text-ink">Users</h1>
            <p class="mt-1 text-sm text-body">Everyone who can log in. New staff create their own account from the login page.</p>
        </div>
        <span class="rounded-full border border-mint-2 bg-white px-3 py-1.5 text-xs font-semibold text-ink">{{ $users->count() }} {{ $users->count() === 1 ? 'account' : 'accounts' }} • {{ $staffCount }} staff</span>
    </div>

    <div class="card mt-6 overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="bg-mint-3">
                <tr><th class="th">Name</th><th class="th">Email</th><th class="th">Role</th><th class="th">Status</th><th class="th text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-mint-2">
                @foreach ($users as $u)
                    <tr>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $u->role === 'admin' ? 'bg-brand text-white' : 'bg-mint-2 text-brand-dark' }} font-display text-xs font-bold">{{ $u->initials() }}</span>
                                <span class="font-semibold text-ink">{{ $u->name }} @if ($u->is(auth()->user())) <span class="pill-green ml-1">You</span> @endif</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-body">{{ $u->email }}</td>
                        <td class="px-4 py-3.5">
                            @if ($u->isPending() || $u->is(auth()->user()))
                                <span class="text-ink">{{ $roles[$u->role] ?? ucfirst($u->role) }}</span>
                            @else
                                <form method="POST" action="{{ route('users.update', $u) }}">
                                    @csrf @method('PUT')
                                    <select name="role" onchange="this.form.submit()" class="input w-32 py-1.5 text-sm" aria-label="Role of {{ $u->name }}">
                                        @foreach ($roles as $value => $label) <option value="{{ $value }}" @selected($u->role === $value)>{{ $label }}</option> @endforeach
                                    </select>
                                </form>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            @if ($u->isPending())
                                <span class="rounded-full border border-warn-line bg-warn-bg px-2.5 py-0.5 text-xs font-semibold text-warn" title="Created an account but hasn't entered the code from their email yet">Not verified</span>
                            @else
                                <span class="pill-green">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            @unless ($u->is(auth()->user()))
                                <form method="POST" action="{{ $u->isPending() ? route('users.reject', $u) : route('users.destroy', $u) }}" onsubmit="return confirm('Delete the account of {{ addslashes($u->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-sm"><x-icon name="trash" class="h-3.5 w-3.5" /> Delete</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
