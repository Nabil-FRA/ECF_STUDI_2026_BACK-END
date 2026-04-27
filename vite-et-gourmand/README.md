# ���️ Vite & Gourmand - Application de Traiteur

Application web de gestion de commandes pour traiteur, développée avec Symfony 7.

## ��� Table des matières

- [Prérequis](#prérequis)
- [Installation avec Docker](#installation-avec-docker)
- [Installation sans Docker](#installation-sans-docker)
- [Accès à l'application](#accès-à-lapplication)
- [Comptes de test](#comptes-de-test)
- [Stack technique](#stack-technique)
- [Structure du projet](#structure-du-projet)

---

## ��� Prérequis

### Avec Docker (recommandé)
- [Docker](https://www.docker.com/get-started) (v20+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2+)
- Git

### Sans Docker
- PHP 8.2+
- Composer 2+
- PostgreSQL 15+
- MongoDB 6+
- Symfony CLI (optionnel)

---

## ��� Installation avec Docker

### 1. Cloner le projet
```bash
git clone https://github.com/Nabil-FRA/ECF_STUDI_2026_BACK-END.git
cd vite-et-gourmand
```

### 2. Configurer l'environnement
```bash
cp .env.docker .env.local
```

### 3. Lancer les conteneurs
```bash
docker-compose up -d --build
```

### 4. Installer les dépendances et créer la base
```bash
docker-compose exec app composer install
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

### 5. C'est prêt ! ���

L'application est accessible sur : **http://localhost:8080**

---

## ���️ Installation sans Docker

### 1. Cloner et installer
```bash
git clone https://github.com/VOTRE_USERNAME/vite-et-gourmand.git
cd vite-et-gourmand
composer install
```

### 2. Configurer la base de données

Créez un fichier `.env.local` :
```env
DATABASE_URL="postgresql://USER:PASSWORD@127.0.0.1:5432/vite_et_gourmand?serverVersion=15"
MONGODB_URL="mongodb://127.0.0.1:27017"
MONGODB_DB=vite_et_gourmand
MAILER_DSN=smtp://localhost:1025
```

### 3. Créer la base et les tables
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

### 4. Lancer le serveur
```bash
symfony serve
# ou
php -S localhost:8000 -t public/
```

---

## ��� Accès à l'application

| Service | URL | Description |
|---------|-----|-------------|
| **Application** | http://localhost:8080 | Site principal |
| **Admin** | http://localhost:8080/admin | Espace administration |
| **Mailhog** | http://localhost:8025 | Visualiser les emails envoyés |
| **Adminer** | http://localhost:8081 | Interface base de données |

---

## ��� Comptes de test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| **Administrateur** | jose@viteetgourmand.fr | admin123! |
| **Employé** | employe@viteetgourmand.fr | employe123! |
| **Client** | client@gmail.com | client123! |

---

## ���️ Stack technique

| Technologie | Version | Usage |
|-------------|---------|-------|
| PHP | 8.2 | Langage backend |
| Symfony |  7.x | Framework PHP |
| PostgreSQL | 15 | Base de données relationnelle |
| MongoDB | 6.0 | Base de données NoSQL (stats, suivi) |
| Nginx | Alpine | Serveur web |
| Docker | 20+ | Conteneurisation |
| EasyAdmin | 4.x | Interface d'administration |
| Twig | 3.x | Moteur de templates |

---

## ��� Structure du projet
```
vite-et-gourmand/
├── config/                 # Configuration Symfony
├── docker/                 # Fichiers Docker
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   └── php.ini
│   └── postgres/
│       └── init.sql
├── migrations/             # Migrations Doctrine
├── public/                 # Point d'entrée web
├── src/
│   ├── Controller/         # Contrôleurs
│   ├── Entity/             # Entités Doctrine
│   ├── Form/               # Formulaires
│   ├── Repository/         # Repositories
│   └── Service/            # Services (MongoDB, etc.)
├── templates/              # Templates Twig
├── .env                    # Variables par défaut
├── .env.docker             # Variables Docker
├── docker-compose.yml      # Configuration Docker Compose
├── Dockerfile              # Image Docker PHP
├── Makefile                # Commandes rapides
└── README.md               # Ce fichier
```

---

## ��� Commandes utiles (Makefile)
```bash
make help           # Afficher toutes les commandes
make up             # Démarrer les conteneurs
make down           # Arrêter les conteneurs
make logs           # Voir les logs
make shell          # Shell dans le conteneur PHP
make migrate        # Lancer les migrations
make clear          # Vider le cache
make install        # Installation complète
```

---

## ��� Déploiement

L'application est déployée sur : **https://vite-et-gourmand.herokuapp.com**

---

## ���‍��� Auteur

Projet réalisé dans le cadre du **TP Développeur Web et Web Mobile** - Studi

---

## ��� Licence

Ce projet est sous licence MIT.
