<?php

namespace App\Console\Commands;

use App\Services\Logbook;
use Illuminate\Console\Command;

/** I-save ang Daily Logbook ng mga nakaraang araw (awtomatiko rin itong ginagawa pag binuksan ang system). */
class SaveLogbook extends Command
{
    protected $signature = 'logbook:save';

    protected $description = 'Save the daily stock logbook for every past day that is not yet saved';

    public function handle(Logbook $logbook): int
    {
        $n = $logbook->saveMissingDays();
        $this->info("Saved {$n} day(s).");

        return self::SUCCESS;
    }
}
