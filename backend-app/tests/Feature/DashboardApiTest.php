<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_device_and_alert_summary(): void
    {
        $this->actingAs(User::factory()->create());
        $device = Device::create([
            'name' => 'Core Router',
            'type' => 'Network gateway',
            'ip_address' => '10.22.0.1',
            'status' => 'online',
            'uptime' => 99.5,
            'latency_ms' => 12,
        ]);
        Alert::create(['device_id' => $device->id, 'severity' => 'high', 'message' => 'Router check failed']);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.total_devices', 1)
            ->assertJsonPath('summary.operational_devices', 1)
            ->assertJsonPath('summary.open_alerts', 1)
            ->assertJsonPath('devices.0.name', 'Core Router')
            ->assertJsonPath('alerts.0.message', 'Router check failed');
    }

    public function test_device_creation_requires_valid_device_data(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/devices', ['name' => 'Incomplete device'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'ip_address']);

        $this->postJson('/api/devices', [
            'name' => 'Science Lab AP-04',
            'type' => 'Wireless access point',
            'ip_address' => '10.22.4.24',
            'latency_ms' => 18,
        ])->assertCreated()->assertJsonPath('name', 'Science Lab AP-04');
    }

    public function test_administrators_can_update_and_delete_devices(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $device = Device::create(['name' => 'Old name', 'type' => 'Switch', 'ip_address' => '10.22.2.12']);

        $this->patchJson("/api/devices/{$device->id}", ['name' => 'Updated switch'])
            ->assertOk()->assertJsonPath('name', 'Updated switch');
        $this->deleteJson("/api/devices/{$device->id}")->assertOk();
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_alert_can_be_acknowledged(): void
    {
        $this->actingAs(User::factory()->create());
        $device = Device::create(['name' => 'Switch', 'type' => 'Network switch', 'ip_address' => '10.22.2.12']);
        $alert = Alert::create(['device_id' => $device->id, 'severity' => 'medium', 'message' => 'High latency']);

        $this->patchJson("/api/alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJson(['message' => 'Alert acknowledged.']);

        $this->assertDatabaseHas('alerts', ['id' => $alert->id, 'acknowledged' => true]);
    }

    public function test_report_returns_current_monitoring_snapshot(): void
    {
        $this->actingAs(User::factory()->create());
        Device::create([
            'name' => 'Core Router',
            'type' => 'Network gateway',
            'ip_address' => '10.22.0.1',
            'status' => 'online',
            'uptime' => 99.5,
            'latency_ms' => 12,
        ]);

        $this->getJson('/api/reports?range=7d')
            ->assertOk()
            ->assertJsonPath('range', '7d')
            ->assertJsonPath('summary.total_devices', 1)
            ->assertJsonPath('status_breakdown.online', 1);
    }

    public function test_device_groups_can_be_listed_and_created(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        DeviceGroup::create(['name' => 'Campus network', 'description' => 'Core infrastructure']);

        $this->getJson('/api/device-groups')
            ->assertOk()
            ->assertJsonPath('0.name', 'Campus network')
            ->assertJsonPath('0.devices_count', 0);

        $this->postJson('/api/device-groups', ['name' => 'Learning services'])
            ->assertCreated()
            ->assertJsonPath('name', 'Learning services');
    }

    public function test_team_members_can_be_listed_and_created(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));

        $this->getJson('/api/team')
            ->assertOk()
            ->assertJsonCount(1);

        $this->postJson('/api/team', [
            'name' => 'Campus Operator',
            'email' => 'operator@example.com',
            'password' => 'secure-password',
            'role' => 'operator',
        ])->assertCreated()->assertJsonPath('role', 'operator');
    }

    public function test_operators_cannot_manage_groups_or_team_members(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'operator']));

        $this->postJson('/api/device-groups', ['name' => 'Restricted group'])->assertForbidden();
        $this->postJson('/api/team', [
            'name' => 'Restricted user',
            'email' => 'restricted@example.com',
            'password' => 'secure-password',
            'role' => 'operator',
        ])->assertForbidden();
    }
}
