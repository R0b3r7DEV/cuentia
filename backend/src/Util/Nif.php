<?php

namespace App\Util;

/**
 * Spanish NIF check for people: DNI (8 digits + letter) and NIE (X/Y/Z + 7 digits + letter), letter = number
 * mod 23. Company CIFs (letter + 7 digits + check) only get a format check. Used to flag, never to reject:
 * a person can always correct what was read from a photo.
 *
 * ES: Comprobación del NIF de personas: DNI y NIE, letra = número mod 23. A los CIF de empresa solo se les
 * mira el formato. Sirve para avisar, nunca para rechazar.
 */
final class Nif
{
    private const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    public static function normalize(string $nif): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nif) ?? '');
    }

    public static function isValid(string $nif): bool
    {
        $n = self::normalize($nif);
        if (preg_match('/^([XYZ]?)(\d{7,8})([A-Z])$/', $n, $m)) {
            $digits = ($m[1] !== '' ? (string) strpos('XYZ', $m[1]) : '') . $m[2];
            if (strlen($digits) !== 8) {
                return false;
            }

            return self::LETTERS[(int) $digits % 23] === $m[3];
        }

        return (bool) preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$/', $n);
    }
}
