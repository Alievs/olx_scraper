<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    protected $primaryKey = 'chat_id';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = ['chat_id'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'chat_id', 'chat_id');
    }
}
