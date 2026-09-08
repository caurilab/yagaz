-- Active l'extension TimescaleDB sur la base Yagaz au premier démarrage du
-- conteneur. Les hypertables (mesures de poids) sont créées par les migrations
-- Laravel en Phase 1, pas ici : ce fichier ne fait qu'activer l'extension.
CREATE EXTENSION IF NOT EXISTS timescaledb;
