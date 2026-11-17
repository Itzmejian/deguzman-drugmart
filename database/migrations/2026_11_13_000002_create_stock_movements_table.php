<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talaan ng bawat galaw ng stock (parang logbook): benta, dagdag/bawas, bagong produkto, edit.
 * Dito kinukuha ng Daily Logbook report ang stock ng bawat araw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('change');                 // + dumagdag, - nabawas
            $table->string('reason', 20);              // sale | restock | adjust | initial | edit
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        // Ilagay ang mga lumang benta para tama ang stock ng mga nakaraang araw
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNotNull('sale_items.product_id')
            ->whereIn('sale_items.product_id', DB::table('products')->select('id'))
            ->select('sale_items.product_id', 'sale_items.quantity', 'sales.id as sale_id', 'sales.user_id', 'sales.created_at')
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('stock_movements')->insert($chunk->map(fn ($r) => [
                'product_id' => $r->product_id, 'change' => -$r->quantity, 'reason' => 'sale',
                'sale_id' => $r->sale_id, 'user_id' => $r->user_id, 'created_at' => $r->created_at, 'updated_at' => $r->created_at,
            ])->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
