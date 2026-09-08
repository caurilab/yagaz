# yagaz-firmware

Firmware du plateau connecté (ESP32). Lit les cellules de charge via un module
HX711, lisse le signal, et publie le poids en MQTT vers la plateforme. Lit
aussi la température ambiante de la cuisine (DS18B20) et la publie séparément.

## État

Phase 2 : chaîne de mesure complète — lecture HX711, tare/calibrage
persistés en NVS, lissage léger, échantillonnage adaptatif, Wi-Fi + MQTT
(LWT, publication conforme à l'ADR 0003).
Phase 3 : capteur de température de cuisine (DS18B20), publication conforme
à l'ADR 0011 (voir section dédiée ci-dessous).
Non compilé/flashé dans cet environnement : `pio run` reste à faire sur un
poste équipé PlatformIO.

Calibrage (commandes série, 115200 bauds) :
- `TARE` : plateau nu, sans bouteille — remet le zéro électronique.
- `CAL:<poids_g>` : poids connu posé — calcule et persiste le facteur d'échelle.

## Build (PlatformIO)

```bash
# Prérequis : PlatformIO Core (pip install platformio) ou l'extension VS Code.
cp src/secrets.example.h src/secrets.h   # puis renseigner Wi-Fi + MQTT
pio run                                    # compiler
pio run -t upload                          # flasher l'ESP32
pio device monitor                         # console série
```

## Matériel

Voir `docs/05-materiel-et-sourcing.md` : 4 cellules de charge en pont, module
HX711, ESP32 DevKit.

## Contrat MQTT

Le format des messages est figé dans `docs/decisions/0003-format-messages-mqtt.md`.
Topic : `yagaz/v1/plateau/{uid}/mesure`.

## Température de cuisine (ADR 0011)

Capteur retenu : **DS18B20** (1-Wire, numérique), branché sur la broche
`PIN_TEMP_ONEWIRE` (GPIO17 par défaut, résistance de tirage ~4.7kΩ vers 3.3V
requise sur le bus 1-Wire). Choisi comme défaut robuste pour l'ambiance
cuisine (chaleur, vapeur) : sortie numérique peu sensible au bruit électrique,
contrairement à une thermistance NTC lue en analogique (alternative moins
chère mais plus sensible au bruit ADC et nécessitant un calibrage du pont
diviseur). Prendre une version étanche (sonde à câble, gaine inox) si le
montage est exposé aux projections.

Libs ajoutées (`platformio.ini`, versions épinglées) :
`paulstoffregen/OneWire` + `milesburton/DallasTemperature`.

Fonctionnement :
- `lireTemperatureCuisine()` renvoie la dernière température lue (°C), ou
  `NAN` si aucun capteur n'est détecté — dans ce cas rien n'est publié.
- Lecture **non bloquante** : la conversion DS18B20 (~750 ms) est lancée en
  asynchrone (`setWaitForConversion(false)`) et relevée au tour de boucle
  suivant via `millis()`, sans jamais retarder `boucleMesure()` (pas de
  `delay()`).
- Publication toutes les **30 s** (`INTERVALLE_PUBLICATION_TEMPERATURE_MS`) :
  compromis entre réactivité de détection de cuisson (fenêtre anti-rebond
  backend de 120 s, ADR 0011 — 30 s donne ~4 points par fenêtre) et charge
  réseau. À resserrer (ex. 10-15 s) si un suivi plus fin de la montée en
  cuisson est souhaité.
- Topic dédié `yagaz/v1/plateau/{uid}/temperature`, payload JSON
  (ADR 0011) : `{"v":1,"ts":<epoch s>,"temp_c":<float>,"seq":<int>}`.
- Compteur `seq` **séparé** de celui des mesures de poids (cadence et topic
  différents), persisté en NVS sous une clé distincte (`seqTemp`).
- QoS aligné sur l'existant : PubSubClient ne fait que du QoS 0 côté client ;
  le dédoublonnage par `seq` côté backend absorbe les pertes/répétitions.

Aucun nouveau secret Wi-Fi/MQTT : le capteur de température réutilise la même
connexion que le reste du plateau (`src/secrets.h` inchangé).

À vérifier au flash réel : câblage 1-Wire (résistance de tirage, longueur de
câble), détection effective du capteur (`getDeviceCount()`), et calage de
`PIN_TEMP_ONEWIRE` sur une broche libre selon le brochage réel du plateau.
