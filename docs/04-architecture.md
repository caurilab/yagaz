# Architecture technique

**Plateforme de suivi et de distribution du gaz butane — Afrique de l'Ouest**

*Document de cadrage — v1*

---

## 1. Vue d'ensemble

Le système se lit en quatre couches, du capteur à l'écran :

1. **Le terrain** — les plateaux connectés sous les bouteilles, qui mesurent un poids et l'émettent.
2. **L'ingestion** — un broker MQTT qui reçoit les mesures de milliers de capteurs et les met à disposition du backend.
3. **Le cœur** — une API Laravel qui traite les mesures, tient la logique métier (niveau, autonomie, alertes, commandes, stocks, tournées) et orchestre les acteurs.
4. **Les clients** — les applications mobiles (foyer, dépôt, mandataire, livreur) et les dashboards web (mandataire, distributeur).

La donnée brute de pesée suit un chemin dédié, séparé du trafic applicatif classique, parce qu'elle n'a ni le même volume ni le même rythme.

## 2. Le socle technique retenu

| Couche | Choix | Rôle |
|---|---|---|
| Backend / API | **Laravel 13** (+ Laravel Boost) | Cœur métier, API, rôles, notifications, files d'attente, multi-tenant |
| Base relationnelle | **PostgreSQL** | Données métier : comptes, bouteilles, sites, commandes, stocks, tournées |
| Séries temporelles | **TimescaleDB** (extension PostgreSQL) | Historique des mesures de poids, calcul de débit et d'autonomie |
| Ingestion capteurs | **Broker MQTT** | Réception des mesures des plateaux, découplée de l'API web |
| Mobile | **React Native (Expo)** | Apps foyer, dépôt, mandataire, livreur |
| Web | **React 19** | Dashboards mandataire et distributeur sur ordinateur |
| Firmware plateau | **ESP32** (Wi-Fi) | Lecture des cellules de charge, émission MQTT |

Le détail du choix Laravel plutôt que FastAPI, et MQTT plutôt que REST pour les capteurs, est développé plus bas.

## 3. Le chemin de la donnée capteur

C'est le point d'architecture le plus spécifique au projet, à soigner en priorité.

**Pourquoi pas du REST classique.** Des milliers de plateaux qui envoient une mesure à intervalle régulier, ce n'est pas un trafic de type requête-réponse web. C'est un flux de petits messages, fréquents, depuis des appareils à faible puissance et sur réseau instable. Le protocole adapté est **MQTT** : léger, conçu pour l'IoT, tolérant aux coupures, économe en énergie côté capteur.

**Le trajet.** Le plateau (ESP32) lit ses cellules de charge, convertit le signal, et publie une mesure sur un *topic* MQTT propre à l'appareil. Le broker reçoit. Un consommateur côté Laravel — un worker qui écoute le broker — récupère la mesure, la valide, la range dans TimescaleDB, et déclenche si besoin la logique métier (recalcul d'autonomie, franchissement de seuil, alerte).

**Pourquoi ce découplage compte.** L'API web et l'ingestion capteur ont des profils opposés. L'API sert des écrans, par à-coups, avec des réponses riches. L'ingestion encaisse un flux continu de petits messages. Les séparer permet de dimensionner chacune indépendamment : un pic de mesures ne doit jamais ralentir l'affichage d'un dashboard, et inversement.

## 4. Le cœur métier : Laravel

**Pourquoi Laravel plutôt que FastAPI.** Le produit a besoin, dès la v1, de briques que Laravel fournit clé en main et que FastAPI demanderait de recomposer à la main :

- **Rôles et permissions multi-acteurs** — cinq rôles aux droits distincts.
- **Notifications multi-canal** — push, SMS, WhatsApp, avec une abstraction unique.
- **Files d'attente** — pour traiter les alertes, les propositions de livraison, les envois asynchrones sans bloquer.
- **Multi-tenant** — chaque dépôt, chaque mandataire opère sur son propre périmètre de données.

Laravel 13 apporte en plus des primitives AI first-party et de la recherche sémantique/vectorielle intégrées, utiles pour la future couche recettes sans greffer de package tiers. La contrepartie — un framework plus lourd que FastAPI — est sans conséquence ici : le goulot d'étranglement est l'ingestion capteur, pas le framework web.

**Ce que le cœur porte.** La logique de niveau et d'autonomie (déduire la masse restante du poids, calculer le débit, traduire en heures de flamme), la gestion des seuils et des alertes, le cycle de vie des commandes et livraisons, la tenue des stocks pleins et vides, la préparation des tournées, l'agrégation pour le distributeur.

## 5. Les données

**PostgreSQL** tient le modèle métier : utilisateurs et rôles, sites, plateaux, bouteilles (format, marque, tare), stocks, commandes, livraisons, tournées, relations entre acteurs.

**TimescaleDB** tient l'histoire des mesures. Chaque pesée est un point horodaté. Cette couche permet de calculer le débit de consommation observé (donc l'autonomie), de lisser le bruit de pesée, et plus tard d'alimenter les agrégats régionaux du distributeur. Le choix de TimescaleDB, comme extension de PostgreSQL, évite d'introduire une seconde base à opérer : une seule technologie, deux usages.

## 6. Les clients

**Mobile d'abord.** Foyer, dépôt, mandataire et livreur passent par des applications mobiles en **React Native (Expo)**, smartphone et tablette. Une base de code partagée, des expériences distinctes par rôle.

**Web pour le pilotage.** Les dashboards mandataire et distributeur, plus riches en tableaux et vues d'ensemble, existent aussi en **React 19** sur ordinateur. Le mandataire a donc les deux entrées, terrain (mobile) et bureau (web) ; le distributeur est surtout web.

**Fonctionnement dégradé.** Côté foyer, l'application garde en cache la dernière mesure connue et reste lisible hors ligne. Les commandes et les mesures se synchronisent au retour du réseau. Le réseau instable est une hypothèse de conception, pas un cas limite.

## 7. Les flux transversaux

**Notifications.** Un service unique adresse push, SMS et WhatsApp selon le canal disponible et les préférences de l'utilisateur. Les alertes de seuil, les propositions de livraison et les statuts de commande passent tous par là.

**Files d'attente.** Tout ce qui n'a pas à être synchrone — envoi d'alerte, préparation de proposition de tournée, agrégation — part en file, pour que l'expérience reste réactive et que les pics soient absorbés.

**Multi-tenant.** L'isolation des données par acteur professionnel est native : un dépôt ne voit que son quartier, un mandataire que son portefeuille, un distributeur que ses agrégats régionaux.

## 8. Sécurité et confidentialité

Les données individuelles de foyer ne remontent jamais telles quelles au distributeur : celui-ci ne reçoit que des agrégats. Les frontières multi-tenant garantissent qu'un acteur n'accède qu'à son périmètre. L'authentification et la gestion des rôles s'appuient sur les mécanismes natifs de Laravel.

## 9. Ce qui demande le plus de soin

Par ordre de risque technique :

1. **L'ingestion MQTT à l'échelle** — dimensionnement du broker, robustesse du consommateur, gestion des capteurs qui se reconnectent en masse après une coupure.
2. **La qualité de la mesure** — lissage du bruit de pesée, gestion de la tare incertaine, détection des mesures aberrantes (bouteille retirée, plateau bousculé).
3. **Le calcul d'autonomie** — un débit observé fiable malgré des usages irréguliers, pour que les heures de flamme annoncées tiennent la route.
4. **Le fonctionnement dégradé** — une expérience foyer qui reste crédible sur réseau faible.

Le reste — CRUD métier, dashboards, notifications — relève de développement standard bien outillé par Laravel et React.
