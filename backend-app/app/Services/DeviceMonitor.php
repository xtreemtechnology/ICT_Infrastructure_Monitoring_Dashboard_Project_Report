<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceCheck;
use Symfony\Component\Process\Process;

class DeviceMonitor
{
    public function check(Device $device): Device
    {
        $checkedAt = now();
        if (str_contains($device->ip_address, '/')) {
            $device->update(['status' => 'warning', 'latency_ms' => null, 'last_checked_at' => $checkedAt]);
            DeviceCheck::create(['device_id' => $device->id, 'status' => 'warning', 'latency_ms' => null, 'checked_at' => $checkedAt]);

            return $device->refresh();
        }

        $arguments = PHP_OS_FAMILY === 'Windows'
            ? ['ping', '-n', '1', '-w', '1000', $device->ip_address]
            : ['ping', '-c', '1', '-W', '1', $device->ip_address];
        $process = new Process($arguments);
        $process->setTimeout(5);
        $process->run();
        preg_match('/time[=<]\s*(\d+)/i', $process->getOutput(), $matches);
        $latency = isset($matches[1]) ? (int) $matches[1] : null;
        $reachable = $process->isSuccessful();

        $device->update([
            'status' => $reachable ? ($latency !== null && $latency > 80 ? 'warning' : 'online') : 'offline',
            'latency_ms' => $latency,
            'last_checked_at' => $checkedAt,
        ]);
        DeviceCheck::create(['device_id' => $device->id, 'status' => $device->status, 'latency_ms' => $latency, 'checked_at' => $checkedAt]);

        return $device->refresh();
    }
}
