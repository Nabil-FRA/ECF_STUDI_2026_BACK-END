-- ============================================
-- Script d'initialisation PostgreSQL
-- Vite & Gourmand
-- ============================================

-- Ce fichier est exécuté automatiquement au premier démarrage du conteneur
-- Les migrations Symfony créeront les tables

-- Extensions utiles
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Message de confirmation
DO $$
BEGIN
    RAISE NOTICE 'Base de données vite_et_gourmand initialisée avec succès!';
END $$;
