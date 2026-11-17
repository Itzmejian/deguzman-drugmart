<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opisyal na admin email ng De Guzman Drugmart.
 * Email lang ang pinapalitan (hindi ang password) ng unang admin account.
 */
return new class extends Migration
{
    private const EMAIL = 'deguzmandrugmart01@gmail.com';

    public function up(): void
    {
        if (DB::table('users')->whereRaw('lower(email) = ?', [self::EMAIL])->exists()) {
            return;
        }

        $admin = DB::table('users')->where('role', 'admin')->orderBy('id')->first();
        if ($admin) {
            DB::table('users')->where('id', $admin->id)->update(['email' => self::EMAIL, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Walang ibabalik: hindi alam ang dating email.
    }
};
