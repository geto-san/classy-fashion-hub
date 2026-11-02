<?php

namespace ClassyFashion\Console\Commands;

use ClassyFashion\Repositories\DeploymentRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AssertDeploymentReady extends Command
{
    protected $signature = 'classy:assert-deployment-ready';

    protected $description = 'Verify imported store data and disable the browser installer for environment-based deployments.';

    /**
     * Recreate the disposable installed marker only after verifying persistent data.
     */
    public function handle(DeploymentRepository $repository): int
    {
        $repository->assertReady();

        File::put(storage_path('installed'), '');

        $this->info('Imported store is ready; browser installer disabled.');

        return self::SUCCESS;
    }
}
