<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'administrator',
        ]);
        User::factory()->create([
            'name' => 'Campus Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('password'),
            'role' => 'operator',
        ]);

        $groups = collect([
            'Campus network' => DeviceGroup::create(['name' => 'Campus network', 'description' => 'Core network infrastructure']),
            'Learning services' => DeviceGroup::create(['name' => 'Learning services', 'description' => 'Teaching and learning systems']),
        ]);

        $devices = collect([
            ['name' => 'Core Router · Main Campus', 'type' => 'Network gateway', 'ip_address' => '10.22.0.1', 'status' => 'online', 'uptime' => 99.98, 'latency_ms' => 12, 'device_group_id' => $groups['Campus network']->id],
            ['name' => 'LMS Production Server', 'type' => 'Application server', 'ip_address' => '10.22.4.18', 'status' => 'online', 'uptime' => 99.96, 'latency_ms' => 24, 'device_group_id' => $groups['Learning services']->id],
            ['name' => 'Library Wi-Fi Cluster', 'type' => 'Wireless access point', 'ip_address' => '10.22.8.0/24', 'status' => 'warning', 'uptime' => 98.71, 'latency_ms' => 86, 'device_group_id' => $groups['Campus network']->id],
            ['name' => 'Admin Block Switch 02', 'type' => 'Network switch', 'ip_address' => '10.22.2.12', 'status' => 'offline', 'uptime' => 94.20, 'latency_ms' => null, 'device_group_id' => $groups['Campus network']->id],
        ])->map(fn (array $device) => Device::create($device + ['last_checked_at' => now()]));

        Alert::create(['device_id' => $devices[3]->id, 'severity' => 'high', 'message' => 'Admin Block Switch 02 is unreachable']);
        Alert::create(['device_id' => $devices[2]->id, 'severity' => 'medium', 'message' => 'Library Wi-Fi latency above threshold']);
    }
}
