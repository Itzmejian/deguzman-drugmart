<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewStaffRequestMail;
use App\Mail\StaffAccessCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create Account for Staff:
 *  1. Pangalan at email lang ang ilalagay (walang password).
 *  2. May verification code na ipapadala sa email. Iyon na rin ang PASSWORD niya.
 *     Sa email may link papunta sa system.
 *  3. Sa system, ilalagay ang code. Tama ang code = active na ang account at papasok na sa Dashboard.
 */
class StaffRequestController extends Controller
{
    /** Ilang oras bago mag-expire ang code kung hindi pa na-verify. */
    public const CODE_HOURS = 24;

    public function show()
    {
        return view('auth.request-account');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $existing = User::whereRaw('lower(email) = ?', [$data['email']])->first();
        if ($existing && ! $existing->isPending()) {
            throw ValidationException::withMessages(['email' => 'This email already has an account. Log in instead.']);
        }

        $code = self::generateCode();

        // Kung hindi pa na-verify dati, bagong code na lang ang ipapadala
        $user = $existing ?? new User(['role' => 'cashier', 'title' => 'Cashier', 'status' => 'pending']);
        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'password' => $code, 'must_change_password' => false]);
        $user->save();
        $user->touch(); // simula ng bilang ng expiry

        $this->sendCode($user, $code);

        if (! $existing) {
            foreach (User::active()->where('role', 'admin')->get() as $admin) {
                try {
                    Mail::to($admin->email)->send(new NewStaffRequestMail($user, $admin));
                } catch (\Throwable $e) {
                    Log::error('Admin notification email failed: ' . $e->getMessage());
                }
            }
        }

        return redirect()->route('staff.verify', ['email' => $user->email])
            ->with('status', 'We sent a verification code to ' . $user->email . '.')
            ->with('demo_code', $this->demoCode($code));
    }

    /** Pahina na bubuksan mula sa link sa email: ilagay ang code. */
    public function verifyForm(Request $request)
    {
        return view('auth.verify-staff', ['email' => Str::lower(trim((string) $request->query('email')))]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email'))), 'code' => Str::upper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'max:20'],
        ]);

        $user = User::whereRaw('lower(email) = ?', [$data['email']])->first();
        $code = self::normalizeCode($data['code']);

        if (! $user || ! Hash::check($code, $user->password)) {
            throw ValidationException::withMessages(['code' => 'Wrong code. Check the latest email we sent you.']);
        }

        if (! $user->isPending()) {
            return redirect()->route('login')->with('status', 'Your account is already verified. Log in with your email and password.');
        }

        self::ensureNotExpired($user, 'code');
        self::activate($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome, ' . $user->name . '! Your account is ready. Use the same code as your password next time.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        // Pareho ang sagot kahit walang account (hindi nalalaman kung sino ang may account)
        $code = null;
        if ($user && $user->isPending()) {
            $code = self::generateCode();
            $user->forceFill(['password' => Hash::make($code)])->save();
            $user->touch();
            $this->sendCode($user, $code);
        }

        return redirect()->route('staff.verify', ['email' => $email])
            ->with('status', 'If that email is waiting for verification, a new code was sent.')
            ->with('demo_code', $code ? $this->demoCode($code) : null);
    }

    /** Na-verify: puwede nang pumasok. */
    public static function activate(User $user): void
    {
        $user->forceFill(['status' => 'active', 'email_verified_at' => now(), 'approved_at' => now()])->save();
    }

    public static function ensureNotExpired(User $user, string $field): void
    {
        if ($user->updated_at && $user->updated_at->lt(now()->subHours(self::CODE_HOURS))) {
            throw ValidationException::withMessages([$field => 'This code has expired. Tap "Send a new code" to get another one.']);
        }
    }

    /** 8 character na code, walang nakakalitong letra (0/O, 1/I/L). Hal. 7KQ4-MX9P */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return substr($s, 0, 4) . '-' . substr($s, 4);
    }

    /** "7kq4mx9p" o "7KQ4 MX9P" -> "7KQ4-MX9P" */
    public static function normalizeCode(string $code): string
    {
        $c = preg_replace('/[^A-Z0-9]/', '', Str::upper($code));

        return strlen($c) === 8 ? substr($c, 0, 4) . '-' . substr($c, 4) : $code;
    }

    private function sendCode(User $user, string $code): void
    {
        try {
            Mail::to($user->email)->send(new StaffAccessCodeMail($user, $code));
        } catch (\Throwable $e) {
            Log::error('Staff verification email failed: ' . $e->getMessage());
        }
    }

    /** Kapag "log" ang mail (local/demo, walang Gmail), ipakita ang code sa screen para masubukan. */
    private function demoCode(string $code): ?string
    {
        return (app()->environment('local') && config('mail.default') === 'log') ? $code : null;
    }
}
