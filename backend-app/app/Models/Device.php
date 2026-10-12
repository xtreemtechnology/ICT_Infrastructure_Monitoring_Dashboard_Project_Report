<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'name', 'type', 'ip_address', 'status', 'uptime', 'latency_ms', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'uptime' => 'float',
            'last_checked_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(DeviceCheck::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DeviceGroup::class, 'device_group_id');
    }
}
