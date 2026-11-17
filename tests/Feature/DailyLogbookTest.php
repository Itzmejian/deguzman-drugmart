<?php

namespace Tests\Feature;

use App\Models\DailyStock;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Daily Logbook: Branded | Generic | Grocery, stock bawat araw na naka-save. */
class DailyLogbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_logbook_tracks_sales_and_stock_and_saves_each_day(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
        $base = ['unit' => 'pc', 'expiration_date' => now()->addYear()->toDateString()];

        // Kahapon: nag-add ng produkto at may benta
        $this->travelTo(now()->subDay()->setTime(10, 0));
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Biogesic', 'type' => 'branded', 'price' => 6, 'stock' => 50]);
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Bear Brand', 'type' => 'grocery', 'price' => 85, 'stock' => 10]);
        $bio = Product::where('name', 'Biogesic')->first();
        $bear = Product::where('name', 'Bear Brand')->first();
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $bio->id, 'quantity' => 5]], 'cash_received' => 100])->assertOk();
        $this->travelBack();

        // Ngayon: benta + restock
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $bio->id, 'quantity' => 3], ['product_id' => $bear->id, 'quantity' => 2]], 'cash_received' => 500])->assertOk();
        $this->actingAs($admin)->patchJson("/products/{$bio->id}/stock", ['delta' => 1])->assertOk();

        // Pagbukas ng logbook: naka-save na ang kahapon
        $today = $this->actingAs($admin)->get('/logbook')->assertOk()->assertSee('Live')->assertDontSee('Branded -')->assertSee('₱188.00');
        $this->assertTrue(DailyStock::whereDate('date', today()->subDay())->exists());

        $d = $today->viewData('d');
        $row = $d['rows']->firstWhere('name', 'Biogesic');
        $this->assertSame([45, 3, 1, 43], [$row->opening, $row->sold, $row->added, $row->closing]); // last stock kahapon = 45
        $this->assertEquals(18.0, $d['columns']['branded']['total']);
        $this->assertEquals(170.0, $d['columns']['grocery']['total']);
        $this->assertEquals(188.0, $d['total']);
        $this->assertCount(1, $d['columns']['branded']['entries']);

        // Kahapon: saved record (hindi na nagbabago kahit may benta ngayon)
        $y = $this->actingAs($admin)->get('/logbook?day=' . now()->subDay()->toDateString())->assertSee('Saved record')->viewData('d');
        $row = $y['rows']->firstWhere('name', 'Biogesic');
        $this->assertSame([0, 5, 50, 45], [$row->opening, $row->sold, $row->added, $row->closing]);
        $this->assertEquals(30.0, $y['total']);

        $csv = $this->actingAs($admin)->get('/logbook/export')->streamedContent();
        $this->assertStringContainsString('Biogesic', $csv);
        $this->assertStringContainsString('TOTAL', $csv);

        // Command para sa scheduler
        $this->artisan('logbook:save')->assertSuccessful();
    }

    public function test_expired_products_are_flagged_and_cashier_can_view_logbook(): void
    {
        $cashier = User::create(['name' => 'C', 'email' => 'c@test.ph', 'password' => 'Secret#12345', 'role' => 'cashier']);
        Product::create(['sku' => 'X-1', 'name' => 'Old Syrup', 'type' => 'generic', 'category' => 'otc', 'unit' => 'bottle',
            'price' => 50, 'stock' => 4, 'expiration_date' => now()->subDays(2)]);

        $d = $this->actingAs($cashier)->get('/logbook')->assertOk()->viewData('d');
        $this->assertTrue($d['rows']->firstWhere('name', 'Old Syrup')->expired);
        $this->assertSame(1, $d['counts']['expired']);
    }

    public function test_product_rules_rx_checkbox_price_and_expiry(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
        $base = ['unit' => 'capsule', 'stock' => 5, 'type' => 'generic'];

        $this->actingAs($admin)->post('/products', $base + ['name' => 'Free', 'price' => 0, 'expiration_date' => now()->addYear()->toDateString()])->assertSessionHasErrors('price');
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Old', 'price' => 5, 'expiration_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('expiration_date');
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Amoxicillin', 'price' => 12, 'requires_rx' => '1', 'expiration_date' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Amoxicillin', 'category' => 'rx']);
    }
}
