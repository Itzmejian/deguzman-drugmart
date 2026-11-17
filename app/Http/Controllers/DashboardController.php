<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;

class DashboardController extends Controller
{
    public function index()
    {
        app(\App\Services\Logbook::class)->saveMissingDays(); // naka-save ang logbook ng mga nakaraang araw

        $todayTotal = (float) Sale::completed()->whereDate('created_at', today())->sum('total');
        $yesterdayTotal = (float) Sale::completed()->whereDate('created_at', today()->subDay())->sum('total');

        $delta = $yesterdayTotal > 0
            ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
            : null;

        $lowStockProducts = Product::lowStock()->orderBy('stock')->limit(100)->get();
        $expiringProducts = Product::expiringOrExpired()->orderBy('expiration_date')->limit(100)->get();

        $lowStockCount = Product::lowStock()->count();
        $expiringCount = Product::expiringSoon()->count();
        $expiredCount  = Product::expired()->count();

        return view('dashboard.index', [
            'totalProducts'    => Product::count(),
            'lowStockCount'    => $lowStockCount,
            'expiringCount'    => $expiringCount,
            'expiredCount'     => $expiredCount,
            'todayTotal'       => $todayTotal,
            'delta'            => $delta,
            'byType'           => $this->todayByType(),
            'todayCount'       => Sale::completed()->whereDate('created_at', today())->count(),
            'lowStockProducts' => $lowStockProducts,
            'expiringProducts' => $expiringProducts,
            // Critical = lahat ng alerts: low stock + expiring soon + expired (kapareho ng nasa mga card)
            'criticalCount'    => $lowStockCount + $expiringCount + $expiredCount,
        ]);
    }

    /** Benta ngayong araw: Branded / Generic / Grocery (piso at dami). */
    private function todayByType(): array
    {
        $out = [];
        foreach (AnalyticsController::TYPES as $key => $label) {
            $out[$key] = ['label' => $label, 'total' => 0.0, 'qty' => 0];
        }
        foreach (AnalyticsController::items(today()->startOfDay(), today()->endOfDay()) as $i) {
            $out[$i->type]['total'] = round($out[$i->type]['total'] + (float) $i->net, 2);
            $out[$i->type]['qty'] += (int) $i->quantity;
        }

        return $out;
    }
}
