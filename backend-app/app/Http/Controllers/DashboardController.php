<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceCheck;
use App\Models\DeviceGroup;
use App\Models\User;
use App\Services\DeviceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function checkDevices(DeviceMonitor $monitor): JsonResponse
    {
        Device::query()->get()->each(fn (Device $device) => $monitor->check($device));

        return response()->json(['message' => 'Device checks completed.']);
    }

    public function index(): JsonResponse
    {
        $devices = Device::query()->latest('last_checked_at')->get();
        $alerts = Alert::query()->with('device')->where('acknowledged', false)->latest()->get();

        return response()->json([
            'summary' => [
                'total_devices' => $devices->count(),
                'operational_devices' => $devices->where('status', 'online')->count(),
                'open_alerts' => $alerts->count(),
                'average_latency_ms' => round($devices->whereNotNull('latency_ms')->avg('latency_ms') ?? 0),
                'network_uptime' => round($devices->avg('uptime') ?? 0, 2),
            ],
            'devices' => $devices->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'ip' => $device->ip_address,
                'status' => $device->status,
                'statusLabel' => match ($device->status) {
                    'online' => 'Operational',
                    'warning' => 'Degraded',
                    default => 'Unreachable',
                },
                'uptime' => number_format($device->uptime, 2).'%',
                'latency' => $device->latency_ms === null ? '--' : $device->latency_ms.' ms',
                'updated' => $device->last_checked_at?->diffForHumans() ?? 'Never',
            ])->values(),
            'alerts' => $alerts->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'message' => $alert->message,
                'severity' => $alert->severity,
                'device' => $alert->device?->name,
                'created_at' => $alert->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $request->validate(['range' => ['sometimes', 'in:24h,7d,30d']]);
        $devices = Device::query()->get();
        $alerts = Alert::query()->get();
        $range = $request->input('range', '30d');
        $since = now()->sub($range === '24h' ? '24 hours' : ($range === '7d' ? '7 days' : '30 days'));
        $history = DeviceCheck::query()->where('checked_at', '>=', $since)->orderBy('checked_at')->get()->groupBy(fn (DeviceCheck $check) => $check->checked_at->format('Y-m-d H:i'));

        return response()->json([
            'range' => $range,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_devices' => $devices->count(),
                'availability' => round($devices->avg('uptime') ?? 0, 2),
                'average_latency_ms' => round($devices->whereNotNull('latency_ms')->avg('latency_ms') ?? 0),
                'open_alerts' => $alerts->where('acknowledged', false)->count(),
            ],
            'status_breakdown' => [
                'online' => $devices->where('status', 'online')->count(),
                'warning' => $devices->where('status', 'warning')->count(),
                'offline' => $devices->where('status', 'offline')->count(),
            ],
            'alert_breakdown' => [
                'high' => $alerts->where('severity', 'high')->where('acknowledged', false)->count(),
                'medium' => $alerts->where('severity', 'medium')->where('acknowledged', false)->count(),
                'low' => $alerts->where('severity', 'low')->where('acknowledged', false)->count(),
            ],
            'history' => $history->map(fn ($checks, string $timestamp) => [
                'timestamp' => $timestamp,
                'availability' => round($checks->whereIn('status', ['online', 'warning'])->count() / max($checks->count(), 1) * 100, 2),
                'average_latency_ms' => round($checks->whereNotNull('latency_ms')->avg('latency_ms') ?? 0),
            ])->values(),
        ]);
    }

    public function storeDevice(Request $request): JsonResponse
    {
        $device = Device::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
        ] + [
            'status' => ['sometimes', 'in:online,warning,offline'],
            'uptime' => ['sometimes', 'numeric', 'between:0,100'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
            'device_group_id' => ['nullable', 'exists:device_groups,id'],
        ]));

        return response()->json($device, 201);
    }

    public function updateDevice(Request $request, Device $device): JsonResponse
    {
        $device->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:255'],
            'ip_address' => ['sometimes', 'ip'],
            'device_group_id' => ['nullable', 'exists:device_groups,id'],
        ]));

        return response()->json($device->fresh());
    }

    public function destroyDevice(Device $device): JsonResponse
    {
        $device->delete();

        return response()->json(['message' => 'Device deleted.']);
    }

    public function groups(): JsonResponse
    {
        return response()->json(DeviceGroup::query()->withCount('devices')->with('devices:id,device_group_id,name,status')->orderBy('name')->get());
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $group = DeviceGroup::create($request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:device_groups,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        return response()->json($group->loadCount('devices'), 201);
    }

    public function users(): JsonResponse
    {
        return response()->json(User::query()->select('id', 'name', 'email', 'role', 'created_at')->orderBy('name')->get());
    }

    public function storeUser(Request $request): JsonResponse
    {
        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:administrator,operator'],
        ]));

        return response()->json($user->only(['id', 'name', 'email', 'role']), 201);
    }

    public function acknowledge(Alert $alert): JsonResponse
    {
        $alert->update(['acknowledged' => true, 'resolved_at' => now()]);

        return response()->json(['message' => 'Alert acknowledged.']);
    }
}
