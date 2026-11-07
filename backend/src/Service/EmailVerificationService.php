<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Time\BusinessClock;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * E-mail confirmation at sign-up: proves the address belongs to whoever registered it, so nobody can open an
 * account in someone else's name. Same design as the password reset: a random 256-bit token, only its SHA-256
 * stored, travelling after the "#" of the link (never in server logs), valid for 48 hours and once.
 *
 * ES: Confirmación del email al darse de alta: demuestra que la dirección es de quien se registra, así nadie
 * abre una cuenta a nombre de otro. Mismo diseño que la recuperación de contraseña: token aleatorio de 256 bits,
 * solo se guarda su SHA-256, va tras el «#» del enlace, sirve 48 horas y una sola vez.
 */
class EmailVerificationService
{
    public const TTL = '+48 hours';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private BusinessClock $clock,
        #[Autowire(env: 'FRONTEND_URL')] private string $frontendUrl,
        #[Autowire(env: 'MAILER_FROM')] private string $from,
    ) {}

    /** A fresh link for this user (any previous one stops working), sent by e-mail. */
    public function send(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }
        $token = bin2hex(random_bytes(32));
        $user->setEmailVerification(hash('sha256', $token), $this->clock->now()->modify(self::TTL));
        $this->em->flush();

        $link = rtrim($this->frontendUrl, '/') . '/verificar#token=' . $token;
        try {
            $this->mailer->send($this->email($user, $link));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('E-mail confirmation could not be sent', ['exception' => $e]);
        }
    }

    /** Resend to an address, if it has an unconfirmed account. Same outcome either way (no enumeration). */
    public function resend(string $email): void
    {
        $user = $this->users->createQueryBuilder('u')
            ->where('LOWER(u.email) = :e')->setParameter('e', mb_strtolower(trim($email)))
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($user instanceof User) {
            $this->send($user);
        }
    }

    /**
     * Confirm with the token from the e-mail. / Confirmar con el token del correo.
     *
     * @throws \InvalidArgumentException invalid_token (unknown, used or expired)
     */
    public function verify(string $token): User
    {
        $user = $token === '' ? null : $this->users->findOneBy(['emailVerificationHash' => hash('sha256', $token)]);
        if (!$user instanceof User || $user->getEmailVerificationExpiresAt() === null
            || $user->getEmailVerificationExpiresAt() < $this->clock->now()) {
            throw new \InvalidArgumentException('invalid_token');
        }
        $user->setEmailVerifiedAt($this->clock->now())->setEmailVerification(null, null);
        $this->em->flush();

        return $user;
    }

    private function email(User $user, string $link): Email
    {
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return (new Email())
            ->from(Address::create($this->from))
            ->to($user->getEmail())
            ->subject('Confirma tu email para entrar en Cuentia')
            ->text(
                "Hola:\n\nGracias por darte de alta en Cuentia. Para activar tu cuenta, confirma que este email es tuyo "
                . "abriendo este enlace (caduca en 48 horas):\n$link\n\n"
                . "Si no te has dado de alta tú, ignora este correo: sin confirmar, nadie puede entrar con esta cuenta.\n"
            )
            ->html(
                '<p>Hola:</p><p>Gracias por darte de alta en Cuentia. Para activar tu cuenta, confirma que este email es tuyo.</p>'
                . '<p><a href="' . $safeLink . '" style="display:inline-block;padding:10px 18px;background:#d4ff24;'
                . 'color:#030407;border-radius:10px;text-decoration:none;font-weight:700">Confirmar mi email</a></p>'
                . '<p style="color:#4f5560;font-size:13px">El enlace caduca en 48 horas.<br>'
                . 'Si no te has dado de alta tú, ignora este correo: sin confirmar, nadie puede entrar con esta cuenta.</p>'
            );
    }
}
