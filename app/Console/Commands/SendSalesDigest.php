<?php

namespace App\Console\Commands;

use App\Mail\DailySalesDigestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Logbook;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Email digest ng benta, para sa (mga) Admin.
 * Pinapatakbo ng scheduler bawat 10 minuto; nagpapadala lang kapag 10:00 PM na at hindi pa naipapadala ngayong araw.
 * Kung sarado ang PC noong 10 PM, ipapadala ang kahapon pagbukas ulit (catch-up).
 */
class SendSalesDigest extends Command
{
    public const HOUR = 22; // 10:00 PM

    protected $signature = 'drugmart:send-digest {--date= : Y-m-d (para sa test)} {--force : Ipadala kahit naka-off o naipadala na}';

    protected $description = 'Ipadala ang daily sales digest sa email ng Admin';

    public function handle(Logbook $logbook): int
    {
        if (! $this->option('force') && Setting::get('email_digest') !== '1') {
            $this->info('Naka-off ang email digest sa Settings.');
            return self::SUCCESS;
        }

        $day = $this->dayToSend();
        if (! $day) {
            $this->info('Wala pang ipapadala.');
            return self::SUCCESS;
        }

        $admins = User::active()->where('role', 'admin')->get();
        $data = $logbook->day($day);
        $sent = 0;

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new DailySalesDigestMail($admin, $day, $data));
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Sales digest email failed: ' . $e->getMessage());
            }
        }

        if ($sent > 0 && ! $this->option('date')) {
            Setting::put('digest_last_sent', $day->toDateString());
        }

        $this->info("Naipadala ang digest para sa {$day->toDateString()} ({$sent} admin).");
        return self::SUCCESS;
    }

    private function dayToSend(): ?Carbon
    {
        if ($this->option('date')) {
            return Carbon::parse($this->option('date'))->startOfDay();
        }

        $last = Setting::get('digest_last_sent');
        $today = today();

        if (now()->hour >= self::HOUR) {
            return $last === $today->toDateString() && ! $this->option('force') ? null : $today;
        }

        // Bago mag-10 PM: kung hindi naipadala kahapon (hal. sarado ang PC), ipadala ngayon
        $yesterday = $today->copy()->subDay();
        if ($last && $last >= $yesterday->toDateString()) {
            return $this->option('force') ? $today : null;
        }

        return $last ? $yesterday : ($this->option('force') ? $today : null);
    }
}
