<?php

namespace ClassyFashion\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Theme\Repositories\SectionRepository;

/**
 * Restore the pushed storefront sections without replacing their content.
 */
class SetupUgandaHomepage extends Command
{
    /**
     * The console command signature.
     */
    protected $signature = 'classy:setup-homepage';

    /**
     * The console command description.
     */
    protected $description = 'Restore the original Bagisto homepage section layout.';

    /**
     * Re-enable the original homepage sections disabled by the local redesign.
     */
    public function handle(SectionRepository $sections): int
    {
        $sections->massUpdateStatus(['status' => 1], range(1, 13));

        return self::SUCCESS;
    }
}
