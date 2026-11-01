<?php

namespace App\Service;

/**
 * Turns untrusted uploaded images into safe ones by decoding and re-encoding them with GD.
 *
 * Never trust the browser: the declared MIME type and the file name are ignored, the bytes are sniffed, the
 * pixel count is capped BEFORE decoding (a 1 KB PNG can declare 50 000 × 50 000 px and eat the server's
 * memory — a "decompression bomb"), and only the re-encoded pixels are kept. Re-encoding drops every
 * metadata block, including EXIF GPS: a photo taken in a client's home must not carry its coordinates.
 *
 * ES: Convierte imágenes subidas (no fiables) en seguras, decodificándolas y volviéndolas a codificar con GD.
 * Nunca se fía del navegador: se ignoran el tipo MIME declarado y el nombre, se miran los bytes, se limita el
 * número de píxeles ANTES de decodificar (un PNG de 1 KB puede declarar 50 000 × 50 000 px y agotar la
 * memoria: una "bomba de descompresión") y solo se guardan los píxeles re-codificados. Al re-codificar se
 * pierden todos los metadatos, incluido el GPS del EXIF: una foto hecha en casa de un cliente no debe llevar
 * sus coordenadas.
 *
 * Errors are thrown as InvalidArgumentException carrying a fixed code, safe to show to the client.
 * ES: Los errores salen como InvalidArgumentException con un código fijo, seguro para enseñarlo al cliente.
 */
class ImageSanitizer
{
    /** Largest upload accepted, before re-encoding. / Subida más grande aceptada, antes de re-codificar. */
    public const MAX_UPLOAD_BYTES = 4 * 1024 * 1024;

    /** Pixel cap checked before decoding. / Tope de píxeles, comprobado antes de decodificar. */
    public const MAX_PIXELS = 40_000_000;

    /** Longest side of a stored photo. / Lado mayor de una foto guardada. */
    public const PHOTO_MAX_SIDE = 1600;

    public const PHOTO_QUALITY = 82;

    public const SIGNATURE_MAX_BYTES = 300 * 1024;
    public const SIGNATURE_MAX_WIDTH = 2000;
    public const SIGNATURE_MAX_HEIGHT = 1000;

    /**
     * A site photo (JPEG or PNG in) → a clean JPEG out, no larger than PHOTO_MAX_SIDE.
     *
     * @return array{jpeg:string, width:int, height:int}
     */
    public function photo(string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('image_too_large');
        }
        [$width, $height] = $this->inspect($bytes, [IMAGETYPE_JPEG, IMAGETYPE_PNG]);

        $image = $this->decode($bytes);
        $scale = min(1, self::PHOTO_MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            if ($resized === false) {
                throw new \InvalidArgumentException('invalid_image');
            }
            $image = $resized;
        }

        // A PNG with transparency would turn black in JPEG: flatten it onto white first.
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        ob_start();
        imagejpeg($canvas, null, self::PHOTO_QUALITY);
        $jpeg = (string) ob_get_clean();

        return ['jpeg' => $jpeg, 'width' => imagesx($canvas), 'height' => imagesy($canvas)];
    }

    /**
     * The client's signature from the canvas: a PNG, as raw bytes or as a "data:image/png;base64,..." URL.
     * Returns a clean PNG that keeps its transparency.
     */
    public function signature(string $input): string
    {
        $base64 = preg_replace('#^data:image/png;base64,#', '', trim($input));
        $bytes = base64_decode((string) $base64, true);
        if ($bytes === false || $bytes === '') {
            throw new \InvalidArgumentException('invalid_signature');
        }
        if (strlen($bytes) > self::SIGNATURE_MAX_BYTES) {
            throw new \InvalidArgumentException('signature_too_large');
        }
        [$width, $height] = $this->inspect($bytes, [IMAGETYPE_PNG], 'invalid_signature');
        if ($width > self::SIGNATURE_MAX_WIDTH || $height > self::SIGNATURE_MAX_HEIGHT) {
            throw new \InvalidArgumentException('signature_too_large');
        }

        $image = $this->decode($bytes, 'invalid_signature');
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * Sniff the real type and size from the bytes, before any decoding.
     *
     * @param int[] $allowed IMAGETYPE_* constants
     * @return array{0:int, 1:int}
     */
    private function inspect(string $bytes, array $allowed, string $error = 'invalid_image'): array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || !in_array($info[2], $allowed, true) || $info[0] < 1 || $info[1] < 1) {
            throw new \InvalidArgumentException($error);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new \InvalidArgumentException('image_too_large');
        }

        return [$info[0], $info[1]];
    }

    private function decode(string $bytes, string $error = 'invalid_image'): \GdImage
    {
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new \InvalidArgumentException($error);
        }

        return $image;
    }
}
