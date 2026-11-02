<?php

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Quote;
use App\Entity\User;
use App\Service\QuotePdf;
use PHPUnit\Framework\TestCase;

class QuotePdfTest extends TestCase
{
    /** The issuer is the fiscal name with NIF, address and phone — it used to print the login e-mail. */
    public function testIssuerIsTheFiscalProfileNotTheLoginEmail(): void
    {
        $user = (new User())->setEmail('login@cuentia.local')->setTaxId('12345678Z')
            ->setBusinessName('José Pérez García')->setFiscalAddress('C/ Major 1, Castelló')->setPhone('600 123 456');
        $quote = (new Quote())->setUser($user)
            ->setCustomer((new Customer())->setUser($user)->setName('ACME')->setTaxId('B1'))
            ->setSeries('P2026')->setNumber(1);

        $html = (new QuotePdf())->html($quote);

        self::assertStringContainsString('José Pérez García', $html);
        self::assertStringContainsString('C/ Major 1, Castelló', $html);
        self::assertStringContainsString('Tel. 600 123 456', $html);
        self::assertStringNotContainsString('login@cuentia.local', $html);
    }
}
