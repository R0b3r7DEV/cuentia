<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserLogo;
use App\Util\Iban;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The issuer's own data as the documents print it: the logo (embedded as a data: URI, so Dompdf never fetches
 * anything) and the contact lines. One place for invoices, quotes and work orders, so they always agree.
 *
 * ES: Los datos propios del emisor tal como los imprimen los documentos: el logo (incrustado como data:, así
 * Dompdf nunca descarga nada) y las líneas de contacto. Un único sitio para facturas, presupuestos y partes.
 */
class IssuerProfile
{
    public function __construct(private EntityManagerInterface $em) {}

    public function logo(?User $user): ?UserLogo
    {
        return $user === null || $user->getId() === null
            ? null
            : $this->em->getRepository(UserLogo::class)->findOneBy(['user' => $user]);
    }

    /** `<img>` with the logo, or '' when there is none. */
    public function logoTag(?User $user, string $class = 'logo'): string
    {
        $logo = $this->logo($user);

        return $logo === null ? '' : sprintf(
            '<img class="%s" src="data:image/png;base64,%s" alt="">',
            htmlspecialchars($class, ENT_QUOTES),
            base64_encode($logo->getPng()),
        );
    }

    /** Name for documents: the fiscal name, or the e-mail as a last resort. */
    public static function name(?User $user): string
    {
        return $user?->getBusinessName() ?: (string) $user?->getEmail();
    }

    /** "Tel. 600 000 000" (escaped HTML), or ''. */
    public static function phoneLine(?User $user): string
    {
        $phone = $user?->getPhone();

        return $phone ? 'Tel. ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : '';
    }

    /** "ES91 2100 0418 4502 0005 1332" for printing, or null. */
    public static function iban(?User $user): ?string
    {
        return $user?->getIban() ? Iban::format($user->getIban()) : null;
    }
}
