<?php

namespace App\Util;

/**
 * CUPS (Código Universal del Punto de Suministro): "ES" + 16 digits + 2 check letters, optionally followed by
 * a 2-character border-point suffix (20 or 22 characters). The check letters come from the 16 digits mod 529,
 * so a mistyped digit is caught — the same idea as the NIF letter.
 *
 * ES: CUPS: "ES" + 16 dígitos + 2 letras de control, más un sufijo opcional de 2 caracteres (20 o 22). Las
 * letras salen de los 16 dígitos módulo 529, así que un dígito mal copiado se detecta, como la letra del NIF.
 */
final class Cups
{
    private const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    public static function normalize(string $cups): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cups) ?? '');
    }

    public static function isValid(string $cups): bool
    {
        $c = self::normalize($cups);
        if (!preg_match('/^ES(\d{16})([A-Z]{2})([0-9][A-Z])?$/', $c, $m)) {
            return false;
        }
        $rest = (int) $m[1] % 529; // 16 digits fit in a 64-bit integer
        $expected = self::LETTERS[intdiv($rest, 23)] . self::LETTERS[$rest % 23];

        return $m[2] === $expected;
    }
}
