<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Logbook: naka-save ang stock ng bawat produkto sa dulo ng bawat araw
 * (parang isang pahina ng logbook), para makita ang "last stock" ng nakaraang araw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stocks', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('unit', 30)->nullable();
            $table->string('type', 20)->default('generic');
            $table->boolean('rx')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->date('expiration_date')->nullable();
            $table->integer('opening')->default(0);   // stock sa umpisa ng araw (= last stock kahapon)
            $table->integer('sold')->default(0);
            $table->integer('added')->default(0);
            $table->integer('removed')->default(0);
            $table->integer('closing')->default(0);   // stock sa dulo ng araw
            $table->decimal('sales_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stocks');
    }
};
