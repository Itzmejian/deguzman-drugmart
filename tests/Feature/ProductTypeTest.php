<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Branded / Generic / Grocery (ayon sa logbook) at Rx / OTC / Grocery na Classification */
class ProductTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@test.ph', 'password' => 'Secret#12345', 'role' => 'admin']);
    }

    private function product(string $name, string $type, string $cat, float $price = 10): Product
    {
        return Product::create(['sku' => 'T-' . random_int(10000, 99999), 'name' => $name, 'type' => $type, 'category' => $cat,
            'unit' => 'pc', 'price' => $price, 'stock' => 50, 'expiration_date' => now()->addYear()]);
    }

    public function test_add_product_saves_type_and_grocery_forces_grocery_classification(): void
    {
        $admin = $this->admin();
        $base = ['price' => 50, 'stock' => 5, 'expiration_date' => now()->addYear()->toDateString(), 'unit' => 'pc'];

        $this->actingAs($admin)->post('/products', $base + ['name' => 'Biogesic', 'type' => 'branded', 'category' => 'otc'])->assertRedirect();
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Bear Brand', 'type' => 'grocery', 'category' => 'otc'])->assertRedirect();
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Amoxicillin', 'type' => 'generic', 'category' => 'rx'])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Biogesic', 'type' => 'branded', 'category' => 'otc']);
        $this->assertDatabaseHas('products', ['name' => 'Bear Brand', 'type' => 'grocery', 'category' => 'grocery']);
        $this->assertDatabaseHas('products', ['name' => 'Amoxicillin', 'type' => 'generic', 'category' => 'otc']); // wala nang Classification sa form

        $this->actingAs($admin)->post('/products', $base + ['name' => 'X', 'type' => 'other', 'category' => 'both'])
            ->assertSessionHasErrors(['type']);
    }

    public function test_products_page_filters_by_branded_generic_grocery(): void
    {
        $admin = $this->admin();
        $this->product('Biogesic', 'branded', 'otc');
        $this->product('Paracetamol', 'generic', 'otc');
        $this->product('Bear Brand', 'grocery', 'grocery');

        $this->actingAs($admin)->get('/products?filter=branded')->assertSee('Biogesic')->assertDontSee('Bear Brand');
        $this->actingAs($admin)->get('/products?filter=generic')->assertSee('Paracetamol')->assertDontSee('Biogesic');
        $this->actingAs($admin)->get('/products?filter=grocery')->assertSee('Bear Brand')->assertDontSee('Paracetamol');
        $this->actingAs($admin)->get('/products')->assertSee('Branded')->assertSee('Generic')->assertSee('Grocery');
    }

    public function test_sales_by_type_on_analytics_dashboard_and_logbook(): void
    {
        $admin = $this->admin();
        $b = $this->product('Biogesic', 'branded', 'otc', 20);
        $g = $this->product('Amoxicillin', 'generic', 'rx', 12);
        $c = $this->product('Bear Brand', 'grocery', 'grocery', 85);

        $this->actingAs($admin)->postJson('/sales', ['items' => [
            ['product_id' => $b->id, 'quantity' => 2], ['product_id' => $g->id, 'quantity' => 1], ['product_id' => $c->id, 'quantity' => 1],
        ], 'prescription_verified' => true, 'cash_received' => 500])->assertOk();

        // Analytics: hati ng benta ayon sa type
        $a = $this->actingAs($admin)->get('/analytics?period=today')->assertOk();
        $this->assertEquals(['branded' => 40.0, 'generic' => 12.0, 'grocery' => 85.0], $a->viewData('totals'));
        $this->assertEquals(137.0, $a->viewData('grand'));

        // Dashboard: parehong totals ngayong araw
        $byType = $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSeeText("Today's Sales by Type")->viewData('byType');
        $this->assertEquals([40.0, 12.0, 85.0], array_column($byType, 'total'));

        // Logbook export: kasama ang lahat ng type
        $csv = $this->actingAs($admin)->get('/logbook/export')->streamedContent();
        $this->assertStringContainsString('Biogesic', $csv);
        $this->assertStringContainsString('Bear Brand', $csv);
    }

    public function test_editing_keeps_existing_rx_flag_without_classification_field(): void
    {
        $admin = $this->admin();
        $p = $this->product('Amoxicillin', 'generic', 'rx');
        $this->actingAs($admin)->put("/products/{$p->id}", ['name' => 'Amoxicillin', 'type' => 'branded', 'price' => 12, 'stock' => 9,
            'expiration_date' => now()->addYear()->toDateString(), 'unit' => 'capsule'])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $p->id, 'type' => 'branded', 'category' => 'rx']);

        $this->actingAs($admin)->get('/products/create')->assertDontSee('Classification');
    }

    public function test_grocery_can_have_no_expiration_but_medicines_cannot(): void
    {
        $admin = $this->admin();
        $base = ['price' => 45, 'stock' => 20, 'unit' => 'pack'];

        // Napkin (grocery) na walang expiry
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Modess Napkin', 'type' => 'grocery', 'no_expiry' => '1'])->assertSessionHasNoErrors();
        $napkin = Product::where('name', 'Modess Napkin')->first();
        $this->assertNull($napkin->expiration_date);
        $this->assertFalse($napkin->isExpired());
        $this->assertFalse($napkin->isExpiringSoon());
        $this->assertTrue($napkin->isSellable());

        // Grocery na walang tsek: kailangan pa rin ng petsa
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Biscuit', 'type' => 'grocery'])->assertSessionHasErrors('expiration_date');

        // Gamot: kahit naka-tsek, required pa rin ang petsa
        $this->actingAs($admin)->post('/products', $base + ['name' => 'Biogesic', 'type' => 'branded', 'no_expiry' => '1'])->assertSessionHasErrors('expiration_date');

        // Lumalabas sa Products, POS, Daily Report; wala sa Expiring/Expired
        $this->actingAs($admin)->get('/products')->assertSee('Modess Napkin')->assertSee('No expiry');
        $this->actingAs($admin)->get('/products?filter=expiring')->assertDontSee('Modess Napkin');
        $this->actingAs($admin)->get('/products?filter=expired')->assertDontSee('Modess Napkin');
        $this->actingAs($admin)->getJson('/sales/search?q=Modess')->assertOk()->assertSee('Modess Napkin');
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $napkin->id, 'quantity' => 2]], 'cash_received' => 100])->assertOk();
        $this->actingAs($admin)->get('/logbook')->assertSee('No expiry');
        $this->actingAs($admin)->get("/products/{$napkin->id}")->assertSee('No expiry');
        $this->actingAs($admin)->get('/dashboard')->assertOk();

        // I-edit: lagyan ng petsa ulit
        $this->actingAs($admin)->put("/products/{$napkin->id}", $base + ['name' => 'Modess Napkin', 'type' => 'grocery', 'stock' => 18, 'expiration_date' => now()->addYear()->toDateString()])->assertSessionHasNoErrors();
        $this->assertNotNull($napkin->fresh()->expiration_date);
    }

    public function test_admin_can_delete_product_from_list_but_staff_cannot(): void
    {
        $admin = $this->admin();
        $cashier = User::create(['name' => 'C', 'email' => 'c@test.ph', 'password' => 'Secret#12345', 'role' => 'cashier']);
        $p = $this->product('Old Item', 'grocery', 'grocery');

        $this->actingAs($admin)->get('/products')->assertSee('Delete Old Item');
        $this->actingAs($cashier)->get('/products')->assertDontSee('Delete Old Item');
        $this->actingAs($cashier)->delete("/products/{$p->id}")->assertForbidden();

        // May benta na: mabubura pa rin sa listahan, pero naiiwan ang history
        $this->actingAs($admin)->postJson('/sales', ['items' => [['product_id' => $p->id, 'quantity' => 1]], 'cash_received' => 100])->assertOk();
        $this->actingAs($admin)->delete("/products/{$p->id}")->assertRedirect('/products');
        $this->actingAs($admin)->get('/products')->assertDontSee('Old Item');
        $this->actingAs($admin)->getJson('/sales/search?q=Old')->assertJsonCount(0);
        $this->assertDatabaseHas('sale_items', ['product_name' => 'Old Item']);
        $this->actingAs($admin)->get('/sales/history')->assertOk();
    }
}
