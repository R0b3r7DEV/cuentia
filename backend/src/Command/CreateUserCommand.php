<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Creates a user (optionally an admin) from the CLI. Handy for seeding a deployment
 * and for creating the admin account.
 * ES: Crea un usuario (opcionalmente admin) desde la consola. Útil para poblar un
 * despliegue y para crear la cuenta de administrador.
 *
 * Usage / Uso:
 *   php bin/console app:create-user admin@cuentia.local "a-strong-password" --admin
 */
#[AsCommand(name: 'app:create-user', description: 'Create a user (optionally an admin)')]
class CreateUserCommand extends Command
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The user email')
            ->addArgument('password', InputArgument::REQUIRED, 'The user password (min 6 chars)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $isAdmin = (bool) $input->getOption('admin');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $io->error('A valid email and a password of at least 6 characters are required.');
            return Command::INVALID;
        }
        if ($this->users->findOneBy(['email' => $email]) !== null) {
            $io->error("A user with email \"$email\" already exists.");
            return Command::FAILURE;
        }

        $user = (new User())->setEmail($email);
        $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : []);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Created %s "%s".', $isAdmin ? 'admin' : 'user', $email));
        return Command::SUCCESS;
    }
}
