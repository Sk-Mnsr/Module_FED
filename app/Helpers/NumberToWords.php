<?php

namespace App\Helpers;

class NumberToWords
{
    private static array $units = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private static array $tens = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante',
        'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix',
    ];

    public static function toFrench(float $number, string $currency = 'CFA'): string
    {
        $intPart = (int) floor($number);
        $decPart = (int) round(($number - $intPart) * 100);

        $words = self::convert($intPart);
        $words = ucfirst($words);

        if ($decPart > 0) {
            $words .= ' virgule ' . self::convert($decPart);
        }

        return $words . ' ' . $currency;
    }

    private static function convert(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }

        $result = '';

        if ($n >= 1_000_000_000) {
            $billions = (int) floor($n / 1_000_000_000);
            $result .= self::convertLessThanThousand($billions);
            $result .= $billions > 1 ? ' milliards ' : ' milliard ';
            $n %= 1_000_000_000;
        }

        if ($n >= 1_000_000) {
            $millions = (int) floor($n / 1_000_000);
            $result .= self::convertLessThanThousand($millions);
            $result .= $millions > 1 ? ' millions ' : ' million ';
            $n %= 1_000_000;
        }

        if ($n >= 1_000) {
            $thousands = (int) floor($n / 1_000);
            if ($thousands > 1) {
                $result .= self::convertLessThanThousand($thousands) . ' ';
            }
            $result .= 'mille ';
            $n %= 1_000;
        }

        if ($n > 0) {
            $result .= self::convertLessThanThousand($n);
        }

        return trim($result);
    }

    private static function convertLessThanThousand(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        $result = '';

        if ($n >= 100) {
            $hundreds = (int) floor($n / 100);
            $result .= $hundreds === 1 ? 'cent ' : self::convertLessThanThousand($hundreds) . ' cent ';
            $n %= 100;
        }

        if ($n >= 80 && $n < 90) {
            $result .= 'quatre-vingt';
            $n -= 80;
            $result .= $n > 0 ? '-' : ' ';
        } elseif ($n >= 70 && $n < 80) {
            $result .= 'soixante-' . self::$units[$n - 60];
            return trim($result);
        } elseif ($n >= 90 && $n < 100) {
            $result .= 'quatre-vingt-' . self::$units[$n - 80];
            return trim($result);
        } elseif ($n >= 20) {
            $tens = (int) floor($n / 10);
            $result .= self::$tens[$tens];
            $n %= 10;
            if ($n === 1 && $tens !== 8) {
                $result .= '-et-un';
            } elseif ($n > 0) {
                $result .= '-' . self::$units[$n];
            }
            $result .= ' ';
        } else {
            $result .= self::$units[$n] . ' ';
        }

        return trim($result);
    }
}
