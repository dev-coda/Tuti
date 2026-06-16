<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetDefaultPasswordForNonAdminUsers extends Command
{
    protected $signature = 'users:set-default-password
                            {--password=Tendero2026 : Password to assign to non-admin users}
                            {--dry-run : Show how many users would be updated without writing}
                            {--force : Run without confirmation in production}';

    protected $description = 'Set a default login password for non-admin users that do not already have a password';

    public function handle(): int
    {
        $password = (string) $this->option('password');
        $dryRun = (bool) $this->option('dry-run');

        if ($password === '') {
            $this->error('Password cannot be empty.');

            return self::FAILURE;
        }

        if (
            !$dryRun
            && $this->laravel->environment('production')
            && !$this->option('force')
            && !$this->confirm('This will set the default password for non-admin users without an existing password. Continue?')
        ) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $query = User::query()
            ->whereDoesntHave('roles', function ($roleQuery) {
                $roleQuery->where('name', 'admin');
            })
            ->where(function ($passwordQuery) {
                $passwordQuery->whereNull('password')
                    ->orWhere('password', '');
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No non-admin users without a password were found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$total} non-admin user(s) without a password would receive the default password.");

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(200, function ($users) use ($password, &$updated) {
            foreach ($users as $user) {
                if (!$this->userNeedsDefaultPassword($user)) {
                    continue;
                }

                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => true,
                ])->save();
                $updated++;
            }
        });

        $this->info("Set default password for {$updated} non-admin user(s) without an existing password.");

        return self::SUCCESS;
    }

    private function userNeedsDefaultPassword(User $user): bool
    {
        $rawPassword = $user->getRawOriginal('password');

        return $rawPassword === null || $rawPassword === '';
    }
}
