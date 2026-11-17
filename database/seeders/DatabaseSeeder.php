<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Admin account (palitan agad ang password pagkatapos mag-login!) ----
        // Gagawin lang ang admin kung WALA pa. Hindi na ire-reset ang password kapag nag-`migrate --seed` ulit.
        $adminEmail = mb_strtolower((string) env('ADMIN_EMAIL', 'deguzmandrugmart01@gmail.com'));
        // Gagawa lang ng admin kung WALA pang kahit isang admin (kahit pinalitan na ang email niya sa Settings).
        $admin = User::where('role', 'admin')->orderBy('id')->first()
            ?? User::create([
                'name'     => 'Administrator',
                'email'    => $adminEmail,
                'password' => env('ADMIN_PASSWORD', 'Admin#Drugmart2026'),
                'role'     => 'admin',
                'title'    => 'Dispensary Lead',
            ]);

        // ---- Store defaults ----
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings.all');

        // ---- Sample catalog (galing sa Figma design) ----
        // Isang beses lang (bagong install). Kapag binura na ang data, hindi na ibabalik.
        if (Product::count() === 0 && ! Setting::get('sample_data_seeded')) {
            $this->seedProducts();

            if (app()->environment('local')) {
                $this->seedDemoSales($admin);
            }
        }
        Setting::put('sample_data_seeded', '1');
    }

    private function seedProducts(): void
    {
        $rows = [
            // name, sku, category (rx|otc|grocery), type (branded|generic|grocery), subtitle, unit, price, stock, expires in (days), description
            ['Paracetamol Tablets 500mg', 'PCM-500', 'otc', 'generic', 'Oral Pain Relief & Antipyretic', 'tablet', 8.50, 48, 780, 'Effective relief for mild to moderate pain including headache, migraine, toothache, and reducing fever. Keep in a dry, cool cabinet away from direct sunlight.'],
            ['Amoxicillin Capsules 500mg', 'AMX-500', 'rx', 'generic', 'Antibiotic', 'capsule', 12.00, 5, 400, 'Prescription antibiotic. Dispense only with a valid prescription.'],
            ['Vitamin C Syrup 120ml', 'VTC-120', 'otc', 'branded', 'Vitamin', 'bottle', 150.00, 120, 520, 'Daily vitamin C supplement syrup. Shake well before use.'],
            ['Cough Syrup (Dextromethorphan) 60ml', 'CGH-060', 'otc', 'branded', 'Respiratory', 'bottle', 95.50, 14, 14, 'Cough suppressant for dry, non-productive cough.'],
            ['Ibuprofen Tablets 400mg', 'IBU-400', 'otc', 'generic', 'Anti-inflammatory', 'tablet', 7.25, 85, 600, 'Relieves pain and inflammation. Take with food.'],
            ['Eye Drops 15ml', 'EYE-015', 'otc', 'branded', 'Lubricant', 'bottle', 135.00, 3, 300, 'Lubricating eye drops for dryness and irritation.'],
            ['Antiseptic Lotion 250ml', 'ANT-250', 'otc', 'branded', 'Antiseptic', 'bottle', 65.00, 62, 700, 'Skin antiseptic for minor cuts and scrapes.'],
            ['Bear Brand Powdered Milk 150g', 'BRB-150', 'grocery', 'grocery', 'Powdered Milk', 'pack', 85.00, 40, 300, 'Fortified powdered milk drink.'],
            ['Safeguard Bar Soap 130g', 'SFG-130', 'grocery', 'grocery', 'Personal Care', 'bar', 48.00, 30, 900, 'Antibacterial bar soap.'],
            ['Bandages (Flexible Fabric)', 'BND-100', 'otc', 'branded', 'First Aid', 'box', 45.00, 210, 900, 'Flexible fabric adhesive bandages, assorted sizes.'],
        ];

        foreach ($rows as [$name, $sku, $cat, $type, $sub, $unit, $price, $stock, $days, $desc]) {
            Product::create([
                'sku' => $sku, 'name' => $name, 'category' => $cat, 'type' => $type, 'subtitle' => $sub, 'unit' => $unit,
                'price' => $price, 'stock' => $stock, 'expiration_date' => now()->addDays($days),
                'description' => $desc,
            ]);
        }
    }

    /** Ilang halimbawang benta para hindi blangko ang Dashboard at Reports (local lang). */
    private function seedDemoSales(User $cashier): void
    {
        $products = Product::all()->keyBy('sku');
        $n = 0;

        foreach ([[0, 'PCM-500', 2, 'VTC-120', 1], [0, 'IBU-400', 4, null, 0], [1, 'VTC-120', 2, 'BND-100', 1], [2, 'PCM-500', 10, 'ANT-250', 1], [3, 'BND-100', 3, 'BRB-150', 1]] as [$daysAgo, $a, $qa, $b, $qb]) {
            $lines = [[$products[$a], $qa]];
            if ($b) $lines[] = [$products[$b], $qb];

            $subtotal = collect($lines)->sum(fn ($l) => $l[0]->price * $l[1]);
            $cash = ceil($subtotal / 100) * 100;

            $sale = Sale::create([
                'invoice_no' => sprintf('INV-%d-%04d', now()->year, ++$n),
                'user_id' => $cashier->id, 'subtotal' => $subtotal, 'senior_pwd' => false, 'discount' => 0,
                'total' => $subtotal, 'cash_received' => $cash, 'change_due' => $cash - $subtotal, 'status' => 'completed',
                'created_at' => now()->subDays($daysAgo)->subMinutes($n * 37), 'updated_at' => now(),
            ]);

            foreach ($lines as [$p, $q]) {
                SaleItem::create([
                    'sale_id' => $sale->id, 'product_id' => $p->id, 'product_name' => $p->name,
                    'unit_price' => $p->price, 'quantity' => $q, 'line_total' => $p->price * $q,
                ]);
                StockMovement::create(['product_id' => $p->id, 'change' => -$q, 'reason' => 'sale', 'sale_id' => $sale->id, 'user_id' => $cashier->id])
                    ->forceFill(['created_at' => $sale->created_at])->save();
            }
        }
    }
}
