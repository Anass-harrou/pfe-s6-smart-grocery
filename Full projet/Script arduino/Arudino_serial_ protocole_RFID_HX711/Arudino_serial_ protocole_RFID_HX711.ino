/*
* Smart Cart System
* Par Anass-harroui
* Date: 2025-06-22
*
* Ce système intègre:
* - Scanner RFID pour identification des produits
* - LEDs (jaune et rouge) pour le feedback visuel
* - Buzzer pour le feedback audio
* - Capteur de poids HX711 pour peser les produits
*/

#include <SPI.h>
#include <MFRC522.h>
#include <HX711.h>

// Définitions des pins pour le module RFID RC522
#define RST_PIN 9
#define SS_PIN 10

// Définitions des pins pour les LEDs et le buzzer
#define LED_YELLOW_PIN 2  // LED pour ajout de produit
#define LED_RED_PIN 7     // LED pour retrait de produit
#define BUZZER_PIN 8      // Buzzer pour feedback sonore

// Définitions des pins pour le module HX711 (capteur de poids)
#define HX711_DOUT_PIN A4
#define HX711_SCK_PIN A5

// Facteur de calibration pour le capteur de poids (à ajuster selon votre capteur)
#define CALIBRATION_FACTOR 420.0

// Initialisation des objets
MFRC522 rfid(SS_PIN, RST_PIN);
HX711 scale;

// Variables globales
float current_weight = 0.0;
float previous_weight = 0.0;
float weight_threshold = 5.0;  // Seuil en grammes pour détecter un changement
String last_rfid_tag = "";
unsigned long last_weight_check = 0;
const long weight_check_interval = 500;  // Vérifier le poids toutes les 500ms

void setup() {
  // Initialisation de la communication série
  Serial.begin(9600);
  
  // Initialisation des pins de sortie
  pinMode(LED_YELLOW_PIN, OUTPUT);
  pinMode(LED_RED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  // Éteindre toutes les LEDs
  digitalWrite(LED_YELLOW_PIN, LOW);
  digitalWrite(LED_RED_PIN, LOW);
  
  // Initialisation du module RFID
  SPI.begin();
  rfid.PCD_Init();
  
  // Initialisation du capteur de poids HX711
  scale.begin(HX711_DOUT_PIN, HX711_SCK_PIN);
  scale.set_scale(CALIBRATION_FACTOR);  // Appliquer la calibration
  scale.tare();  // Réinitialiser la balance à zéro
  
  // Tonalité de démarrage
  beep(100);
  delay(100);
  beep(200);
  
  Serial.println("Smart Cart System initialized");
  Serial.println("Ready to scan RFID tags and weigh products");
}

void loop() {
  // Vérification du poids à intervalles réguliers
  unsigned long current_millis = millis();
  if (current_millis - last_weight_check >= weight_check_interval) {
    last_weight_check = current_millis;
    
    // Lire le poids actuel
    current_weight = scale.get_units(5);  // Moyenne de 5 lectures pour plus de stabilité
    
    // Détecter un changement significatif de poids
    if (abs(current_weight - previous_weight) > weight_threshold) {
      Serial.print("Weight change detected: ");
      Serial.print(current_weight);
      Serial.println(" g");
      
      // Déterminer si un produit a été ajouté ou retiré
      if (current_weight > previous_weight) {
        // Produit ajouté
        Serial.println("Product added based on weight");
        productAdded();
      } else {
        // Produit retiré
        Serial.println("Product removed based on weight");
        productRemoved();
      }
      
      // Mettre à jour le poids précédent
      previous_weight = current_weight;
    }
  }
  
  // Vérifier si une carte RFID est présente
  if (rfid.PICC_IsNewCardPresent() && rfid.PICC_ReadCardSerial()) {
    // Lire l'ID de la carte RFID
    String tag_id = readRFID();
    
    // Vérifier si c'est une nouvelle carte
    if (tag_id != last_rfid_tag) {
      Serial.print("RFID tag detected: ");
      Serial.println(tag_id);
      
      // Simuler la détection d'ajout/retrait basée sur le premier caractère de l'ID
      // (Dans un système réel, vous utiliseriez une recherche dans une base de données)
      char first_char = tag_id.charAt(0);
      if (first_char >= '0' && first_char <= '7') {
        // Produit ajouté pour les IDs commençant par 0-7
        Serial.println("Product added based on RFID");
        productAdded();
      } else {
        // Produit retiré pour les autres IDs
        Serial.println("Product removed based on RFID");
        productRemoved();
      }
      
      // Mettre à jour la dernière carte lue
      last_rfid_tag = tag_id;
    }
    
    // Terminer la lecture RFID
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
  }
  
  // Traiter les commandes série
  if (Serial.available() > 0) {
    char command = Serial.read();
    
    switch(command) {
      case 'A':  // Allumer LED jaune (produit ajouté)
        productAdded();
        Serial.println("Command received: Product added");
        break;
        
      case 'R':  // Allumer LED rouge (produit retiré)
        productRemoved();
        Serial.println("Command received: Product removed");
        break;
        
      case 'O':  // Éteindre toutes les LEDs
        turnOffLEDs();
        Serial.println("Command received: All LEDs off");
        break;
        
      case 'T':  // Tare (réinitialiser) le capteur de poids
        scale.tare();
        previous_weight = 0.0;
        Serial.println("Scale tared to zero");
        break;
        
      case 'W':  // Demander le poids actuel
        Serial.print("Current weight: ");
        Serial.print(scale.get_units(10));
        Serial.println(" g");
        break;
    }
  }
}

// Fonction pour lire l'ID de la carte RFID
String readRFID() {
  String tag_id = "";
  
  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) tag_id += "0";
    tag_id += String(rfid.uid.uidByte[i], HEX);
  }
  
  tag_id.toUpperCase();
  return tag_id;
}

// Fonction pour signaler l'ajout d'un produit
void productAdded() {
  // Allumer la LED jaune
  digitalWrite(LED_YELLOW_PIN, HIGH);
  digitalWrite(LED_RED_PIN, LOW);
  
  // Son de confirmation - deux bips courts
  beep(100);
  delay(100);
  beep(100);
  
  // Garder la LED allumée pendant 3 secondes
  delay(3000);
  
  // Éteindre les LEDs
  turnOffLEDs();
}

// Fonction pour signaler le retrait d'un produit
void productRemoved() {
  // Allumer la LED rouge
  digitalWrite(LED_YELLOW_PIN, LOW);
  digitalWrite(LED_RED_PIN, HIGH);
  
  // Son d'alerte - un bip long
  beep(300);
  
  // Garder la LED allumée pendant 3 secondes
  delay(3000);
  
  // Éteindre les LEDs
  turnOffLEDs();
}

// Fonction pour éteindre toutes les LEDs
void turnOffLEDs() {
  digitalWrite(LED_YELLOW_PIN, LOW);
  digitalWrite(LED_RED_PIN, LOW);
}

// Fonction pour émettre un bip avec le buzzer
void beep(int duration) {
  tone(BUZZER_PIN, 2000);  // 2kHz
  delay(duration);
  noTone(BUZZER_PIN);
}