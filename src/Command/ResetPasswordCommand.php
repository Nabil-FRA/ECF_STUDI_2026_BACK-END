<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:reset-password', description: "Redéfinit le mot de passe d'un utilisateur existant (politique RGPD/CNIL)")]
class ResetPasswordCommand extends Command
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "Email de l'utilisateur")
            ->addArgument('password', InputArgument::REQUIRED, 'Nouveau mot de passe en clair');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Même politique que l'inscription : 10 car. min., 1 maj., 1 min., 1 chiffre, 1 spécial
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $password)) {
            $output->writeln('<error>Mot de passe non conforme : 10 caractères minimum, avec au moins une majuscule, une minuscule, un chiffre et un caractère spécial.</error>');

            return Command::FAILURE;
        }

        $user = $this->utilisateurRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('<error>Aucun utilisateur trouvé avec l\'email : ' . $email . '</error>');

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->entityManager->flush();

        $output->writeln('<info>Mot de passe mis à jour pour ' . $email . ' (' . $user->getRole()?->getLibelle() . ')</info>');

        return Command::SUCCESS;
    }
}
