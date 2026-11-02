<?php

namespace App\Tests\Util;

use App\Util\Iban;
use PHPUnit\Framework\TestCase;

class IbanTest extends TestCase
{
    public function testAcceptsValidIbansWrittenAnyWay(): void
    {
        self::assertTrue(Iban::isValid('ES91 2100 0418 4502 0005 1332'));
        self::assertTrue(Iban::isValid('es9121000418450200051332'));
        self::assertTrue(Iban::isValid('ES91-2100-0418-4502-0005-1332'));
        self::assertTrue(Iban::isValid('DE89 3704 0044 0532 0130 00'));
    }

    public function testCatchesTheUsualCopyingMistakes(): void
    {
        self::assertFalse(Iban::isValid('ES91 2100 0418 4502 0005 1333'), 'one wrong digit');
        self::assertFalse(Iban::isValid('ES91 2100 0418 4502 0005 3132'), 'two swapped digits');
        self::assertFalse(Iban::isValid('ES91 2100 0418 4502 0005 133'), 'a digit missing (wrong length for ES)');
        self::assertFalse(Iban::isValid('2100 0418 4502 0005 1332'), 'no country code');
        self::assertFalse(Iban::isValid(''));
    }

    public function testNormalizesAndFormats(): void
    {
        self::assertSame('ES9121000418450200051332', Iban::normalize(' es91 2100-0418 4502 0005 1332 '));
        self::assertSame('ES91 2100 0418 4502 0005 1332', Iban::format('ES9121000418450200051332'));
    }
}
