<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinusunod ang logbook ng botika: Branded / Generic / Grocery.
 * - Bagong column `type`: branded | generic | grocery
 * - Ang `category` (Classification) ay rx | otc | grocery na lang ('both' → 'otc').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('generic')->after('category')->index();
        });

        DB::table('products')->where('category', 'both')->update(['category' => 'otc']);

        // Mga sample na produkto: branded ang mga may brand
        DB::table('products')->whereIn('sku', ['VTC-120', 'CGH-060', 'EYE-015', 'ANT-250', 'BND-100'])->update(['type' => 'branded']);
    }

    public function down(): void
    {
        DB::table('products')->where('category', 'grocery')->update(['category' => 'otc']);

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
