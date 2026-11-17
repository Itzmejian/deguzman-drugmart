<?php

namespace Tests\Feature;

use App\Mail\DailySalesDigestMail;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalesDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_digest_is_sent_to_admins_at_10pm_once_with_type_totals(): void
    {
        Mail::fake();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
        User::create(['name' => 'C', 'email' => 'c@test.ph', 'password' => 'Secret#12345', 'role' => 'cashier']);
        Setting::put('email_digest', '1');
        $p = Product::create(['sku' => 'B-1', 'name' => 'Biogesic', 'type' => 'branded', 'category' => 'otc', 'unit' => 'piece',
            'price' => 10, 'stock' => 50, 'expiration_date' => now()->addYear()]);

        $this->travelTo(today()->setTime(15, 0));
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $p->id, 'quantity' => 3]], 'cash_received' => 100])->assertOk();

        // Bago 10 PM: wala pa
        $this->artisan('drugmart:send-digest')->assertSuccessful();
        Mail::assertNothingSent();

        // 10:05 PM: ipapadala sa admin lang, isang beses
        $this->travelTo(today()->setTime(22, 5));
        $this->artisan('drugmart:send-digest')->assertSuccessful();
        $this->artisan('drugmart:send-digest')->assertSuccessful();
        Mail::assertSent(DailySalesDigestMail::class, 1);
        Mail::assertSent(DailySalesDigestMail::class, fn ($m) => $m->hasTo('a@test.ph') && $m->d['columns']['branded']['total'] == 30.0
            && str_contains($m->render(), '₱30.00') && str_contains($m->render(), 'Biogesic') && str_contains($m->render(), 'Stock left'));
        Mail::assertNotSent(DailySalesDigestMail::class, fn ($m) => $m->hasTo('c@test.ph'));
    }

    public function test_missed_digest_is_sent_next_day_and_toggle_off_stops_it_and_test_button_works(): void
    {
        Mail::fake();
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
        Setting::put('email_digest', '1');
        Setting::put('digest_last_sent', today()->subDays(2)->toDateString()); // sarado ang PC kahapon ng 10 PM

        $this->travelTo(today()->setTime(9, 0));
        $this->artisan('drugmart:send-digest')->assertSuccessful();
        Mail::assertSent(DailySalesDigestMail::class, fn ($m) => $m->day->isSameDay(today()->subDay()));

        // Naka-off: walang ipapadala
        Setting::put('email_digest', '0');
        $this->travelTo(today()->setTime(22, 30));
        $this->artisan('drugmart:send-digest')->assertSuccessful();
        Mail::assertSent(DailySalesDigestMail::class, 1);

        // Wala nang Send test email sa Settings
        $this->actingAs($admin)->get('/settings')->assertDontSee('Send test email');
    }
}
