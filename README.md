# Vite & Gourmand — Back-end API REST

> **ECF — RNCP37674 Developpeur Web et Web Mobile**
> Bloc de competences BC02 — Developper la partie back-end d'une application web

Application de traiteur en ligne permettant la gestion des menus, des commandes, des clients, des employes et des statistiques. Le back-end expose une **API REST securisee** consommée par le front-end SPA, et propose egalement une **interface d'administration Twig** via EasyAdmin.

---

## Sommaire

1. [Technologies utilisees](#technologies-utilisees)
2. [Architecture](#architecture)
3. [Prerequis](#prerequis)
4. [Installation et demarrage](#installation-et-demarrage)
5. [Variables d'environnement](#variables-denvironnement)
6. [Base de donnees](#base-de-donnees)
7. [API REST — Endpoints](#api-rest--endpoints)
8. [Securite](#securite)
9. [Lancer les tests](#lancer-les-tests)
10. [Comptes de test](#comptes-de-test)
11. [Services disponibles](#services-disponibles)
12. [Commandes utiles](#commandes-utiles)
13. [Regles metier principales](#regles-metier-principales)

---

## Technologies utilisees

| Technologie | Version | Role |
|---|---|---|
| PHP | 8.2 | Langage back-end |
| Symfony | 7.4 | Framework back-end |
| PostgreSQL | 15 | Base de donnees relationnelle |
| MongoDB | 6.0 | Base de donnees NoSQL (stats/suivi) |
| Nginx | Alpine | Serveur web |
| Docker | 20+ | Conteneurisation |
| EasyAdmin | 4.x | Interface d'administration |
| nelmio/cors-bundle | — | Gestion CORS |
| Symfony Mailer | — | Envoi d'emails |
| Mailhog | — | Serveur mail de test |

---

## Architecture

```
vite-et-gourmand/
├── src/
│   ├── Controller/
│   │   ├── Api/                        # API REST (JSON)
│   │   │   ├── AuthApiController.php   # login, register, forgot/reset password
│   │   │   ├── MenuApiController.php   # menus, themes, regimes, allergenes
│   │   │   ├── PublicApiController.php # avis, horaires, contact
│   │   │   ├── UserApiController.php   # espace client
│   │   │   ├── EmployeApiController.php# espace employe
│   │   │   └── AdminApiController.php  # espace administrateur
│   │   ├── Admin/                      # Back-office EasyAdmin (Twig)
│   │   └── ...                         # Controleurs Twig classiques
│   ├── Entity/                         # 11 entites Doctrine
│   ├── Repository/                     # Repositories Doctrine
│   ├── Security/
│   │   └── ApiTokenAuthenticator.php   # Auth par token HMAC-SHA256
│   ├── EventListener/
│   │   ├── ApiRateLimiterListener.php  # Rate limiting
│   │   └── SecurityHeadersListener.php # Headers de securite
│   ├── Service/
│   │   └── MongoDbService.php          # Service MongoDB
│   └── DataFixtures/
│       └── AppFixtures.php             # Donnees de test
├── config/
│   ├── packages/
│   │   ├── security.yaml               # Firewalls, access_control
│   │   ├── nelmio_cors.yaml            # Configuration CORS
│   │   └── rate_limiter.yaml           # Rate limiting
│   └── services.yaml
├── migrations/                         # Migrations Doctrine
├── docker-compose.yml
├── Dockerfile
└── test_api.sh                         # Script de tests fonctionnels (53 tests)
```

---

## Prerequis:

- **Docker Desktop** (Windows/Mac) ou **Docker Engine** (Linux)
- **Docker Compose** v2+
- **Git**
- `curl` et `bash` pour executer les tests (Git Bash sur Windows)

---

## Installation et demarrage:

> **Important** : le `docker-compose.yml` orchestre a la fois le back-end ET le front-end.
> Les deux depots doivent être clonés en respectant l'arborescence ci-dessous.

### 1. Cloner les deux depots

```
Projet/
├── ECF_STUDI_2026_FRONT-END/          <-- depot front-end
└── Backend/
    └── ECF_STUDI_2026_BACK-END/
        └── vite-et-gourmand/           <-- depot back-end (vous etes ici)
```

```bash
mkdir Projet && cd Projet

# Front-end
git clone https://github.com/Nabil-FRA/ECF_STUDI_2026_FRONT-END.git

# Back-end
mkdir -p Backend/ECF_STUDI_2026_BACK-END
cd Backend/ECF_STUDI_2026_BACK-END
git clone https://github.com/Nabil-FRA/ECF_STUDI_2026_BACK-END.git vite-et-gourmand
cd vite-et-gourmand
```

### 2. Configurer l'URL API dans le front-end:

Dans le fichier `../../../ECF_STUDI_2026_FRONT-END/JS/api.js`, ligne 16, remplacer :

```js
var API_BASE_URL = 'https://vite-et-gourmand-ecf-nar-7b5ab7722b1a.herokuapp.com/api';
```

Par :

```js
var API_BASE_URL = 'http://localhost:8080/api';
```

### 3. Creer le fichier de configuration locale:

```bash
cp .env .env.local
```

Modifier `.env.local` avec vos valeurs (voir section [Variables d'environnement](#variables-denvironnement)).

### 4. Demarrer les conteneurs Docker

```bash
docker-compose up -d --build
```

Attendre que tous les services soient demarres (30 a 60 secondes la premiere fois).

### 5. Creer la base de donnees et charger les donnees

```bash
# Creer le schema PostgreSQL
docker exec vg_app php bin/console doctrine:schema:drop --force
docker exec vg_app php bin/console doctrine:schema:create

# Charger les fixtures (donnees de test)
docker exec vg_app php bin/console doctrine:fixtures:load --no-interaction

# Vider le cache
docker exec vg_app php bin/console cache:clear
```

### 6. Verifier que tout fonctionne

| Service | URL | Description |
|---|---|---|
| Front-end | http://localhost:3000 | Site web Vite & Gourmand |
| API Back-end | http://localhost:8080/api/menus | Liste des menus (JSON) |
| Adminer | http://localhost:8081 | Interface base de donnees |
| Mailhog | http://localhost:8025 | Emails de test |

---

## Variables d'environnement

Contenu recommande pour `.env.local` :

```env
APP_ENV=dev
APP_SECRET=votre_secret_unique_ici_changez_moi

DATABASE_URL="postgresql://vg_user:vg_password@127.0.0.1:5432/vite_et_gourmand?serverVersion=15&charset=utf8"

MONGODB_URL="mongodb://127.0.0.1:27017"
MONGODB_DB=vite_et_gourmand

MAILER_DSN=smtp://localhost:1025

CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

FRONTEND_URL=http://localhost:5173
```

> Le fichier `.env.local` n'est pas versionné (`.gitignore`). Il contient les secrets locaux.

---

## Base de donnees

### PostgreSQL — Schema relationnel

| Entite | Description |
|---|---|
| `Utilisateur` | Clients, employes, administrateurs |
| `Role` | Roles : utilisateur, employe, administrateur, desactive |
| `Commande` | Commandes avec cycle de vie complet (8 statuts) |
| `Menu` | Menus avec prix, regime, theme, conditions |
| `Plat` | Plats composant un menu |
| `MenuImage` | Images associees aux menus |
| `Allergene` | 14 allergenes reglementaires europeens |
| `Regime` | Regimes alimentaires (classique, vegetarien, vegan, sans gluten) |
| `Theme` | Themes des menus (Noel, Paques, Classique, Evenement) |
| `Horaire` | Horaires d'ouverture de l'entreprise |
| `Avis` | Avis clients (en attente, valide, refuse) |

### MongoDB — Donnees NoSQL

| Collection | Contenu |
|---|---|
| `commandes_stats` | Snapshot de chaque commande (CA, menu, client, statut) |
| `commandes_suivi` | Historique de tous les changements de statut |

### Cycle de vie d'une commande

```
en cours --> accepte --> en preparation --> en cours de livraison
         --> livre --> en attente du retour de materiel --> terminee
         --> annulee  (depuis n'importe quel statut, motif obligatoire)
```

---

## API REST — Endpoints:

### Authentification (`/api/auth`) — Public

| Methode | URL | Description |
|---|---|---|
| POST | `/api/auth/login` | Connexion — retourne un token Bearer (24h) |
| POST | `/api/auth/register` | Inscription client |
| POST | `/api/auth/forgot-password` | Demande de reinitialisation (envoi email) |
| POST | `/api/auth/reset-password` | Reinitialisation avec token (expire apres 1h) |

### Donnees publiques — Public

| Methode | URL | Description |
|---|---|---|
| GET | `/api/menus` | Liste des menus (filtres : `regime`, `theme`, `prix_min`, `prix_max`) |
| GET | `/api/menus/{id}` | Detail menu avec plats et allergenes |
| GET | `/api/themes` | Liste des themes |
| GET | `/api/regimes` | Liste des regimes alimentaires |
| GET | `/api/allergenes` | Liste des 14 allergenes |
| GET | `/api/avis` | Avis clients valides uniquement |
| GET | `/api/horaires` | Horaires d'ouverture |
| GET | `/api/plats` | Liste des plats |
| POST | `/api/contact` | Formulaire de contact (honeypot anti-spam) |

### Espace client (`/api/user`) — `ROLE_USER`

| Methode | URL | Description |
|---|---|---|
| GET | `/api/user/profile` | Voir son profil |
| PUT | `/api/user/profile` | Modifier son profil |
| GET | `/api/user/commandes` | Mes commandes |
| POST | `/api/user/commandes` | Passer une commande |
| GET | `/api/user/commandes/{id}` | Detail d'une commande |
| PUT | `/api/user/commandes/{id}` | Modifier une commande (si statut = "en cours") |
| PUT | `/api/user/commandes/{id}/annuler` | Annuler une commande |
| POST | `/api/user/commandes/{id}/avis` | Deposer un avis (commande terminee uniquement) |

### Espace employe (`/api/employe`) — `ROLE_EMPLOYE`

| Methode | URL | Description |
|---|---|---|
| GET | `/api/employe/commandes` | Toutes les commandes (filtres : `?statut=`, `?client=`) |
| PUT | `/api/employe/commandes/{id}/statut` | Changer le statut d'une commande |
| GET | `/api/employe/avis` | Liste de tous les avis |
| PUT | `/api/employe/avis/{id}/statut` | Valider ou refuser un avis |

### Espace administrateur (`/api/admin`) — `ROLE_ADMIN`

| Methode | URL | Description |
|---|---|---|
| GET | `/api/admin/utilisateurs` | Liste des utilisateurs et employes |
| POST | `/api/admin/employes` | Creer un compte employe |
| PUT | `/api/admin/utilisateurs/{id}/toggle` | Activer / desactiver un compte |
| GET | `/api/admin/stats` | Statistiques CA MongoDB (filtres : `menu`, `date_debut`, `date_fin`) |

### Format du token Bearer

```
Authorization: Bearer <token>
```

Le token est un JSON encode en base64, signe HMAC-SHA256 :

```json
{ "email": "user@example.com", "exp": 1234567890, "sig": "hmac_sha256_signature" }
```

---

## Securite

| Mesure | Detail |
|---|---|
| Authentification | Token HMAC-SHA256 signe avec `APP_SECRET`, stateless |
| Anti-timing attack | `hash_equals()` pour toutes les comparaisons de hash |
| Rate limiting | Login : 5 tentatives/minute — Register : 3/heure (par IP) |
| Headers HTTP | CSP, HSTS, X-Frame-Options, X-XSS-Protection, Referrer-Policy |
| CORS | Origines autorisees configurables via `CORS_ALLOW_ORIGIN` |
| Politique mot de passe | 10 car. min., 1 maj., 1 min., 1 chiffre, 1 special (RGPD/CNIL) |
| Anti-enumeration | `forgot-password` retourne toujours le meme message |
| Honeypot | Champ cache sur le formulaire de contact pour detecter les bots |
| Sanitisation | `strip_tags` + `htmlspecialchars` sur toutes les entrees utilisateur |
| Controle d'acces | `IsGranted` par controleur + `access_control` dans `security.yaml` |
| Firewall stateless | Pas de session cote serveur pour l'API |

---

## Lancer les tests

Le projet inclut un script de **53 tests fonctionnels** couvrant tous les endpoints :

```bash
# Depuis la racine du projet (Git Bash ou terminal Linux/Mac)
bash test_api.sh
```

Cas testes :
- Endpoints publics (menus, themes, avis, horaires, contact)
- Authentification (login, register, forgot-password, reset-password)
- Espace client (profil, commandes CRUD, annulation, avis)
- Espace employe (filtres, changement de statut, gestion avis)
- Espace admin (utilisateurs, stats, creation employe)
- Securite (token invalide, token expire, acces non autorise)

**Resultat attendu : 53/53 tests passants.**

> **Prerequis** : l'application doit tourner sur `http://localhost:8080` et les fixtures doivent etre chargees.

---

## Comptes de test

Apres chargement des fixtures, les comptes suivants sont disponibles :

| Email | Mot de passe | Role |
|---|---|---|
| `jose@viteetgourmand.fr` | `Password1!` | Administrateur |
| `julie@viteetgourmand.fr` | `Password1!` | Employe |
| `jean.dupont@gmail.com` | `Password1!` | Client (commande terminee + avis) |
| `marie.martin@gmail.com` | `Password1!` | Client |
| `pierre.durand@gmail.com` | `Password1!` | Client |
| `sophie.bernard@gmail.com` | `Password1!` | Client |

---

## Services disponibles

| Service | URL | Description |
|---|---|---|
| Application / API | http://localhost:8080 | Application principale |
| Interface Admin (EasyAdmin) | http://localhost:8080/admin | Back-office Twig |
| Adminer (PostgreSQL) | http://localhost:8081 | Interface base de donnees |
| Mailhog (emails) | http://localhost:8025 | Visualiser les emails envoyes |
| MongoDB | localhost:27017 | Base de donnees NoSQL |

### Connexion Adminer

- Systeme : `PostgreSQL`
- Serveur : `database`
- Utilisateur : `vg_user`
- Mot de passe : `vg_password`
- Base de donnees : `vite_et_gourmand`

### Connexion EasyAdmin

Compte : `jose@viteetgourmand.fr` / `Password1!`

---

## Commandes utiles

```bash
# Voir les logs de l'application
docker-compose logs -f app

# Acceder au conteneur PHP
docker exec -it vg_app bash

# Recharger les fixtures (remet la BDD a zero)
docker exec vg_app php bin/console doctrine:schema:drop --force
docker exec vg_app php bin/console doctrine:schema:create
docker exec vg_app php bin/console doctrine:fixtures:load --no-interaction

# Vider le cache Symfony
docker exec vg_app php bin/console cache:clear

# Synchroniser MongoDB depuis PostgreSQL
docker exec vg_app php bin/console app:sync-mongodb

# Arreter les conteneurs
docker-compose down

# Arreter et supprimer les volumes (repart de zero)
docker-compose down -v
```

---

## Regles metier principales

- **Prix de livraison** : 2 EUR/km au-dela de 10 km, gratuit en dessous
- **Reduction** : 10 % si la commande depasse 10 personnes
- **Pret de materiel** : email automatique lors du passage en "en attente du retour de materiel" avec delai de 10 jours ouvres et penalite de 600 EUR
- **Annulation** : mode de contact et motif obligatoires pour toute annulation par un employe
- **Modification commande** : uniquement possible si le statut est "en cours"
- **Avis** : deposable uniquement sur une commande "terminee" appartenant au client connecte
- **Stats CA** : calculees depuis MongoDB avec agregations (total, prix moyen, filtre par menu/date)

---

*Projet realise dans le cadre de l'ECF — RNCP37674 Developpeur Web et Web Mobile — Studi 2026*
