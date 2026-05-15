<?php

namespace App\Support;

use App\Models\CoficarteTransfer;

final class CoficarteBonNumeroGenerator
{
    public static function next(): string
    {
        $year = now()->year;
        $prefix = "BON-{$year}-";

        $last = CoficarteTransfer::query()
            ->whereNotNull('bon_numero')
            ->where('bon_numero', 'like', $prefix.'%')
            ->orderByDesc('bon_numero')
            ->value('bon_numero');

        $next = 1;
        if ($last !== null && preg_match('/-(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
