<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Rules\EmailNotTaken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public const ROLES = ['admin' => 'Admin', 'cashier' => 'Cashier'];

    public function index()
    {
        return view('users.index', [
            // Lahat ng account: admin muna, tapos staff; kasama ang hindi pa na-verify
            'users'   => User::query()->orderByRaw("case when role = 'admin' then 0 else 1 end")->orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(self::ROLES))]]);

        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own admin access.');
        }
        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'There must be at least one administrator.');
        }

        $user->update(['role' => $data['role'], 'title' => $data['role'] === 'admin' ? 'Dispensary Lead' : 'Cashier']);

        return back()->with('success', 'Role updated for ' . $user->name . '.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'There must be at least one administrator.');
        }
        if ($user->sales()->exists()) {
            return back()->with('error', $user->name . ' has recorded sales and cannot be deleted. Change the role instead.');
        }

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->delete();

        return back()->with('success', 'Account deleted.');
    }

    /** I-approve ang staff na nag-request ng account. */
    /** Burahin ang hindi pa na-verify na staff account. */
    public function reject(User $user): RedirectResponse
    {
        abort_unless($user->isPending(), 404);

        $name = $user->name;
        $user->delete();

        return back()->with('success', 'The unverified account of ' . $name . ' was removed.');
    }
}
