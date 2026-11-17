<?php

namespace Tests\Feature;

use App\Mail\NewStaffRequestMail;
use App\Mail\StaffAccessCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Create Account for Staff: pangalan + email -> code sa email -> ilagay ang code -> Dashboard */
class StaffRequestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
    }

    private function codeFor(string $email): string
    {
        $code = null;
        Mail::assertSent(StaffAccessCodeMail::class, function ($m) use (&$code, $email) {
            if ($m->hasTo($email)) $code = $m->code;
            return true;
        });

        return $code;
    }

    public function test_staff_creates_account_gets_code_by_email_and_enters_it_to_reach_dashboard(): void
    {
        Mail::fake();
        $this->admin();

        $this->get('/login')->assertSee('Create account for staff');
        $this->get('/staff/create-account')->assertOk()->assertDontSee('type="password"', false);

        $this->post('/staff/create-account', ['name' => 'Maria Santos', 'email' => 'Maria@Test.ph'])
            ->assertRedirect('/staff/verify?email=maria%40test.ph');

        $maria = User::where('email', 'maria@test.ph')->first();
        $this->assertSame('pending', $maria->status);
        $this->assertSame('cashier', $maria->role);

        $code = $this->codeFor('maria@test.ph');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        Mail::assertSent(StaffAccessCodeMail::class, fn ($m) => str_contains($m->render(), route('staff.verify', ['email' => 'maria@test.ph'])));
        Mail::assertSent(NewStaffRequestMail::class, fn ($m) => $m->hasTo('a@test.ph'));

        // Mali ang code -> hindi makakapasok
        $this->post('/staff/verify', ['email' => 'maria@test.ph', 'code' => 'AAAA-AAAA'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Tamang code (kahit maliit na letra at walang gitling) -> Dashboard
        $this->get('/staff/verify?email=maria@test.ph')->assertSee('Enter your code');
        $this->post('/staff/verify', ['email' => 'maria@test.ph', 'code' => strtolower(str_replace('-', '', $code))])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($maria->fresh());
        $this->assertSame('active', $maria->fresh()->status);
        $this->assertNotNull($maria->fresh()->email_verified_at);
        $this->get('/dashboard')->assertOk()->assertDontSee('Set my password');
        $this->post('/logout');

        // Sa susunod: login gamit ang email at ang code
        $this->post('/login', ['email' => 'maria@test.ph', 'password' => $code])->assertRedirect('/dashboard');
    }

    public function test_code_expires_resend_works_and_existing_accounts_are_protected(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->post('/staff/create-account', ['name' => 'Juan', 'email' => 'juan@test.ph']);
        $old = $this->codeFor('juan@test.ph');

        // Bagong code: hindi na gagana ang luma
        $this->post('/staff/verify/resend', ['email' => 'juan@test.ph'])->assertRedirect();
        $new = $this->codeFor('juan@test.ph');
        $this->assertNotSame($old, $new);
        $this->post('/staff/verify', ['email' => 'juan@test.ph', 'code' => $old])->assertSessionHasErrors('code');

        // Expired pagkalipas ng 24 oras
        $this->travel(25)->hours();
        $this->post('/staff/verify', ['email' => 'juan@test.ph', 'code' => $new])->assertSessionHasErrors('code');
        $this->post('/login', ['email' => 'juan@test.ph', 'password' => $new])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travelBack();

        // Hindi puwedeng gamitin ang email ng may account na
        $this->post('/staff/create-account', ['name' => 'Copy', 'email' => 'A@test.ph'])->assertSessionHasErrors('email');

        // Admin: makikita at mabubura ang hindi pa na-verify
        $juan = User::where('email', 'juan@test.ph')->first();
        $this->actingAs($admin)->get('/users')->assertSee('Not verified')->assertSee('juan@test.ph');
        $cashier = User::create(['name' => 'C', 'email' => 'c@test.ph', 'password' => 'Secret#12345', 'role' => 'cashier']);
        $this->actingAs($cashier)->delete("/users/{$juan->id}/reject")->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$juan->id}/reject")->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'juan@test.ph']);
    }

    public function test_staff_settings_have_no_password_or_store_info_and_can_email_a_new_code(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $staff = User::create(['name' => 'Ana', 'email' => 'ana@test.ph', 'password' => 'OLDC-ODE2', 'role' => 'cashier']);

        // Staff: walang Change Password, Store Information at Alerts
        $this->actingAs($staff)->get('/settings')->assertOk()
            ->assertSee('Email me a new code')->assertDontSee('Change Password')
            ->assertDontSee('Store Information')->assertDontSee('Alerts &amp; Inventory', false);
        $this->actingAs($staff)->put('/settings/password', ['current_password' => 'OLDC-ODE2', 'password' => 'New#Pass12345', 'password_confirmation' => 'New#Pass12345'])->assertForbidden();

        // Hindi mababago ng staff ang store info
        $this->actingAs($staff)->put('/settings', ['name' => 'Ana', 'email' => 'ana@test.ph', 'store_name' => 'HACKED']);
        $this->assertNotSame('HACKED', \App\Models\Setting::get('store_name'));

        // Bagong code sa email = bagong password
        $this->actingAs($staff)->post('/settings/new-code')->assertRedirect()->assertSessionHas('success');
        $code = $this->codeFor('ana@test.ph');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($code, $staff->fresh()->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('OLDC-ODE2', $staff->fresh()->password));
        Mail::assertSent(StaffAccessCodeMail::class, fn ($m) => str_contains($m->render(), 'Your new password code'));

        // Admin: kumpleto pa rin
        $this->actingAs($admin)->get('/settings')->assertSee('Change Password')->assertSee('Store Information')->assertDontSee('Email me a new code');
        $this->actingAs($admin)->post('/settings/new-code')->assertForbidden();
    }
}
