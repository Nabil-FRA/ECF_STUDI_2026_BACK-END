# Modele Conceptuel de Donnees - Vite et Gourmand

## Diagramme Entite-Relation (MCD)

```mermaid
erDiagram
    ROLE {
        int id PK
        varchar libelle
    }

    UTILISATEUR {
        int id PK
        varchar email UK
        json roles
        varchar password
        varchar nom
        varchar prenom
        varchar telephone
        varchar ville
        varchar pays
        varchar adresse_postale
    }

    THEME {
        int id PK
        varchar libelle
    }

    REGIME {
        int id PK
        varchar libelle
    }

    MENU {
        int id PK
        varchar titre
        int nombre_personne_minimum
        float prix_par_personne
        text description
        text conditions
        int quantite_restante
    }

    PLAT {
        int id PK
        varchar titre_plat
        varchar photo
    }

    ALLERGENE {
        int id PK
        varchar libelle
    }

    MENU_IMAGE {
        int id PK
        varchar url_image
    }

    COMMANDE {
        int id PK
        varchar numero_commande
        date date_commande
        date date_prestation
        varchar heure_livraison
        varchar lieu_prestation
        float prix_menu
        int nombre_personne
        float prix_livraison
        varchar statut
        bool pret_materiel
        bool restitution_materiel
        text motif_annulation
        varchar mode_contact_client
    }

    AVIS {
        int id PK
        smallint note
        text description
        varchar statut
    }

    HORAIRE {
        int id PK
        varchar jour
        varchar heure_ouverture
        varchar heure_fermeture
    }

    ROLE ||--o{ UTILISATEUR : "1,N"
    UTILISATEUR ||--o{ COMMANDE : "1,N"
    UTILISATEUR ||--o{ AVIS : "1,N"
    THEME ||--o{ MENU : "1,N"
    REGIME ||--o{ MENU : "1,N"
    MENU ||--o{ MENU_IMAGE : "1,N"
    MENU ||--o{ COMMANDE : "1,N"
    COMMANDE ||--o{ AVIS : "1,N"
    MENU }o--o{ PLAT : "N,N"
    PLAT }o--o{ ALLERGENE : "N,N"
```

## Description du modele

La base de donnees relationnelle PostgreSQL de l'application Vite et Gourmand repose sur **11 entites** interconnectees. Au centre du modele, l'entite **Menu** constitue le pivot principal : chaque menu est rattache a un **Theme** (Noel, Paques, classique, evenement) et a un **Regime** alimentaire (vegetarien, vegan, classique). Un menu est compose de plusieurs **Plats** (entrees, plats, desserts) via une relation ManyToMany, permettant a un meme plat de figurer dans plusieurs menus. Chaque plat peut etre associe a plusieurs **Allergenes** reglementaires (14 allergenes majeurs) via une seconde relation ManyToMany. Les **MenuImages** constituent la galerie photo de chaque menu (relation OneToMany).

Cote utilisateurs, l'entite **Utilisateur** est liee a un **Role** (utilisateur, employe, admin) qui determine ses droits d'acces. Un utilisateur authentifie peut passer des **Commandes** sur un menu, avec suivi de statut (en attente, accepte, en preparation, en livraison, livre, terminee). Apres livraison, il peut deposer un **Avis** (note de 1 a 5 et commentaire) soumis a validation par un employe.

L'entite **Horaire** est independante et stocke les horaires d'ouverture hebdomadaires affiches dans le pied de page du site.
