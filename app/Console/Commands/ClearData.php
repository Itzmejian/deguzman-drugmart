<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Burahin ang lahat ng test data: products, benta, Daily Report at stock history.
 * Hindi kasama ang users at store settings. May backup (JSON) muna bago burahin.
 */
class ClearData extends Command
{
    protected $signature = 'drugmart:clear-data {--force : Huwag nang magtanong}';

    protected $description = 'Burahin ang products, sales, Daily Report at stock history (may backup muna)';

    /** Pagkakasunod-sunod ng pagbura (anak muna bago magulang). */
    private const TABLES = ['sale_items', 'sales', 'stock_movements', 'daily_stocks', 'products'];

    public function handle(): int
    {
        $counts = collect(self::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $this->table(['Table', 'Rows'], $counts->map(fn ($n, $t) => [$t, $n])->values()->all());

        if (! $this->option('force') && ! $this->confirm('Burahin ang lahat ng ito? Hindi na maibabalik (maliban sa backup file).')) {
            $this->info('Hindi itinuloy.');
            return self::SUCCESS;
        }

        // Backup muna
        $backup = collect(self::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()])->all();
        $path = 'backups/before-clear-' . now()->format('Y-m-d_His') . '.json';
        Storage::disk('local')->put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Backup: storage/app/private/' . $path);

        DB::transaction(function () {
            foreach (self::TABLES as $t) {
                DB::table($t)->delete();
            }
        });

        // Huwag nang ibalik ng seeder ang sample products
        Setting::put('sample_data_seeded', '1');
        Cache::flush();

        $this->info('Tapos! Malinis na ang products, sales at Daily Report.');
        return self::SUCCESS;
    }
}
