<?php

namespace App\Command;

use App\Repository\CommandeRepository;
use App\Service\MongoDbService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync-mongo', description: 'Synchronise les commandes PostgreSQL vers MongoDB')]
class SyncMongoCommand extends Command
{
    public function __construct(
        private CommandeRepository $commandeRepository,
        private MongoDbService $mongoDbService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandes = $this->commandeRepository->findAll();

        foreach ($commandes as $commande) {
            $this->mongoDbService->syncCommande([
                'numero_commande' => $commande->getNumeroCommande(),
                'menu_titre' => $commande->getMenu() ? $commande->getMenu()->getTitre() : 'Inconnu',
                'nombre_personne' => $commande->getNombrePersonne(),
                'prix_menu' => $commande->getPrixMenu(),
                'prix_livraison' => $commande->getPrixLivraison(),
                'prix_total' => $commande->getPrixTotal(),
                'date_commande' => $commande->getDateCommande() ? $commande->getDateCommande()->format('Y-m-d') : date('Y-m-d'),
                'statut' => $commande->getStatut(),
                'client_email' => $commande->getUtilisateur() ? $commande->getUtilisateur()->getEmail() : '',
            ]);

            $output->writeln('Synced: ' . $commande->getNumeroCommande());
        }

        $output->writeln('Synchronisation terminée ! ' . count($commandes) . ' commandes synchronisées.');

        return Command::SUCCESS;
    }
}
