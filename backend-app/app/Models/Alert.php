<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = ['device_id', 'severity', 'message', 'acknowledged', 'resolved_at'];

    protected function casts(): array
    {
        return ['acknowledged' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
