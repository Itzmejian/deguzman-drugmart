<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Grocery na walang expiry (hal. napkin, sabon): puwedeng walang petsa. Ang gamot ay required pa rin (sa validation). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->date('expiration_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Iniiwang nullable para hindi masira ang mga grocery na walang petsa.
    }
};
