<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class EnsureAdminUser extends Command
{
    protected $signature = 'app:ensure-admin';

    protected $description = 'Create or update the deployment administrator from environment variables';

    public function handle(): int
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->error('ADMIN_EMAIL and ADMIN_PASSWORD must be configured.');

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make($password),
                'role' => 'administrator',
            ],
        );

        $this->info("Administrator ready: {$email}");

        return self::SUCCESS;
    }
}
