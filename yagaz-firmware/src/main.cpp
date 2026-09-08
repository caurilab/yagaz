// Firmware du plateau Yagaz — ESP32
// SQUELETTE Phase 0 : structure et TODO. La chaîne complète (lecture HX711,
// calibrage, lissage, publication MQTT selon ADR 0003) est implémentée en Phase 2.
//
// Topic de publication (voir docs/decisions/0003-format-messages-mqtt.md) :
//   yagaz/v1/plateau/{uid}/mesure   (QoS 1, JSON compact)

#include <Arduino.h>
// #include <WiFi.h>
// #include <PubSubClient.h>
// #include <HX711.h>
// #include <ArduinoJson.h>
// #include "secrets.h"

void setup() {
  Serial.begin(115200);
  Serial.println("Yagaz plateau — squelette firmware (Phase 0).");
  // TODO Phase 2 :
  //  - init HX711 (broches DOUT/SCK), tare, facteur d'échelle (calibrage)
  //  - connexion Wi-Fi puis MQTT (avec Last-Will sur .../etat)
  //  - boucle de mesure adaptative (souvent si conso active, sinon veille)
}

void loop() {
  // TODO Phase 2 : lire poids -> lisser -> publier mesure JSON en MQTT.
  delay(1000);
}
