<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Rules\EmailNotTaken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SettingController extends Controller
{
    public function edit()
    {
        $keys = array_keys(Setting::DEFAULTS);
        $values = collect($keys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all();

        return view('settings.edit', ['s' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // I-lowercase muna bago i-validate (iwas duplicate / 500 error)
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', new EmailNotTaken($user->id)],
            'avatar'        => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,webp,gif,bmp,avif', 'max:10240'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];

        // Store info at thresholds: Admin lang.
        if ($user->isAdmin()) {
            $rules += [
                'store_name'       => ['required', 'string', 'max:120'],
                'store_address'    => ['required', 'string', 'max:255'],
                'store_phone'      => ['nullable', 'string', 'max:40'],
                'low_stock_below'  => ['required', 'integer', 'min:0', 'max:100000'],
                'expiry_warn_days' => ['required', 'integer', 'min:1', 'max:3650'],
            ];
        }

        $data = $request->validate($rules, [
            'avatar.uploaded' => 'The photo failed to upload. Please try a smaller PNG or JPG image.',
            'avatar.image'    => 'The profile photo must be an image (PNG, JPG, JPEG, WEBP, GIF or BMP).',
            'avatar.mimes'    => 'The profile photo must be a PNG, JPG, JPEG, WEBP, GIF or BMP file.',
            'avatar.max'      => 'The profile photo may not be larger than 10MB.',
        ]);

        $account = ['name' => $data['name'], 'email' => $data['email']];

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $account['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $account['avatar_path'] = null;
        }

        $user->update($account);

        if ($user->isAdmin()) {
            foreach (['store_name', 'store_address', 'store_phone', 'low_stock_below', 'expiry_warn_days'] as $key) {
                Setting::put($key, (string) ($data[$key] ?? ''));
            }
            Setting::put('email_digest', $request->boolean('email_digest') ? '1' : '0');
        }

        return back()->with('success', 'Settings saved.');
    }

    /** Staff: walang Change Password. Bagong code ang ipapadala sa email at iyon ang bagong password. */
    public function newCode(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403);

        $code = \App\Http\Controllers\Auth\StaffRequestController::generateCode();

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\StaffAccessCodeMail($user, $code));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('New staff code email failed: ' . $e->getMessage());
            return back()->with('error', 'We could not send the email right now, so your password was not changed. Please try again later.');
        }

        // Papalitan lang ang password kapag naipadala na ang email
        $user->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($code), 'must_change_password' => false])->save();
        $request->session()->regenerate();

        $demo = (app()->environment('local') && config('mail.default') === 'log') ? ' (Demo code: ' . $code . ')' : '';

        return back()->with('success', 'A new code was sent to ' . $user->email . '. Use it as your password next time you log in.' . $demo);
    }

    public function password(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403); // Staff: gumamit ng "Email me a new code"

        // Sariling error bag para hindi maghalo sa main form
        $data = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed'                => 'The new password and confirmation do not match.',
            'password.different'                => 'The new password must be different from your current password.',
        ]);

        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);
        $request->session()->regenerate();

        return back()->with('success', 'Password updated successfully.');
    }
}
