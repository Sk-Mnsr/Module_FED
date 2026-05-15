<?php

namespace App\Support;

use App\Models\CoficarteCard;
use App\Models\CoficarteCardMovement;

final class CoficarteMovementLogger
{
    public static function log(CoficarteCard $card, string $eventType, array $meta = [], ?int $actorId = null): void
    {
        CoficarteCardMovement::query()->create([
            'coficarte_card_id' => $card->id,
            'event_type' => $eventType,
            'meta' => $meta !== [] ? $meta : null,
            'actor_id' => $actorId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }
}
