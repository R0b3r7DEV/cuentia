<?php

namespace App\Tests\Service;

use App\Service\ImageSanitizer;
use PHPUnit\Framework\TestCase;

class ImageSanitizerTest extends TestCase
{
    public static function jpeg(int $w = 40, int $h = 30): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 60, 20));
        ob_start();
        imagejpeg($img);

        return (string) ob_get_clean();
    }

    public static function png(int $w = 40, int $h = 20, bool $alpha = false): string
    {
        $img = imagecreatetruecolor($w, $h);
        if ($alpha) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /** A JPEG with an APP1/EXIF block right after SOI, standing in for a phone photo with GPS. */
    private function jpegWithExif(): string
    {
        $exif = "Exif\0\0" . 'GPSLatitude=39.98N;GPSLongitude=0.05W';
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $jpeg = self::jpeg();

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    public function testPhotoIsReEncodedAndLosesItsExifGpsData(): void
    {
        $original = $this->jpegWithExif();
        self::assertStringContainsString('GPSLatitude', $original, 'the fixture really carries the metadata');

        $clean = (new ImageSanitizer())->photo($original);

        self::assertStringNotContainsString('GPSLatitude', $clean['jpeg']);
        self::assertStringNotContainsString('Exif', $clean['jpeg']);
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($clean['jpeg'])[2]);
        self::assertSame([40, 30], [$clean['width'], $clean['height']]);
    }

    public function testPngPhotoComesOutAsJpeg(): void
    {
        $clean = (new ImageSanitizer())->photo(self::png(30, 30, true));
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($clean['jpeg'])[2]);
    }

    public function testBigPhotoIsScaledDownToTheMaximumSide(): void
    {
        $clean = (new ImageSanitizer())->photo(self::jpeg(3200, 1600));
        self::assertSame([1600, 800], [$clean['width'], $clean['height']]);
    }

    public function testSomethingThatIsNotAnImageIsRejectedWhateverItClaimsToBe(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('invalid_image'));
        (new ImageSanitizer())->photo("<?php system(\$_GET['c']); ?>");
    }

    /**
     * A tiny PNG whose header claims 20 000 × 20 000 px (400 Mpx): rejected from the header alone, before
     * GD tries to allocate it ("decompression bomb").
     */
    public function testDecompressionBombIsRejectedBeforeDecoding(): void
    {
        $png = self::png(1, 1);
        // IHDR width/height live at bytes 16-23 (getimagesize does not check the chunk CRC).
        $bomb = substr($png, 0, 16) . pack('NN', 20000, 20000) . substr($png, 24);

        $this->expectExceptionObject(new \InvalidArgumentException('image_too_large'));
        (new ImageSanitizer())->photo($bomb);
    }

    public function testSignatureAcceptsAPngDataUrlAndKeepsTransparency(): void
    {
        $out = (new ImageSanitizer())->signature('data:image/png;base64,' . base64_encode(self::png(300, 120, true)));

        $info = getimagesizefromstring($out);
        self::assertSame(IMAGETYPE_PNG, $info[2]);
        $img = imagecreatefromstring($out);
        self::assertSame(127, (imagecolorat($img, 0, 0) >> 24) & 0x7F, 'the background stays transparent');
    }

    public function testSignatureMustBeAPng(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('invalid_signature'));
        (new ImageSanitizer())->signature('data:image/png;base64,' . base64_encode(self::jpeg()));
    }

    public function testSignatureWithBrokenBase64IsRejected(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('invalid_signature'));
        (new ImageSanitizer())->signature('data:image/png;base64,%%%not-base64%%%');
    }

    public function testOversizedSignatureCanvasIsRejected(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('signature_too_large'));
        (new ImageSanitizer())->signature(base64_encode(self::png(2400, 200)));
    }
}
