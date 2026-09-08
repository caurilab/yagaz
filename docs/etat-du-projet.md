# État du projet — Yagaz

Photo de l'avancement, mise à jour en continu par l'archiviste.

Dernière mise à jour : 2026-09-08

## En un coup d'œil

Le projet est en **phase de cadrage et d'amorçage**. Le concept, le modèle
économique et l'architecture sont posés. Le code n'a pas encore commencé. Le
prototype matériel est en cours de sourcing.

## Fait

- Cadrage produit complet : vision, modèle économique, PRD, architecture,
  matériel et sourcing, UX/UI (voir les documents de `docs/`).
- Stack technique arrêtée (voir `.ai/guidelines/stack.md`).
- Choix du monorepo et structure du dépôt (voir ADR 0001).
- Structure des agents et guidelines de collaboration.
- Sourcing des composants du prototype identifié (kit HX711 + cellules 50 kg,
  ESP32 DevKit).

## En cours

- Commande des composants du prototype (3 plateaux de test).
- Vérification d'un fournisseur ESP32 possiblement local (helectro.net).

## À venir

- Code de test ESP32 : lecture HX711, calibrage, affichage du poids.
- Validation de la chaîne de mesure avec un poids connu, puis une bouteille.
- Initialisation des trois projets applicatifs (api, web, app).
- Schéma de données initial et premières migrations.

## À trancher

- **Paiement en ligne / Mobile Money** : non encore discuté, laissé en backlog
  dans le PRD. Sujet à ouvrir.
- Format précis des messages MQTT publiés par le plateau.
- Modèle de données détaillé (un document dédié est proposé).

## Bloqué

- Rien pour l'instant.
