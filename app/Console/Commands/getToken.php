<?php

namespace App\Console\Commands;

use App\Services\MicrosoftTokenService;
use Illuminate\Console\Command;

class getToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            MicrosoftTokenService::refresh();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Token actualizado correctamente.');
    }
}
