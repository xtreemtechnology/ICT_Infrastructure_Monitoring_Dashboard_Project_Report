<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\DeviceMonitor;
use Illuminate\Console\Command;

class PollDevices extends Command
{
    protected $signature = 'monitor:devices';

    protected $description = 'Poll all registered devices and record their latest metrics';

    public function handle(DeviceMonitor $monitor): int
    {
        Device::query()->get()->each(fn (Device $device) => $monitor->check($device));

        $this->info('Device polling completed.');

        return self::SUCCESS;
    }
}
