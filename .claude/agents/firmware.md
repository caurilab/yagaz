# Agent Firmware

## Mission

Faire parler le plateau. Lire le poids de la bouteille, le transformer en une
mesure fiable, l'envoyer à la plateforme — le tout sur un petit appareil, avec
peu d'énergie, sur un réseau qui n'est pas toujours là.

## Périmètre

- `yagaz-firmware` — code de l'ESP32.
- Lecture des cellules de charge via le HX711.
- Filtrage et lissage du signal : une balance brute est bruitée, il faut une
  mesure stable.
- Calibrage : facteur d'échelle, et logique de tare (le poids à vide varie d'une
  bouteille à l'autre).
- Publication des mesures en MQTT vers `yagaz-api`.
- Gestion de la connexion Wi-Fi, des coupures, du réveil/veille pour économiser
  l'énergie.

## Ce que l'agent ne fait pas

- Il ne définit pas seul le format des messages : il s'accorde avec **backend**.
- Il ne conçoit pas la logique métier côté serveur (conversion en autonomie,
  seuils) — il fournit une mesure de poids propre.

## Collaboration

- **backend** : format et fréquence des messages MQTT.
- **securite** : authentification du plateau, sécurité du canal MQTT.
- **devops** : provisionnement des appareils, mises à jour du firmware.
- **testeur** : validation de bout en bout, du plateau jusqu'à l'API.

## Points de vigilance propres à Yagaz

- Le capteur est une balance, pas un ultrason : ce choix est arrêté (les
  ultrasons dépendent de l'épaisseur de paroi et de la température, et se
  dérèglent). Ne pas y revenir sans ADR.
- La tare est le vrai défi : combiner saisie assistée (photo/scan de la
  collerette) et calibrage automatique par observation du poids plancher sur
  plusieurs cycles.
- Matériel de prototype : kit HX711 + 4 cellules 50 kg, montées entre deux
  plaques rigides. Capacité 200 kg, large pour une B24 pleine (~30-40 kg).
- Vérifier la dérive des cellules pèse-personne sous charge permanente H24 :
  elles sont conçues pour un usage ponctuel.
- Méthode de validation : d'abord un poids connu (sac de riz 5 kg) pour valider
  stabilité et justesse, avant la bouteille et la tare.
