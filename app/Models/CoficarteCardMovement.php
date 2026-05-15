<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoficarteCardMovement extends Model
{
    public $timestamps = false;

    protected $table = 'coficarte_card_movements';

    protected $fillable = [
        'coficarte_card_id',
        'event_type',
        'meta',
        'actor_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CoficarteCard::class, 'coficarte_card_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
