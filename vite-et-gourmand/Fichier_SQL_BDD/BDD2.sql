-- ============================================================
-- 1. NETTOYAGE DE LA BASE DE DONNÉES
-- L'option CASCADE supprime automatiquement les contraintes liées
-- ============================================================
DROP TABLE IF EXISTS plat_allergene CASCADE;
DROP TABLE IF EXISTS menu_plat CASCADE;
DROP TABLE IF EXISTS menu_image CASCADE;
DROP TABLE IF EXISTS avis CASCADE;
DROP TABLE IF EXISTS commande CASCADE;
DROP TABLE IF EXISTS menu CASCADE;
DROP TABLE IF EXISTS utilisateur CASCADE;
DROP TABLE IF EXISTS plat CASCADE;
DROP TABLE IF EXISTS horaire CASCADE;
DROP TABLE IF EXISTS allergene CASCADE;
DROP TABLE IF EXISTS regime CASCADE;
DROP TABLE IF EXISTS theme CASCADE;
DROP TABLE IF EXISTS role CASCADE;

-- ============================================================
-- 2. CRÉATION DES TABLES INDÉPENDANTES
-- ============================================================

CREATE TABLE role (
    role_id SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
);

CREATE TABLE theme (
    theme_id SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
);

CREATE TABLE regime (
    regime_id SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
);

CREATE TABLE allergene (
    allergene_id SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL
);

CREATE TABLE horaire (
    horaire_id SERIAL PRIMARY KEY,
    jour VARCHAR(50) NOT NULL,
    heure_ouverture VARCHAR(50),
    heure_fermeture VARCHAR(50)
);

CREATE TABLE plat (
    plat_id SERIAL PRIMARY KEY,
    titre_plat VARCHAR(50) NOT NULL,
    photo VARCHAR(255)
);

-- ============================================================
-- 3. CRÉATION DES TABLES PRINCIPALES (ATTENTION À L'ORDRE)
-- ============================================================

CREATE TABLE utilisateur (
    utilisateur_id SERIAL PRIMARY KEY,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    telephone VARCHAR(50),
    ville VARCHAR(50),
    pays VARCHAR(50),
    adresse_postale VARCHAR(255),
    role_id INT NOT NULL,
    CONSTRAINT fk_utilisateur_role FOREIGN KEY (role_id) REFERENCES role(role_id) ON DELETE RESTRICT
);

CREATE TABLE menu (
    menu_id SERIAL PRIMARY KEY,
    titre VARCHAR(50) NOT NULL,
    nombre_personne_minimum INT NOT NULL,
    prix_par_personne DOUBLE PRECISION NOT NULL,
    description TEXT,
    conditions TEXT,
    quantite_restante INT,
    theme_id INT NOT NULL,
    regime_id INT NOT NULL,
    CONSTRAINT fk_menu_theme FOREIGN KEY (theme_id) REFERENCES theme(theme_id) ON DELETE RESTRICT,
    CONSTRAINT fk_menu_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id) ON DELETE RESTRICT
);

CREATE TABLE menu_image (
    image_id SERIAL PRIMARY KEY,
    url_image VARCHAR(255) NOT NULL,
    menu_id INT NOT NULL,
    CONSTRAINT fk_image_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE
);

-- CORRECTION : Création de la table Commande AVANT la table Avis
CREATE TABLE commande (
    numero_commande VARCHAR(50) PRIMARY KEY,
    date_commande DATE NOT NULL,
    date_prestation DATE NOT NULL,
    heure_livraison VARCHAR(50),
    lieu_prestation VARCHAR(255) NOT NULL,
    prix_menu DOUBLE PRECISION NOT NULL,
    nombre_personne INT NOT NULL,
    prix_livraison DOUBLE PRECISION NOT NULL,
    statut VARCHAR(50) NOT NULL,
    pret_materiel BOOLEAN DEFAULT FALSE,
    restitution_materiel BOOLEAN DEFAULT FALSE,
    motif_annulation TEXT, -- NOUVEAU : Motif obligatoire si employé annule
    mode_contact_client VARCHAR(50), -- NOUVEAU : Spécification du contact (mail, gsm)
    utilisateur_id INT NOT NULL,
    menu_id INT NOT NULL,
    CONSTRAINT fk_commande_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    CONSTRAINT fk_commande_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE RESTRICT
);

-- CORRECTION : Avis lié à la commande + Type de note ajusté
CREATE TABLE avis (
    avis_id SERIAL PRIMARY KEY,
    note SMALLINT NOT NULL CHECK (note >= 1 AND note <= 5),
    description TEXT,
    statut VARCHAR(50),
    utilisateur_id INT NOT NULL,
    numero_commande VARCHAR(50) NOT NULL, -- NOUVEAU : L'avis est lié à une commande précise
    CONSTRAINT fk_avis_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_commande FOREIGN KEY (numero_commande) REFERENCES commande(numero_commande) ON DELETE CASCADE
);

-- ============================================================
-- 4. CRÉATION DES TABLES DE LIAISON (RELATIONS N:M)
-- ============================================================

CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT fk_mp_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
);

CREATE TABLE plat_allergene (
    plat_id INT NOT NULL,
    allergene_id INT NOT NULL,
    PRIMARY KEY (plat_id, allergene_id),
    CONSTRAINT fk_pa_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_allergene FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id) ON DELETE CASCADE
);

-- ============================================================
-- 5. INTÉGRATION DES DONNÉES DE TEST (FIXTURES)
-- ============================================================

-- Insertion des Rôles
INSERT INTO role (libelle) VALUES
('utilisateur'),
('employe'),
('administrateur');

-- Insertion des Thèmes de test
INSERT INTO theme (libelle) VALUES
('Noël'), ('Pâques'), ('Mariage'), ('Anniversaire');

-- Insertion des Régimes de test
INSERT INTO regime (libelle) VALUES
('Classique'), ('Végétarien'), ('Végétalien'), ('Sans Gluten');

-- Insertion de l'Administrateur (José)
INSERT INTO utilisateur (email, password, nom, prenom, telephone, ville, pays, adresse_postale, role_id)
VALUES (
    'jose@restaurant-bordeaux.fr',
    '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
    'Patron',
    'José',
    '0612345678',
    'Bordeaux',
    'France',
    '15 rue de la Restauration',
    3
);

-- Insertion d'une Employée
INSERT INTO utilisateur (email, password, nom, prenom, telephone, ville, pays, adresse_postale, role_id)
VALUES (
    'employe@restaurant-bordeaux.fr',
    '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
    'Martin',
    'Sophie',
    '0698765432',
    'Bordeaux',
    'France',
    '10 rue Sainte-Catherine',
    2
);

-- Insertion d'un Client (Utilisateur régulier)
INSERT INTO utilisateur (email, password, nom, prenom, telephone, ville, pays, adresse_postale, role_id)
VALUES (
    'client@gmail.com',
    '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
    'Dupont',
    'Jean',
    '0711223344',
    'Mérignac',
    'France',
    '5 avenue de la République',
    1
);
