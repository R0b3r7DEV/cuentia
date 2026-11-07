<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * "Forgot my password": e-mails a single-use link that expires in an hour, and redeems it.
 *
 * - The token is 256 random bits; only its SHA-256 is stored, so the database alone cannot be used to log in.
 * - Asking for a new link invalidates the previous ones; using a link deletes every pending one.
 * - request() behaves the same whether the e-mail exists or not, so the endpoint cannot be used to find out
 *   who has an account. It never throws on a delivery failure for the same reason (it is logged).
 * - Changing the password also ends the other open sessions: Symfony compares the stored password hash on
 *   every request and logs out a session whose user's hash has changed.
 *
 * ES: "He olvidado la contraseña": envía por correo un enlace de un solo uso que caduca en una hora, y lo canjea.
 * El token son 256 bits aleatorios y solo se guarda su SHA-256. Pedir un enlace nuevo invalida los anteriores;
 * usar uno borra todos los pendientes. request() se comporta igual exista o no el email (no revela quién tiene
 * cuenta). Cambiar la contraseña cierra también las demás sesiones abiertas.
 */
class PasswordResetService
{
    public const TTL = '+1 hour';
    public const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private BusinessClock $clock,
        #[Autowire(env: 'FRONTEND_URL')] private string $frontendUrl,
        #[Autowire(env: 'MAILER_FROM')] private string $from,
    ) {}

    /** Send a reset link if the e-mail has an account. Same outcome either way. */
    public function request(string $email): void
    {
        // Case-insensitive: registration stores the e-mail as typed. / Sin distinguir mayúsculas.
        $user = $this->users->createQueryBuilder('u')
            ->where('LOWER(u.email) = :e')->setParameter('e', mb_strtolower(trim($email)))
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (!$user instanceof User) {
            return;
        }

        $this->deleteTokensOf($user);
        $token = bin2hex(random_bytes(32));
        $now = $this->clock->now();
        $this->em->persist(new PasswordResetToken($user, hash('sha256', $token), $now->modify(self::TTL), $now));
        $this->em->flush();

        // The token goes in the URL fragment (#), which browsers never send to a server: it stays out of
        // access logs, proxies and the Referer header. / El token va tras el #, que el navegador no envía.
        $link = rtrim($this->frontendUrl, '/') . '/restablecer#token=' . $token;

        try {
            $this->mailer->send($this->email($user, $link));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Password reset e-mail could not be sent', ['exception' => $e]);
        }
    }

    /**
     * Set a new password with a token from the e-mail.
     * Throws InvalidArgumentException with a code: invalid_token (unknown, used or expired) or weak_password.
     */
    public function reset(string $token, string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException('weak_password');
        }
        $record = $token === '' ? null
            : $this->em->getRepository(PasswordResetToken::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);
        if ($record === null || $record->isExpired($this->clock->now())) {
            throw new \InvalidArgumentException('invalid_token');
        }

        $user = $record->getUser();
        $user->setPassword($this->hasher->hashPassword($user, $password));
        // Opening the reset link proves the address is theirs. / Abrir el enlace demuestra que el email es suyo.
        if (!$user->isEmailVerified()) {
            $user->setEmailVerifiedAt($this->clock->now())->setEmailVerification(null, null);
        }
        $this->deleteTokensOf($user); // single use, and any other pending link dies too
        $this->em->flush();
    }

    private function deleteTokensOf(User $user): void
    {
        $this->em->createQueryBuilder()
            ->delete(PasswordResetToken::class, 't')
            ->where('t.user = :u')->setParameter('u', $user)
            ->getQuery()->execute();
    }

    private function email(User $user, string $link): Email
    {
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return (new Email())
            ->from(Address::create($this->from))
            ->to($user->getEmail())
            ->subject('Restablece tu contraseña de Cuentia')
            ->text(
                "Hola:\n\nHemos recibido una petición para cambiar la contraseña de tu cuenta de Cuentia.\n\n"
                . "Para elegir una nueva, abre este enlace (caduca en 1 hora y solo sirve una vez):\n$link\n\n"
                . "Si no lo has pedido tú, ignora este correo: tu contraseña no cambia.\n"
            )
            ->html(
                '<p>Hola:</p><p>Hemos recibido una petición para cambiar la contraseña de tu cuenta de Cuentia.</p>'
                . '<p><a href="' . $safeLink . '" style="display:inline-block;padding:10px 18px;background:#443ea8;'
                . 'color:#fff;border-radius:999px;text-decoration:none;font-weight:600">Elegir una contraseña nueva</a></p>'
                . '<p style="color:#56506e;font-size:13px">El enlace caduca en 1 hora y solo sirve una vez.<br>'
                . 'Si no lo has pedido tú, ignora este correo: tu contraseña no cambia.</p>'
            );
    }
}
