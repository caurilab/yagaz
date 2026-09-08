// Firmware du plateau Yagaz — ESP32
// Phase 2 : lecture HX711 (4 cellules de charge en pont), lissage léger,
// échantillonnage adaptatif, publication MQTT conforme à l'ADR 0003.
// Phase 3 : capteur de température de cuisine (DS18B20), publication MQTT
// conforme à l'ADR 0011.
//
// Matériel : voir docs/05-materiel-et-sourcing.md (4 cellules + HX711 + ESP32).
// Format MQTT : voir docs/decisions/0003-format-messages-mqtt.md (topics + JSON).
// Rôle du plateau dans la chaîne de mesure : voir docs/08-chaine-de-mesure.md.
// Capteur de température cuisine : voir docs/decisions/0011-capteur-temperature-cuisine.md.
//
// NB : ce fichier n'a pas été compilé ici (pas de toolchain PlatformIO dans cet
// environnement). Vérification faite "à la main" des API de libs ; compilation
// réelle (`pio run`) à faire sur un poste équipé avant flash.

#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <HX711.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <time.h>
#include <math.h>
#include "secrets.h"

// ---------------------------------------------------------------------------
// Configuration — broches
// ---------------------------------------------------------------------------

const uint8_t HX711_DOUT_PIN = 16;
const uint8_t HX711_SCK_PIN  = 4;

// Capteur de température de cuisine (ADR 0011) — bus 1-Wire, une seule broche
// data (avec résistance de tirage ~4.7kΩ vers 3.3V, cf. datasheet DS18B20).
const uint8_t PIN_TEMP_ONEWIRE = 17;

// Suivi batterie (optionnel, désactivé par défaut — doc 05 §4 : alimentation
// secteur ou batterie à trancher au prototypage). Mettre une broche ADC valide
// et ajuster le diviseur de tension si un montage sur batterie est retenu.
const int PIN_BATTERIE_ADC = -1; // -1 = suivi batterie désactivé
const float DIVISEUR_TENSION_BATTERIE = 2.0f; // à ajuster au pont diviseur réel

// ---------------------------------------------------------------------------
// Configuration — calibrage HX711
// ---------------------------------------------------------------------------
//
// Procédure de calibrage (à faire une fois par plateau, en atelier) :
//   1. Plateau posé, rien dessus -> commande série "TARE".
//      (tare électronique du plateau NU ; ne pas confondre avec la tare de la
//      bouteille, qui est gérée côté serveur — doc 08 §5. Ici on veut que le
//      plateau à vide indique 0 g.)
//   2. Poser un poids connu et stable (ex. 5 kg) -> commande série "CAL:5000"
//      (grammes). Le firmware calcule le facteur d'échelle et l'affiche.
//   3. Le facteur calculé est persisté en NVS (Preferences) : il survit aux
//      redémarrages sans recalibrage. FACTEUR_ECHELLE_DEFAUT ci-dessous n'est
//      qu'une valeur de secours tant qu'aucun calibrage n'a été fait.
const float FACTEUR_ECHELLE_DEFAUT = 420.0f; // valeur indicative, À RECALIBRER par plateau

const uint8_t HX711_LECTURES_MOYENNE = 10; // nb lectures moyennées par pesée (bruit HX711)

// ---------------------------------------------------------------------------
// Configuration — lissage léger firmware
// ---------------------------------------------------------------------------
//
// Le gros du lissage (EMA / médiane glissante) est fait côté serveur (doc 08
// §4) sur la mesure brute stockée. Ici on ne fait qu'éviter d'envoyer du bruit
// évident : moyenne mobile courte + rejet des pics isolés non soutenus.

const int TAILLE_FENETRE_LISSAGE = 5;
const float SEUIL_REJET_ABERRANT_G = 800.0f; // écart à la moyenne courante jugé "pic"
const uint8_t MAX_REJETS_CONSECUTIFS = 3;    // au-delà, on accepte (vrai changement de poids)

// Garde-fou de plausibilité physique, en miroir du contrôle backend (doc 08 §10).
// But : ne pas laisser un glitch capteur polluer même la fenêtre de lissage.
const int32_t POIDS_MAX_ABSOLU_G   = 60000; // rejet aberration haute
const int32_t POIDS_MIN_PLAUSIBLE_G = -2000; // marge négative tolérée (bruit autour de 0)

// ---------------------------------------------------------------------------
// Configuration — échantillonnage adaptatif (doc 05 §4)
// ---------------------------------------------------------------------------
//
// Mesurer souvent quand le poids bouge (consommation active ou manipulation),
// espacer quand il est stable, pour préserver la batterie si le plateau n'est
// pas sur secteur. Deep sleep NON implémenté ici : il remettrait à zéro la RAM
// (seq, fenêtre de lissage, état Wi-Fi/MQTT) à chaque réveil, ce qui demande
// une persistance NVS plus lourde que ce que permet ce sprint ; à évaluer en
// Phase suivante si l'alimentation batterie est retenue. L'intervalle long
// ci-dessous joue déjà le rôle d'économie d'énergie/réseau en mode "veille".

const unsigned long INTERVALLE_MESURE_ACTIF_MS = 2000;   // poids qui bouge
const unsigned long INTERVALLE_MESURE_REPOS_MS = 30000;  // poids stable
const float SEUIL_VARIATION_ACTIVE_G = 20.0f;            // variation qui déclenche le mode actif
const unsigned long DUREE_MAINTIEN_ACTIF_MS = 60000;     // reste actif 60 s après le dernier mouvement

// ---------------------------------------------------------------------------
// Configuration — réseau / MQTT / NTP
// ---------------------------------------------------------------------------

const unsigned long INTERVALLE_RETRY_WIFI_MS = 5000;
const unsigned long INTERVALLE_RETRY_MQTT_MS = 5000;
const unsigned long TIMEOUT_WIFI_INITIAL_MS  = 15000;
const unsigned long TIMEOUT_NTP_INITIAL_MS   = 10000;

const char* NTP_SERVEUR = "pool.ntp.org";
// ts de l'ADR 0003 est un epoch UTC : pas de décalage horaire ni d'heure d'été.
const long NTP_GMT_OFFSET_SEC = 0;
const int  NTP_DAYLIGHT_OFFSET_SEC = 0;
const time_t SEUIL_EPOCH_PLAUSIBLE = 1700000000; // ~nov. 2023 : détecte une horloge non synchronisée

const uint8_t INTERVALLE_PERSISTENCE_SEQ = 20; // persiste "seq" en NVS toutes les N publications (usure flash)

// ---------------------------------------------------------------------------
// Configuration — température de cuisine (ADR 0011)
// ---------------------------------------------------------------------------
//
// Cadence : 30 s. Compromis réactivité/charge réseau : la détection de
// cuisson (ADR 0011) utilise une fenêtre anti-rebond `duree_min_cuisson_s` de
// 120 s côté backend, donc 30 s donne ~4 points par fenêtre (suffisant pour
// une détection soutenue) sans multiplier le trafic MQTT. À resserrer (ex.
// 10-15 s) si un suivi plus fin de la montée en cuisson est souhaité, au prix
// d'un peu plus de trafic réseau/veille.
const unsigned long INTERVALLE_PUBLICATION_TEMPERATURE_MS = 30000;

// Durée de conversion DS18B20 à la résolution par défaut (12 bits, datasheet
// Maxim/Dallas). Utilisée pour piloter la lecture en non-bloquant (cf.
// boucleTemperatureCuisine) sans jamais appeler delay().
const unsigned long DUREE_CONVERSION_DS18B20_MS = 750;

// ---------------------------------------------------------------------------
// État global
// ---------------------------------------------------------------------------

HX711 balance;
WiFiClient wifiClient;
PubSubClient mqttClient(wifiClient);
Preferences preferences;

OneWire oneWireTemperature(PIN_TEMP_ONEWIRE);
DallasTemperature capteurTemperatureCuisine(&oneWireTemperature);

String topicMesure;
String topicEtat;
String topicCmd;
String topicTemperature;

unsigned long seqCompteur = 0;

// Compteur "seq" séparé de celui des mesures de poids : la température est
// publiée sur son propre topic/payload (ADR 0011) à une cadence différente,
// donc un compteur dédié est plus simple à raisonner côté backend (dédup
// indépendante) que de partager celui des mesures. Persisté en NVS sous une
// clé distincte ("seqTemp"), avec la même politique d'écriture périodique.
unsigned long seqTemperatureCompteur = 0;

bool capteurTemperatureCuisineDetecte = false;
bool conversionTemperatureEnCours = false;
unsigned long debutConversionTemperatureMs = 0;
unsigned long dernierTempsTemperatureMs = 0;
float derniereTemperatureCuisineC = NAN;

float fenetreLissage[TAILLE_FENETRE_LISSAGE];
int nbEchantillonsFenetre = 0;
int indexFenetre = 0;
uint8_t rejetsConsecutifs = 0;

int32_t dernierPoidsPublie = 0;
unsigned long dernierMouvementMs = 0;
unsigned long intervalleCourantMs = INTERVALLE_MESURE_ACTIF_MS;
unsigned long dernierTempsMesureMs = 0;

unsigned long dernierEssaiWifiMs = 0;
unsigned long dernierEssaiMqttMs = 0;

// ---------------------------------------------------------------------------
// Déclarations anticipées (fichier .cpp : pas de génération auto de
// prototypes comme avec un .ino)
// ---------------------------------------------------------------------------

void setupWifi();
void assurerConnexionWifi();
void setupNtp();
bool obtenirEpoch(time_t &epoch);
void mqttCallback(char* topic, byte* payload, unsigned int length);
bool connecterMqtt();
void assurerConnexionMqtt();
float moyenneFenetre();
float lisserPoids(float brut);
void ajusterIntervalleAdaptatif(int32_t poidsActuel);
int lireTensionBatterieMv();
float lireTemperatureC();
void publierMesure(int32_t poids_g);
void boucleMesure();
void gererCommandeSerie();
void setupTemperatureCuisine();
float lireTemperatureCuisine();
void publierTemperatureCuisine(float temp_c);
void boucleTemperatureCuisine();

// ---------------------------------------------------------------------------
// Wi-Fi
// ---------------------------------------------------------------------------

void setupWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connexion Wi-Fi");
  unsigned long debut = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - debut < TIMEOUT_WIFI_INITIAL_MS) {
    delay(250);
    Serial.print(".");
  }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("Wi-Fi connecté, IP=%s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("Wi-Fi non connecté au démarrage ; réessai automatique en boucle.");
  }
}

// Reconnexion Wi-Fi non bloquante, appelée à chaque tour de boucle.
void assurerConnexionWifi() {
  if (WiFi.status() == WL_CONNECTED) return;
  unsigned long maintenant = millis();
  if (maintenant - dernierEssaiWifiMs < INTERVALLE_RETRY_WIFI_MS) return;
  dernierEssaiWifiMs = maintenant;
  Serial.println("Reconnexion Wi-Fi...");
  WiFi.disconnect();
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
}

// ---------------------------------------------------------------------------
// NTP (horodatage epoch UTC pour "ts", ADR 0003)
// ---------------------------------------------------------------------------

void setupNtp() {
  configTime(NTP_GMT_OFFSET_SEC, NTP_DAYLIGHT_OFFSET_SEC, NTP_SERVEUR);
  Serial.print("Synchronisation NTP");
  unsigned long debut = millis();
  time_t maintenant = time(nullptr);
  while (maintenant < SEUIL_EPOCH_PLAUSIBLE && millis() - debut < TIMEOUT_NTP_INITIAL_MS) {
    delay(250);
    Serial.print(".");
    maintenant = time(nullptr);
  }
  Serial.println();
  if (maintenant >= SEUIL_EPOCH_PLAUSIBLE) {
    Serial.println("Heure NTP synchronisée.");
  } else {
    Serial.println("NTP non synchronisé au démarrage ; les mesures seront retardées jusqu'à synchro.");
  }
}

// Renvoie l'heure courante si elle est plausible (évite de publier un ts
// proche de l'epoch 0 quand le NTP n'a pas encore répondu).
bool obtenirEpoch(time_t &epoch) {
  epoch = time(nullptr);
  return epoch >= SEUIL_EPOCH_PLAUSIBLE;
}

// ---------------------------------------------------------------------------
// MQTT
// ---------------------------------------------------------------------------

// Callback d'abonnement — structure prête pour de futures commandes reçues
// sur yagaz/v1/plateau/{uid}/cmd. Aucune commande traitée en Phase 2.
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  Serial.printf("Commande reçue sur %s (%u octets) — non traitée (Phase 2).\n", topic, length);
}

bool connecterMqtt() {
  Serial.print("Connexion MQTT...");

  // Last Will retained : le broker publie ceci automatiquement si le plateau
  // tombe sans clean disconnect (ADR 0003).
  const char* etatHorsLigne = "{\"online\":false}";

  bool ok = mqttClient.connect(
      PLATEAU_UID,        // client id
      PLATEAU_UID,        // username = uid du plateau (doc 08 §2)
      MQTT_PASSWORD,       // password = secret du plateau
      topicEtat.c_str(),   // topic du testament (LWT)
      1,                   // QoS du testament
      true,                // retained
      etatHorsLigne);

  if (ok) {
    Serial.println(" connecté.");
    // Signale la présence en ligne, retained, dès la connexion établie.
    mqttClient.publish(topicEtat.c_str(), "{\"online\":true}", true);
    mqttClient.subscribe(topicCmd.c_str());
  } else {
    Serial.printf(" échec (rc=%d).\n", mqttClient.state());
  }
  return ok;
}

// Reconnexion MQTT non bloquante, appelée à chaque tour de boucle.
void assurerConnexionMqtt() {
  if (mqttClient.connected()) return;
  if (WiFi.status() != WL_CONNECTED) return; // inutile sans réseau
  unsigned long maintenant = millis();
  if (maintenant - dernierEssaiMqttMs < INTERVALLE_RETRY_MQTT_MS) return;
  dernierEssaiMqttMs = maintenant;
  connecterMqtt();
}

// ---------------------------------------------------------------------------
// Lissage léger
// ---------------------------------------------------------------------------

float moyenneFenetre() {
  if (nbEchantillonsFenetre == 0) return 0.0f;
  float somme = 0.0f;
  for (int i = 0; i < nbEchantillonsFenetre; i++) {
    somme += fenetreLissage[i];
  }
  return somme / nbEchantillonsFenetre;
}

// Moyenne mobile courte + rejet des pics isolés (bousculade, frôlement).
// Un écart soutenu (au-delà de MAX_REJETS_CONSECUTIFS lectures) est accepté :
// ce n'est plus du bruit mais un vrai changement (bouteille posée/retirée).
float lisserPoids(float brut) {
  if (nbEchantillonsFenetre > 0) {
    float moyenneCourante = moyenneFenetre();
    if (fabs(brut - moyenneCourante) > SEUIL_REJET_ABERRANT_G &&
        rejetsConsecutifs < MAX_REJETS_CONSECUTIFS) {
      rejetsConsecutifs++;
      return moyenneCourante; // pic ignoré, on republie la moyenne actuelle
    }
  }
  rejetsConsecutifs = 0;
  fenetreLissage[indexFenetre] = brut;
  indexFenetre = (indexFenetre + 1) % TAILLE_FENETRE_LISSAGE;
  if (nbEchantillonsFenetre < TAILLE_FENETRE_LISSAGE) nbEchantillonsFenetre++;
  return moyenneFenetre();
}

// ---------------------------------------------------------------------------
// Échantillonnage adaptatif
// ---------------------------------------------------------------------------

void ajusterIntervalleAdaptatif(int32_t poidsActuel) {
  long variation = (long)poidsActuel - (long)dernierPoidsPublie;
  if (variation < 0) variation = -variation;
  if (variation >= (long)SEUIL_VARIATION_ACTIVE_G) {
    dernierMouvementMs = millis();
  }
  bool mouvementRecent = (millis() - dernierMouvementMs) < DUREE_MAINTIEN_ACTIF_MS;
  intervalleCourantMs = mouvementRecent ? INTERVALLE_MESURE_ACTIF_MS : INTERVALLE_MESURE_REPOS_MS;
}

// ---------------------------------------------------------------------------
// Champs optionnels (batterie, température)
// ---------------------------------------------------------------------------

// Renvoie la tension batterie en mV, ou -1 si le suivi batterie est désactivé
// (PIN_BATTERIE_ADC < 0 — cas par défaut, plateau sur secteur, doc 05 §4).
int lireTensionBatterieMv() {
  if (PIN_BATTERIE_ADC < 0) return -1;
  int lectureAdc = analogRead(PIN_BATTERIE_ADC); // 0-4095 (ADC 12 bits ESP32)
  float tensionAdcMv = (lectureAdc / 4095.0f) * 3300.0f; // réf. ADC 3.3V
  return (int) lroundf(tensionAdcMv * DIVISEUR_TENSION_BATTERIE);
}

// NB : cette fonction est distincte de lireTemperatureCuisine() ci-dessous
// (ADR 0011). Elle vise une éventuelle sonde de dérive thermique des cellules
// de charge elles-mêmes (électronique du plateau), pas la température
// ambiante de la cuisine. Pas de capteur dédié dans la configuration
// matérielle actuelle (doc 05 : cellules + HX711 + ESP32 seuls). Renvoie NAN
// => champ "temp_c" omis du JSON de "mesure". Prévu pour une future sonde
// NTC/DS18B20 si la dérive thermique des cellules l'exige (doc 05 §6).
float lireTemperatureC() {
  return NAN;
}

// ---------------------------------------------------------------------------
// Capteur de température de cuisine (DS18B20, 1-Wire) — ADR 0011
// ---------------------------------------------------------------------------
//
// Capteur retenu : DS18B20 (1-Wire, digital, ±0.5°C entre -10 et +85°C,
// plage -55..+125°C). Choisi comme défaut robuste pour l'ambiance cuisine
// (proximité de chaleur/vapeur) : sortie numérique peu sensible au bruit
// électrique (contrairement à une thermistance NTC lue en analogique, plus
// simple/moins chère mais plus sensible au bruit ADC et nécessitant un
// calibrage du pont diviseur). Prendre une version étanche (sonde à câble,
// gaine inox) si le montage est exposé aux projections de cuisine.
// Libs : milesburton/DallasTemperature + paulstoffregen/OneWire (versions
// épinglées dans platformio.ini).
//
// Lecture non bloquante : setWaitForConversion(false) + machine à 2 états
// dans boucleTemperatureCuisine(), pour ne jamais retarder boucleMesure()
// (contrairement à un requestTemperatures() bloquant, qui figerait la boucle
// ~750ms à chaque cycle).

void setupTemperatureCuisine() {
  capteurTemperatureCuisine.begin();
  capteurTemperatureCuisine.setWaitForConversion(false); // conversion asynchrone, pilotée par millis()
  capteurTemperatureCuisineDetecte = capteurTemperatureCuisine.getDeviceCount() > 0;
  if (capteurTemperatureCuisineDetecte) {
    Serial.println("Capteur DS18B20 (température cuisine) détecté.");
  } else {
    Serial.println("DS18B20 non détecté (PIN_TEMP_ONEWIRE) ; température cuisine non publiée.");
  }
}

// Renvoie la dernière température de cuisine connue (°C), ou NAN si aucun
// capteur n'est détecté ou si aucune conversion n'a encore abouti — dans ce
// cas on ne publie pas (cf. boucleTemperatureCuisine). Ne bloque jamais :
// c'est un simple accesseur, le déclenchement/la lecture de la conversion
// DS18B20 sont pilotés par boucleTemperatureCuisine() via millis().
float lireTemperatureCuisine() {
  if (!capteurTemperatureCuisineDetecte) return NAN;
  return derniereTemperatureCuisineC;
}

// ---------------------------------------------------------------------------
// Publication MQTT (ADR 0003)
// ---------------------------------------------------------------------------

void publierMesure(int32_t poids_g) {
  if (!mqttClient.connected()) {
    Serial.println("MQTT non connecté, mesure non publiée.");
    return;
  }

  time_t epoch;
  if (!obtenirEpoch(epoch)) {
    Serial.println("Heure NTP non synchronisée, mesure non publiée.");
    return;
  }

  JsonDocument doc; // ArduinoJson v7 : JsonDocument (élastique), pas StaticJsonDocument
  doc["v"] = 1;
  doc["ts"] = (uint32_t) epoch;
  doc["poids_g"] = poids_g;
  doc["seq"] = seqCompteur;
  doc["rssi"] = WiFi.RSSI();

  int battMv = lireTensionBatterieMv();
  if (battMv >= 0) doc["batt_mv"] = battMv;

  float tempC = lireTemperatureC();
  if (!isnan(tempC)) doc["temp_c"] = tempC;

  char buffer[256];
  size_t taille = serializeJson(doc, buffer, sizeof(buffer));

  // NOTE IMPORTANTE : knolleary/PubSubClient ne publie qu'en QoS 0 côté
  // client (pas d'attente de PUBACK) ; l'ADR 0003 demande QoS 1 pour
  // "mesure". Le dédoublonnage par "seq" côté backend (doc 08 §3.3) absorbe
  // les pertes/répétitions résiduelles. À vérifier au flash réel : si un vrai
  // QoS 1 est requis, il faudra une lib gérant le PUBACK (ex. esp-mqtt natif
  // IDF, ou une lib tierce type "MQTT" d'Arduino-ESP32).
  bool ok = mqttClient.publish(topicMesure.c_str(), (const uint8_t*)buffer, taille, false);

  if (ok) {
    seqCompteur++;
    if (seqCompteur % INTERVALLE_PERSISTENCE_SEQ == 0) {
      preferences.putULong("seq", seqCompteur); // persistance périodique (usure flash NVS)
    }
    Serial.printf("Mesure publiée seq=%lu poids=%ld g rssi=%d\n",
                  seqCompteur, (long)poids_g, WiFi.RSSI());
  } else {
    Serial.println("Échec de la publication MQTT.");
  }
}

// ---------------------------------------------------------------------------
// Publication MQTT — température de cuisine (ADR 0011)
// ---------------------------------------------------------------------------

void publierTemperatureCuisine(float temp_c) {
  if (!mqttClient.connected()) return; // assurerConnexionMqtt() gère déjà la reconnexion

  time_t epoch;
  if (!obtenirEpoch(epoch)) return; // horloge NTP pas encore synchronisée

  JsonDocument doc;
  doc["v"] = 1;
  doc["ts"] = (uint32_t) epoch;
  doc["temp_c"] = temp_c;
  doc["seq"] = seqTemperatureCompteur;

  char buffer[128];
  size_t taille = serializeJson(doc, buffer, sizeof(buffer));

  // Même limite QoS que publierMesure() : PubSubClient ne fait que du QoS 0
  // côté client. Le dédoublonnage par "seq" (ADR 0011) absorbe les pertes.
  bool ok = mqttClient.publish(topicTemperature.c_str(), (const uint8_t*)buffer, taille, false);

  if (ok) {
    seqTemperatureCompteur++;
    if (seqTemperatureCompteur % INTERVALLE_PERSISTENCE_SEQ == 0) {
      preferences.putULong("seqTemp", seqTemperatureCompteur); // clé NVS distincte de "seq" (mesures)
    }
    Serial.printf("Température cuisine publiée seq=%lu temp=%.1f°C\n", seqTemperatureCompteur, temp_c);
  } else {
    Serial.println("Échec de la publication MQTT (température).");
  }
}

// Machine à 2 états non bloquante :
//   1. au bout de INTERVALLE_PUBLICATION_TEMPERATURE_MS, on lance une
//      conversion DS18B20 (asynchrone, setWaitForConversion(false)) ;
//   2. au tour de boucle où DUREE_CONVERSION_DS18B20_MS s'est écoulée depuis,
//      on va chercher le résultat et on publie.
// Appelée à chaque tour de loop() ; ne retarde jamais boucleMesure().
void boucleTemperatureCuisine() {
  if (!capteurTemperatureCuisineDetecte) return;
  unsigned long maintenant = millis();

  if (conversionTemperatureEnCours) {
    if (maintenant - debutConversionTemperatureMs < DUREE_CONVERSION_DS18B20_MS) return;
    conversionTemperatureEnCours = false;
    float lue = capteurTemperatureCuisine.getTempCByIndex(0);
    if (lue == DEVICE_DISCONNECTED_C) {
      Serial.println("Lecture DS18B20 invalide (capteur déconnecté ?), non publiée.");
      return;
    }
    derniereTemperatureCuisineC = lue;
    // lireTemperatureCuisine() gère l'absence de capteur (NAN) : on ne publie
    // que si elle renvoie une valeur exploitable.
    float temperaturePourPublication = lireTemperatureCuisine();
    if (!isnan(temperaturePourPublication)) {
      publierTemperatureCuisine(temperaturePourPublication);
    }
    return;
  }

  if (maintenant - dernierTempsTemperatureMs < INTERVALLE_PUBLICATION_TEMPERATURE_MS) return;
  dernierTempsTemperatureMs = maintenant;
  capteurTemperatureCuisine.requestTemperatures(); // async (setWaitForConversion(false) en setup)
  debutConversionTemperatureMs = maintenant;
  conversionTemperatureEnCours = true;
}

// ---------------------------------------------------------------------------
// Boucle de mesure
// ---------------------------------------------------------------------------

void boucleMesure() {
  unsigned long maintenant = millis();
  if (maintenant - dernierTempsMesureMs < intervalleCourantMs) return;
  dernierTempsMesureMs = maintenant;

  if (!balance.is_ready()) {
    Serial.println("HX711 non prêt, mesure ignorée.");
    return;
  }

  float brut = balance.get_units(HX711_LECTURES_MOYENNE);

  // Garde-fou de plausibilité physique avant même le lissage (glitch capteur).
  if (brut > POIDS_MAX_ABSOLU_G || brut < POIDS_MIN_PLAUSIBLE_G) {
    Serial.printf("Lecture HX711 hors plage plausible (%.0f g), ignorée.\n", brut);
    return;
  }

  float lisse = lisserPoids(brut);
  int32_t poids_g = (int32_t) lroundf(lisse);
  if (poids_g < 0) poids_g = 0; // pas de poids négatif publié (bruit autour de la tare)

  ajusterIntervalleAdaptatif(poids_g);
  dernierPoidsPublie = poids_g;

  publierMesure(poids_g);
}

// ---------------------------------------------------------------------------
// Calibrage déclenchable via commande série
// ---------------------------------------------------------------------------

void gererCommandeSerie() {
  if (!Serial.available()) return;
  String ligne = Serial.readStringUntil('\n');
  ligne.trim();
  if (ligne.length() == 0) return;

  if (ligne.equalsIgnoreCase("TARE")) {
    Serial.println("Tare du plateau nu en cours (rien ne doit être posé dessus)...");
    balance.tare(HX711_LECTURES_MOYENNE);
    long offset = balance.get_offset();
    preferences.putLong("offset", offset);
    Serial.printf("Tare effectuée. offset=%ld (persisté en NVS)\n", offset);
    return;
  }

  if (ligne.startsWith("CAL:")) {
    float poidsConnu_g = ligne.substring(4).toFloat();
    if (poidsConnu_g <= 0) {
      Serial.println("Poids de calibrage invalide. Usage : CAL:<grammes>, ex. CAL:5000");
      return;
    }
    Serial.printf("Calibrage avec un poids connu de %.1f g. Ne pas toucher le plateau...\n", poidsConnu_g);
    long lectureBrute = balance.read_average(HX711_LECTURES_MOYENNE);
    float nouveauFacteur = (lectureBrute - balance.get_offset()) / poidsConnu_g;
    if (nouveauFacteur == 0) {
      Serial.println("Facteur calculé nul, calibrage annulé (vérifier le câblage).");
      return;
    }
    balance.set_scale(nouveauFacteur);
    preferences.putFloat("scale", nouveauFacteur);
    Serial.printf("Nouveau facteur d'échelle = %.4f (persisté en NVS)\n", nouveauFacteur);
    return;
  }

  Serial.println("Commande inconnue. Commandes disponibles : TARE | CAL:<poids_connu_g>");
}

// ---------------------------------------------------------------------------
// setup / loop
// ---------------------------------------------------------------------------

void setup() {
  Serial.begin(115200);
  delay(200);
  Serial.println("Yagaz plateau — démarrage du firmware.");

  topicMesure      = String("yagaz/v1/plateau/") + PLATEAU_UID + "/mesure";
  topicEtat        = String("yagaz/v1/plateau/") + PLATEAU_UID + "/etat";
  topicCmd         = String("yagaz/v1/plateau/") + PLATEAU_UID + "/cmd";
  topicTemperature = String("yagaz/v1/plateau/") + PLATEAU_UID + "/temperature";

  preferences.begin("yagaz", false);
  seqCompteur = preferences.getULong("seq", 0);
  seqTemperatureCompteur = preferences.getULong("seqTemp", 0);
  long offsetPersiste = preferences.getLong("offset", 0);
  float facteurPersiste = preferences.getFloat("scale", FACTEUR_ECHELLE_DEFAUT);

  balance.begin(HX711_DOUT_PIN, HX711_SCK_PIN);
  if (balance.wait_ready_timeout(2000)) {
    if (offsetPersiste != 0) {
      balance.set_offset(offsetPersiste); // calibrage antérieur retrouvé en NVS
    } else {
      balance.tare(HX711_LECTURES_MOYENNE); // aucun calibrage connu : tare au démarrage
    }
    balance.set_scale(facteurPersiste);
  } else {
    Serial.println("HX711 non détecté au démarrage (vérifier câblage DOUT/SCK).");
  }

  setupTemperatureCuisine();

  setupWifi();
  setupNtp();

  mqttClient.setServer(MQTT_HOST, MQTT_PORT);
  mqttClient.setCallback(mqttCallback);
  connecterMqtt();

  Serial.println("Commandes série disponibles : TARE | CAL:<poids_connu_g>");
}

void loop() {
  assurerConnexionWifi();
  assurerConnexionMqtt();
  if (mqttClient.connected()) {
    mqttClient.loop();
  }
  gererCommandeSerie();
  boucleMesure();
  boucleTemperatureCuisine();
}
