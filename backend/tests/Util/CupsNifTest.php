<?php

namespace App\Tests\Util;

use App\Util\Cups;
use App\Util\Nif;
use PHPUnit\Framework\TestCase;

class CupsNifTest extends TestCase
{
    public function testCupsCheckLettersCatchAMisreadDigit(): void
    {
        self::assertTrue(Cups::isValid('ES0021000012345678LB'));
        self::assertTrue(Cups::isValid('es 0021 0000 1234 5678 lb 0F'), 'spaces, lower case and the border-point suffix');
        self::assertFalse(Cups::isValid('ES0021000012345679LB'), 'one digit misread');
        self::assertFalse(Cups::isValid('ES0021000012345678BL'), 'letters swapped');
        self::assertFalse(Cups::isValid('0021000012345678LB'), 'no ES prefix');
    }

    public function testNifDniNieAndCompanyFormat(): void
    {
        self::assertTrue(Nif::isValid('12345678Z'));
        self::assertTrue(Nif::isValid('12.345.678-z'));
        self::assertTrue(Nif::isValid('X1234567L'));
        self::assertFalse(Nif::isValid('12345678A'), 'wrong DNI letter');
        self::assertTrue(Nif::isValid('B12345678'), 'company CIF: format only');
        self::assertFalse(Nif::isValid('1234'));
    }
}
