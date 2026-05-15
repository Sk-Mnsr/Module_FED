<?php

namespace App\Support;

use InvalidArgumentException;

class CoficarteCardNumberGenerator
{
    public static function normalize(string $numero): string
    {
        return trim(preg_replace('/\s+/', ' ', $numero));
    }

    /**
     * @return list<string>
     */
    public static function generateFromFirst(string $first, int $quantity): array
    {
        $norm = self::normalize($first);
        if ($quantity < 1) {
            return [];
        }
        $parts = explode(' ', $norm);
        if ($parts === [''] || $parts === []) {
            return [];
        }
        $last = array_pop($parts);
        if (! ctype_digit((string) $last)) {
            throw new InvalidArgumentException('Impossible de déduire une plage : le dernier segment du numéro doit être numérique.');
        }
        $len = strlen((string) $last);
        $prefix = implode(' ', $parts);
        $start = (int) $last;
        $out = [];
        for ($i = 0; $i < $quantity; $i++) {
            $n = $start + $i;
            $segment = str_pad((string) $n, $len, '0', STR_PAD_LEFT);
            if (strlen($segment) > $len) {
                throw new InvalidArgumentException('La quantité dépasse la plage numérique disponible.');
            }
            $out[] = $prefix !== '' ? $prefix.' '.$segment : $segment;
        }

        return $out;
    }
}
