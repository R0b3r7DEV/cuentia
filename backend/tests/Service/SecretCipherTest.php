<?php

namespace App\Tests\Service;

use App\Service\SecretCipher;
use PHPUnit\Framework\TestCase;

class SecretCipherTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $cipher = new SecretCipher('some-app-secret');
        $secret = 'sk-ant-abc123-VERY-secret';

        $encrypted = $cipher->encrypt($secret);
        self::assertNotSame($secret, $encrypted);
        self::assertStringNotContainsString($secret, $encrypted);
        self::assertSame($secret, $cipher->decrypt($encrypted));
    }

    public function testCiphertextDiffersEachTime(): void
    {
        $cipher = new SecretCipher('some-app-secret');
        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same')); // random IV
    }

    public function testDecryptRejectsTamperedOrForeignData(): void
    {
        $cipher = new SecretCipher('secret-a');
        self::assertNull($cipher->decrypt('not-valid-base64-or-cipher'));
        // a value encrypted with a different app secret can't be decrypted
        self::assertNull($cipher->decrypt((new SecretCipher('secret-b'))->encrypt('x')));
    }
}
