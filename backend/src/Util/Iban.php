<?php

namespace App\Util;

/**
 * IBAN checks (ISO 13616): country code, length and the mod-97 check digits, which catch a mistyped
 * digit or two swapped digits — exactly the slips made when copying an account number by hand.
 * ES: Comprobación de IBAN: país, longitud y dígitos de control mod-97, que detectan un dígito mal copiado o
 * dos dígitos cambiados de sitio — justo los errores al copiar una cuenta a mano.
 */
final class Iban
{
    /** Lengths of the countries an electrician in Spain will realistically see; others: 15–34. */
    private const LENGTHS = ['ES' => 24, 'PT' => 25, 'FR' => 27, 'DE' => 22, 'IT' => 27, 'AD' => 24, 'GB' => 22, 'NL' => 18, 'BE' => 16];

    /** "es91 2100-0418 4502 0005 1332" → "ES9121000418450200051332". */
    public static function normalize(string $iban): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $iban) ?? '');
    }

    public static function isValid(string $iban): bool
    {
        $iban = self::normalize($iban);
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        $country = substr($iban, 0, 2);
        if (isset(self::LENGTHS[$country]) && strlen($iban) !== self::LENGTHS[$country]) {
            return false;
        }

        // Move the first four characters to the end, letters → numbers (A=10 … Z=35), and the number mod
        // 97 must be 1. Done in chunks so it never overflows an integer.
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $digits = '';
        foreach (str_split($rearranged) as $ch) {
            $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rest = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $rest = (int) ($rest . $chunk) % 97;
        }

        return $rest === 1;
    }

    /** "ES9121000418450200051332" → "ES91 2100 0418 4502 0005 1332". */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }
}
