"""
Script de gestion du capteur de poids HX711, d'un buzzer et de LEDs avec Arduino/Raspberry Pi (GPIO).
Ce script lit les valeurs du capteur HX711, active le buzzer en cas de surcharge et pilote des LEDs d'état.

Dépendances:
- bibliothèque HX711 pour Raspberry Pi: https://github.com/tatobari/hx711py
- RPi.GPIO pour Raspberry Pi

Connexion typique:
- HX711 DT -> GPIO5
- HX711 SCK -> GPIO6
- Buzzer   -> GPIO17
- LED verte -> GPIO22
- LED rouge -> GPIO27

"""

import time
import RPi.GPIO as GPIO
from hx711 import HX711

# Définition des broches GPIO
HX711_DT = 5
HX711_SCK = 6
BUZZER_PIN = 17
LED_GREEN = 22
LED_RED = 27

# Seuil de surcharge en grammes (à ajuster selon votre besoin)
OVERLOAD_THRESHOLD = 5000  # ex: 5000g = 5kg

def setup():
    GPIO.setmode(GPIO.BCM)
    GPIO.setup(BUZZER_PIN, GPIO.OUT)
    GPIO.setup(LED_GREEN, GPIO.OUT)
    GPIO.setup(LED_RED, GPIO.OUT)
    GPIO.output(BUZZER_PIN, GPIO.LOW)
    GPIO.output(LED_GREEN, GPIO.LOW)
    GPIO.output(LED_RED, GPIO.LOW)

def buzzer_beep(times=1, duration=0.1):
    for _ in range(times):
        GPIO.output(BUZZER_PIN, GPIO.HIGH)
        time.sleep(duration)
        GPIO.output(BUZZER_PIN, GPIO.LOW)
        time.sleep(0.1)

def main():
    setup()
    hx = HX711(dout_pin=HX711_DT, pd_sck_pin=HX711_SCK)
    hx.set_reading_format("MSB", "MSB")
    hx.set_reference_unit(1)  # À ajuster avec la calibration
    hx.reset()
    hx.tare()
    print("Placez un objet sur la balance...")

    try:
        while True:
            val = max(0, int(hx.get_weight(5)))  # valeur en grammes
            print(f"Poids mesuré: {val} g")

            if val < 100:
                # Poids faible, LED verte
                GPIO.output(LED_GREEN, GPIO.HIGH)
                GPIO.output(LED_RED, GPIO.LOW)
            elif val < OVERLOAD_THRESHOLD:
                # Poids normal, LED verte
                GPIO.output(LED_GREEN, GPIO.HIGH)
                GPIO.output(LED_RED, GPIO.LOW)
            else:
                # Surcharge, LED rouge + buzzer
                GPIO.output(LED_GREEN, GPIO.LOW)
                GPIO.output(LED_RED, GPIO.HIGH)
                buzzer_beep(times=2, duration=0.2)

            time.sleep(0.5)
            hx.power_down()
            hx.power_up()
            time.sleep(0.1)

    except KeyboardInterrupt:
        print("Arrêt du script.")
    finally:
        GPIO.cleanup()

if __name__ == "__main__":
    main()