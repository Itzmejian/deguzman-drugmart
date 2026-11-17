<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inililipat ang lumang data (database/database.sqlite) papunta sa MySQL.
 * Isang beses lang tumatakbo: kapag may laman na ang `users` sa MySQL, hindi na ito gagalaw.
 */
class ImportSqlite extends Command
{
    protected $signature = 'drugmart:import-sqlite {--path= : Path ng .sqlite file} {--force : Patungan kahit may data na}';

    protected $description = 'Ilipat ang data mula sa lumang SQLite database papunta sa MySQL';

    private const SKIP = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $path = $this->option('path') ?: database_path('database.sqlite');
        $target = DB::connection();

        if ($target->getDriverName() === 'sqlite') {
            $this->info('SQLite pa ang gamit (DB_CONNECTION=sqlite). Walang ililipat.');
            return self::SUCCESS;
        }
        if (! is_file($path) || filesize($path) === 0) {
            $this->info('Walang lumang SQLite data. Skip.');
            return self::SUCCESS;
        }
        if (! $this->option('force') && $target->table('users')->exists()) {
            $this->info('May data na ang MySQL. Skip (nailipat na dati).');
            return self::SUCCESS;
        }

        config(['database.connections.sqlite_import' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => false]]);
        $source = DB::connection('sqlite_import');

        $tables = collect($source->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP, true) || ! Schema::hasTable($t));

        $target->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $columns = array_values(array_intersect(Schema::connection('sqlite_import')->getColumnListing($table), Schema::getColumnListing($table)));
                $target->table($table)->truncate();

                $count = 0;
                foreach ($source->table($table)->select($columns)->get()->chunk(300) as $chunk) {
                    $target->table($table)->insert($chunk->map(fn ($r) => (array) $r)->all());
                    $count += $chunk->count();
                }
                $this->line(str_pad($table, 26) . $count . ' rows');
            }
        } finally {
            $target->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Tapos! Nailipat na sa MySQL ang lumang data.');
        return self::SUCCESS;
    }
}
