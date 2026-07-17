<?php

namespace App\Console\Commands;

use App\Jobs\SyncProductDimensions;
use Illuminate\Console\Command;

class SyncProductDimensionsCommand extends Command
{
    protected $signature = 'products:sync-dimensions
                            {--sku= : Sync only the article with this Dynamics ItemId}
                            {--now : Run synchronously instead of queueing}';

    protected $description = 'Sync product weight and dimensions from the Dynamics ObtenerArticulos webservice';

    public function handle(): int
    {
        $sku = $this->option('sku') ?: null;

        if ($this->option('now')) {
            (new SyncProductDimensions($sku))->handle();
            $this->info('Product dimension sync executed.');
        } else {
            SyncProductDimensions::dispatch($sku);
            $this->info('Product dimension sync job dispatched.');
        }

        return self::SUCCESS;
    }
}
