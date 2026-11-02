<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\DatabaseTransferRepository;
use Illuminate\Console\Command;

/**
 * Rehearse or execute the transfer after both schemas have been upgraded to 2.5.
 */
class TransferDatabase extends Command
{
    protected $signature = 'classy:transfer-database {--execute : Import into the empty PostgreSQL schema} {--replace-schema-defaults : Replace only the attributes and marketing event rows created by fresh migrations}';

    protected $description = 'Validate or transfer an upgraded MySQL snapshot into PostgreSQL.';

    /**
     * Validation is the default; explicit execution preserves the target migration history.
     */
    public function handle(DatabaseTransferRepository $repository): int
    {
        $report = $repository->transfer((bool) $this->option('execute'), (bool) $this->option('replace-schema-defaults'));

        $this->table(['Table', 'Rows'], collect($report)->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->info($this->option('execute') ? 'Transfer committed; foreign keys and row counts verified.' : 'Schemas validated. No data was written.');

        return self::SUCCESS;
    }
}
