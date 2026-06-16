<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DefaultNonAdminPasswordSeeder extends Seeder
{
    /**
     * Assign the default login password to non-admin users that do not already have one.
     * Safe to run in stage, production, or local via:
     *   php artisan db:seed --class=DefaultNonAdminPasswordSeeder --force
     */
    public function run(): void
    {
        Artisan::call('users:set-default-password', [
            '--force' => true,
        ]);

        $this->command?->info(trim(Artisan::output()));
    }
}
