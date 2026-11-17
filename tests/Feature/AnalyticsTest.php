<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_totals_per_type_for_every_period(): void
    {
        Cache::flush();
        $cashier = User::create(['name' => 'C', 'email' => 'c@test.ph', 'password' => 'Secret#12345', 'role' => 'cashier']);
        $mk = fn ($n, $t, $price) => Product::create(['sku' => 'A-' . $n, 'name' => $n, 'type' => $t, 'category' => $t === 'grocery' ? 'grocery' : 'otc',
            'unit' => 'pc', 'price' => $price, 'stock' => 100, 'expiration_date' => now()->addYear()]);
        $b = $mk('Biogesic', 'branded', 10); $g = $mk('Paracetamol', 'generic', 5); $c = $mk('Bear Brand', 'grocery', 80);

        $this->actingAs($cashier)->postJson('/sales', ['items' => [['product_id' => $b->id, 'quantity' => 3], ['product_id' => $g->id, 'quantity' => 2], ['product_id' => $c->id, 'quantity' => 1]], 'cash_received' => 200])->assertOk();

        foreach (['today', 'week', 'month', 'year'] as $period) {
            $r = $this->actingAs($cashier)->get('/analytics?period=' . $period)->assertOk()->assertSee('Sales over time');
            $this->assertEquals(['branded' => 30.0, 'generic' => 10.0, 'grocery' => 80.0], $r->viewData('totals'), $period);
            $this->assertEquals(120.0, $r->viewData('grand'));
            $this->assertEquals(120.0, collect($r->viewData('series'))->sum(fn ($s) => $s['branded'] + $s['generic'] + $s['grocery']), $period);
            $this->assertSame('Bear Brand', $r->viewData('top')->first()['name']);
        }

        $this->assertCount(7, $this->actingAs($cashier)->get('/analytics?period=week')->viewData('series'));
        $this->assertCount(12, $this->actingAs($cashier)->get('/analytics?period=year')->viewData('series'));
        $this->actingAs($cashier)->get('/analytics?period=bad')->assertSessionHasErrors('period');
        $this->actingAs($cashier)->get('/reports')->assertRedirect('/logbook');
        $this->actingAs($cashier)->get('/dashboard')->assertSee(route('analytics'))->assertDontSee('Recent Transactions')
            // Clickable ang 4 na card (admin at staff)
            ->assertSee('href="' . route('products.index') . '"', false)
            ->assertSee(route('products.index', ['filter' => 'low']), false)
            ->assertSee(route('products.index', ['filter' => 'expiring']), false)
            ->assertSee('View Daily Report');
    }
}
