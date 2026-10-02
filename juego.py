import os
import random
import time

GREEN = "\033[32m"
END = "\033[0m"


def limpiar_pantalla():
    os.system("cls" if os.name == "nt" else "clear")


def buses(n1, n2):
    # La pista ahora mide 120 caracteres de ancho total
    espacio_fin_1 = max(0, 150 - n1)
    espacio_fin_2 = max(0, 150 - n2)

    output = []
    output.append(170 * "-")  # Línea superior más larga
    output.append((n1 * " ") + " _______________" + (espacio_fin_1 * " ") + "|")
    output.append((n1 * " ") + "|__|__|__|__|__|___ " + (espacio_fin_1 * " ") + "|")
    output.append((n1 * " ") + "|     AUTOBUS1      |)" + (espacio_fin_1 * " ") + "|")
    output.append((n1 * " ") + "|___-()-------()-___|" + (espacio_fin_1 * " ") + "|")
    output.append(170 * "-")  # Línea divisoria
    output.append((n2 * " ") + " _______________" + (espacio_fin_2 * " ") + "|")
    output.append((n2 * " ") + "|__|__|__|__|__|___ " + (espacio_fin_2 * " ") + "|")
    output.append((n2 * " ") + "|     AUTOBUS2      |)" + (espacio_fin_2 * " ") + "|")
    output.append((n2 * " ") + "|___-()-------()-___|" + (espacio_fin_2 * " ") + "|")
    output.append(170 * "-")  # Línea inferior más larga
    return "\n".join(output)


def iniciar_carrera():
    pos_hugo = 0
    pos_abelino = 0
    meta = 140  # <--- META MÁS LARGA (Antes 60)

    while pos_hugo < meta and pos_abelino < meta:
        limpiar_pantalla()
        print(GREEN + buses(pos_hugo, pos_abelino) + END)

        # Avance paso a paso
        pos_hugo += random.randint(1, 3)
        pos_abelino += random.randint(1, 3)

        time.sleep(0.3)  # Velocidad pausada

    # Pantalla final con los autobuses en la meta
    limpiar_pantalla()
    print(GREEN + buses(pos_hugo, pos_abelino) + END)

    # Resultado
    if pos_hugo > pos_abelino:
        print("\n🏆 ¡GANÓ AUTOBUS1!")
    elif pos_abelino > pos_hugo:
        print("\n🏆 ¡GANÓ AUTOBUS2!")
    else:
        print("\n🤝 ¡EMPATE ABSOLUTO!")


if __name__ == "__main__":
    iniciar_carrera()