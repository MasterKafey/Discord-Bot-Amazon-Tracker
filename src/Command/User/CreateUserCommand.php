<?php

namespace App\Command\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface      $entityManager
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User\'s email')
            ->addArgument('password', InputArgument::REQUIRED, 'User\'s password');

    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = (new User())->setEmail($input->getArgument('email'));

        $hashedPassword = $this->hasher->hashPassword($user, $input->getArgument('password'));
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}