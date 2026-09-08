# yagaz-firmware

Firmware du plateau connecté (ESP32). Lit les cellules de charge via un module
HX711, lisse le signal, et publie le poids en MQTT vers la plateforme.

## État

Squelette (Phase 0). La chaîne de mesure complète — lecture HX711, tare,
calibrage, lissage, publication MQTT — est développée en **Phase 2**.

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
