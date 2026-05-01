<?php

namespace App\Service;

use MongoDB\Client;
use MongoDB\Collection;

class MongoDbService
{
    private Client $client;
    private string $dbName;

    private bool $available = false;

    public function __construct()
    {
        if (!extension_loaded('mongodb')) {
            $this->available = false;
            return;
        }
        $this->client = new Client($_ENV['MONGODB_URL'] ?? 'mongodb://localhost:27017');
        $this->dbName = $_ENV['MONGODB_DB'] ?? 'vite_et_gourmand';
        $this->available = true;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getCollection(string $name): ?Collection
    {
        if (!$this->available) {
            return null;
        }
        return $this->client->{$this->dbName}->{$name};
    }

    /**
     * Synchronise une commande vers MongoDB
     */
    public function syncCommande(array $data): void
    {
        $collection = $this->getCollection('commandes_stats');
        if (!$collection) return;

        $collection->updateOne(
            ['numero_commande' => $data['numero_commande']],
            ['$set' => $data],
            ['upsert' => true]
        );
    }

    /**
     * Ajoute un événement dans l'historique de suivi d'une commande
     */
    public function ajouterSuivi(string $numeroCommande, string $statut): void
    {
        $collection = $this->getCollection('commandes_suivi');
        if (!$collection) return;

        $collection->insertOne([
            'numero_commande' => $numeroCommande,
            'statut' => $statut,
            'date' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Récupère l'historique de suivi d'une commande
     */
    public function getSuivi(string $numeroCommande): array
    {
        $collection = $this->getCollection('commandes_suivi');
        if (!$collection) return [];

        $results = $collection->find(
            ['numero_commande' => $numeroCommande],
            ['sort' => ['date' => 1]]
        )->toArray();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'statut' => $result['statut'],
                'date' => $result['date'],
            ];
        }

        return $data;
    }

    /**
     * Nombre de commandes par menu
     */
    public function getCommandesParMenu(): array
    {
        $collection = $this->getCollection('commandes_stats');
        if (!$collection) return [];

        $pipeline = [
            ['$group' => [
                '_id' => '$menu_titre',
                'count' => ['$sum' => 1],
                'chiffre_affaires' => ['$sum' => '$prix_total'],
            ]],
            ['$sort' => ['count' => -1]],
        ];

        $results = $collection->aggregate($pipeline)->toArray();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'menu' => $result['_id'],
                'count' => $result['count'],
                'chiffre_affaires' => $result['chiffre_affaires'],
            ];
        }

        return $data;
    }

    /**
     * Chiffre d'affaires par menu avec filtres
     */
    public function getChiffreAffaires(?string $menuTitre = null, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $collection = $this->getCollection('commandes_stats');
        if (!$collection) return [];

        $match = [];

        if ($menuTitre) {
            $match['menu_titre'] = $menuTitre;
        }

        if ($dateDebut) {
            $match['date_commande'] = ['$gte' => $dateDebut];
        }

        if ($dateFin) {
            if (isset($match['date_commande'])) {
                $match['date_commande']['$lte'] = $dateFin;
            } else {
                $match['date_commande'] = ['$lte' => $dateFin];
            }
        }

        $pipeline = [];

        if (!empty($match)) {
            $pipeline[] = ['$match' => $match];
        }

        $pipeline[] = ['$group' => [
            '_id' => '$menu_titre',
            'total_commandes' => ['$sum' => 1],
            'chiffre_affaires' => ['$sum' => '$prix_total'],
            'prix_moyen' => ['$avg' => '$prix_total'],
        ]];

        $pipeline[] = ['$sort' => ['chiffre_affaires' => -1]];

        $results = $collection->aggregate($pipeline)->toArray();

        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'menu' => $result['_id'],
                'total_commandes' => $result['total_commandes'],
                'chiffre_affaires' => round($result['chiffre_affaires'], 2),
                'prix_moyen' => round($result['prix_moyen'], 2),
            ];
        }

        return $data;
    }

    /**
     * Liste des menus distincts dans MongoDB
     */
    public function getMenusList(): array
    {
        $collection = $this->getCollection('commandes_stats');
        if (!$collection) return [];
        return $collection->distinct('menu_titre');
    }
}
