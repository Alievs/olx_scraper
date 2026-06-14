<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use HasUuids;

    protected $fillable = [
        'url',
        'title',
        'current_price',
        'currency',
        'is_active',
        'last_checked_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
