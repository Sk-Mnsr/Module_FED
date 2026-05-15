<?php

namespace App\Support;

use App\Models\CoficarteRecharge;
use App\Models\CoficarteSale;
use Illuminate\Support\Str;

/**
 * Code unique saisi par le caissier pour retrouver une vente ou une recharge en attente.
 */
final class CoficarteEncaissementCode
{
    public static function normalize(?string $raw): string
    {
        return strtoupper(trim((string) $raw));
    }

    /**
     * Préfixe V = vente, R = recharge (évite collision entre les deux tables).
     */
    public static function generateForVente(): string
    {
        return self::unique('V-', fn (string $code) => CoficarteSale::query()->where('encaissement_code', $code)->exists());
    }

    public static function generateForRecharge(): string
    {
        return self::unique('R-', fn (string $code) => CoficarteRecharge::query()->where('encaissement_code', $code)->exists());
    }

    private static function unique(string $prefix, callable $exists): string
    {
        for ($i = 0; $i < 30; $i++) {
            $code = $prefix.strtoupper(Str::random(8));
            if (! $exists($code)) {
                return $code;
            }
        }

        return $prefix.strtoupper(Str::ulid());
    }
}
