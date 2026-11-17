<?php

namespace Tests\Feature;

use App\Models\DailyStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClearDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_data_removes_products_sales_and_logbook_but_keeps_users_and_seeder_does_not_refill(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $p = Product::first();
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $p->id, 'quantity' => 1]], 'cash_received' => 1000])->assertOk();
        $this->travelTo(now()->addDay());
        $this->actingAs($admin)->get('/logbook')->assertOk(); // nagse-save ng Daily Report kahapon
        $this->travelBack();
        $this->assertTrue(DailyStock::exists());

        $this->artisan('drugmart:clear-data', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Product::count());
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, DailyStock::count());
        $this->assertTrue(User::where('id', $admin->id)->exists());
        $this->assertNotEmpty(Storage::disk('local')->files('backups'));

        // Pagpatakbo ulit ng run-drugmart.bat (db:seed): hindi na babalik ang sample products
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, Product::count());

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/products')->assertOk();
        $this->actingAs($admin)->get('/logbook')->assertOk();
        $this->actingAs($admin)->get('/analytics')->assertOk();
    }
}
