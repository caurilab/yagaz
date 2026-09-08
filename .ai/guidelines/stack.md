# Stack technique

Ce document fige les choix de socle. Toute déviation passe par un ADR.

## Vue d'ensemble

| Couche | Technologie | Où |
|---|---|---|
| API et logique métier | Laravel 13 + Laravel Boost | `yagaz-api` |
| Base de données | PostgreSQL + TimescaleDB | — |
| Ingestion capteur | MQTT | — |
| Dashboards web | React 19 | `yagaz-web` |
| Application mobile | React Native (Expo) | `yagaz-app` |
| Firmware plateau | ESP32 (C++/Arduino) | `yagaz-firmware` |

## Pourquoi ces choix

**Laravel 13.** Stable depuis mars 2026, PHP 8.3 minimum. Apporte déjà rôles
multi-acteurs, notifications multi-canal, queues et multi-tenant — d'où le choix
face à FastAPI, qui aurait imposé de recomposer ces briques. Ses primitives AI
first-party (recherche sémantique/vectorielle) serviront la future couche
recettes.

**PostgreSQL + TimescaleDB.** Les mesures de poids sont des séries temporelles
en gros volume. TimescaleDB les gère nativement (hypertables, agrégations
continues, rétention) tout en restant du PostgreSQL pour le reste.

**MQTT, découplé du REST.** L'ingestion capteur est un flux continu de petits
messages depuis des appareils faible puissance sur réseau instable. Ce n'est pas
du HTTP requête/réponse. On garde les deux canaux séparés.

**React Native (Expo).** Le mobile est la priorité produit : foyer, dépôt,
mandataire, livreur. Expo pour aller vite et déployer simplement.

**React 19.** Les dashboards mandataire et distributeur sont des outils de
bureau sur grand écran, distincts de l'app mobile.

**ESP32.** Microcontrôleur Wi-Fi/Bluetooth du plateau, largement documenté, avec
la librairie HX711 pour lire les cellules de charge.

## Conventions transversales

- Langue du code et des commentaires : cohérente dans tout le dépôt.
- Le contrat d'API est la référence partagée : on ne le change pas à la légère.
- On s'appuie sur ce que le framework fournit avant d'écrire du sur-mesure.
- Robustesse réseau par défaut : aucun client ne suppose une connexion stable
  ou permanente.
