# -*- coding: utf-8 -*-
"""
Genera las ilustraciones provisionales del diagnostico guiado.

Son DIBUJOS esquematicos, no fotos: sirven para comprobar que las imagenes
se muestran en cada paso mientras soporte toma las fotos reales. Cada una
lleva arriba una franja que dice que es provisional, para que nadie la
confunda con una foto del aula.

PENSADAS PARA EL CELULAR

El docente las ve en un celular, a unos 330 px de ancho: el dibujo se reduce
a menos de la mitad. Por eso ningun texto baja de 26 unidades (unos 11 px en
pantalla), los rotulos son de pocas palabras y no llevan pie: el paso ya
explica debajo lo que hay que hacer.

Uso (desde la raiz del proyecto):
    python database/imagenes-provisionales/generar.py

Escribe un .svg por clave de componente en esta misma carpeta. Los .svg se
versionan en Git: el servidor no necesita Python para cargarlos.
"""
import io
import math
import os

ANCHO, ALTO = 800, 480
AQUI = os.path.dirname(os.path.abspath(__file__))

TINTA = '#334155'
GRIS = '#64748b'
CLARO = '#e2e8f0'
FONDO = '#f8fafc'
ROJO = '#dc2626'
VERDE = '#16a34a'
NARANJA = '#ea580c'
AZUL = '#2563eb'
FUENTE = "font-family='system-ui, -apple-system, Segoe UI, Roboto, sans-serif'"

T = 30   # tamano normal de rotulo
TG = 34  # rotulo destacado


def texto(x, y, t, tam=T, color=TINTA, anchor='middle', peso='bold'):
    t = t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
    return (f"<text x='{x}' y='{y}' {FUENTE} font-size='{tam}' fill='{color}' "
            f"text-anchor='{anchor}' font-weight='{peso}'>{t}</text>")


def rect(x, y, w, h, fill='#ffffff', stroke=TINTA, sw=3, r=8):
    return f"<rect x='{x}' y='{y}' width='{w}' height='{h}' rx='{r}' fill='{fill}' stroke='{stroke}' stroke-width='{sw}'/>"


def circulo(x, y, r, fill='none', stroke=TINTA, sw=3):
    return f"<circle cx='{x}' cy='{y}' r='{r}' fill='{fill}' stroke='{stroke}' stroke-width='{sw}'/>"


def linea(x1, y1, x2, y2, stroke=TINTA, sw=3, dash=None):
    d = f" stroke-dasharray='{dash}'" if dash else ''
    return f"<line x1='{x1}' y1='{y1}' x2='{x2}' y2='{y2}' stroke='{stroke}' stroke-width='{sw}' stroke-linecap='round'{d}/>"


def resalte(x, y, r=26):
    """Circulo rojo que marca donde mirar."""
    return circulo(x, y, r, stroke=ROJO, sw=6)


def flecha(x1, y1, x2, y2, color=ROJO):
    """Flecha dibujada con lineas: sin <marker>, que choca de ids al incrustar."""
    ang = math.atan2(y2 - y1, x2 - x1)
    a1 = ang + math.radians(150)
    a2 = ang - math.radians(150)
    p1 = (x2 + 22 * math.cos(a1), y2 + 22 * math.sin(a1))
    p2 = (x2 + 22 * math.cos(a2), y2 + 22 * math.sin(a2))
    return (linea(x1, y1, x2, y2, color, 6)
            + f"<polygon points='{x2},{y2} {p1[0]:.0f},{p1[1]:.0f} {p2[0]:.0f},{p2[1]:.0f}' fill='{color}'/>")


def simbolo_encendido(x, y, r=12, color=TINTA):
    return (f"<path d='M {x - r * 0.7:.0f} {y - r * 0.7:.0f} A {r} {r} 0 1 0 {x + r * 0.7:.0f} {y - r * 0.7:.0f}' "
            f"fill='none' stroke='{color}' stroke-width='4' stroke-linecap='round'/>"
            + linea(x, y - r - 2, x, y - 2, color, 4))


def lienzo(cuerpo):
    franja = (rect(20, 14, ANCHO - 40, 56, fill='#fef3c7', stroke='#d97706', sw=3, r=12)
              + texto(ANCHO / 2, 53, 'ILUSTRACIÓN PROVISIONAL', 30, '#92400e'))
    return (f"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {ANCHO} {ALTO}' role='img' "
            f"style='width:100%;height:auto;display:block'>"
            f"<rect x='0' y='0' width='{ANCHO}' height='{ALTO}' fill='{FONDO}'/>"
            + franja + cuerpo + '</svg>')


def aio(x, y, w=260, h=170):
    """Computadora todo en uno: pantalla con pie."""
    return (rect(x, y, w, h, fill='#1e293b', stroke=TINTA, sw=4, r=10)
            + rect(x + 10, y + 10, w - 20, h - 34, fill='#334155', stroke='#475569', sw=2, r=4)
            + f"<path d='M {x + w / 2 - 30} {y + h} L {x + w / 2 - 45} {y + h + 45} L {x + w / 2 + 45} {y + h + 45} L {x + w / 2 + 30} {y + h} Z' fill='#cbd5e1' stroke='{TINTA}' stroke-width='3'/>")


def monitor(x, y, w=200, h=130):
    return (rect(x, y, w, h, fill='#1e293b', stroke=TINTA, sw=4, r=8)
            + rect(x + 8, y + 8, w - 16, h - 16, fill='#334155', stroke='#475569', sw=2, r=4)
            + rect(x + w / 2 - 10, y + h, 20, 30, fill='#cbd5e1', stroke=TINTA, sw=3, r=2)
            + rect(x + w / 2 - 45, y + h + 30, 90, 10, fill='#cbd5e1', stroke=TINTA, sw=3, r=3))


def torre(x, y, w=80, h=170):
    return (rect(x, y, w, h, fill='#e2e8f0', stroke=TINTA, sw=4, r=6)
            + rect(x + 12, y + 18, w - 24, 10, fill=GRIS, stroke='none', sw=0, r=2)
            + rect(x + 12, y + 36, w - 24, 10, fill=GRIS, stroke='none', sw=0, r=2)
            + circulo(x + w / 2, y + h - 40, 10))


def proyector(x, y, w=240, h=90, techo=True):
    s = ''
    if techo:
        s += linea(x + w / 2, 78, x + w / 2, y, TINTA, 6) + rect(x + w / 2 - 40, 78, 80, 10, fill='#94a3b8', stroke=TINTA, sw=2, r=2)
    return (s + rect(x, y, w, h, fill='#f1f5f9', stroke=TINTA, sw=4, r=14)
            + circulo(x + 50, y + h / 2, 28, fill='#cbd5e1')
            + circulo(x + 50, y + h / 2, 14, fill='#64748b'))


def control(x, y, w=110, h=250, resaltar=False):
    s = rect(x, y, w, h, fill='#1f2937', stroke=TINTA, sw=4, r=26)
    s += circulo(x + w / 2, y + 34, 14, fill=ROJO, stroke='#7f1d1d', sw=2)
    for fila in range(4):
        for col in range(3):
            s += circulo(x + 25 + col * 30, y + 80 + fila * 34, 10, fill='#4b5563', stroke='#111827', sw=2)
    if resaltar:
        s += resalte(x + w / 2, y + 34, 28)
    return s


def puerto_usb(x, y):
    return rect(x, y, 56, 22, fill='#ffffff', stroke=TINTA, sw=3, r=3) + rect(x + 10, y + 5, 36, 7, fill=TINTA, stroke='none', sw=0, r=1)


def puerto_hdmi(x, y, etiqueta=None):
    s = f"<path d='M {x} {y} L {x + 90} {y} L {x + 80} {y + 28} L {x + 10} {y + 28} Z' fill='#ffffff' stroke='{TINTA}' stroke-width='4'/>"
    if etiqueta:
        s += texto(x + 45, y + 90, etiqueta, T)
    return s


def barra_tareas(y):
    return (rect(40, y, 720, 64, fill='#e2e8f0', stroke='#94a3b8', sw=2, r=6)
            + rect(60, y + 16, 32, 32, fill=AZUL, stroke='none', sw=0, r=4)
            + texto(690, y + 42, '10:30', 26, TINTA, peso='normal'))


def parlante_icono(x, y, tachado=False, escala=1.4):
    e = escala
    s = (f"<path d='M {x} {y - 8 * e} L {x + 10 * e} {y - 8 * e} L {x + 22 * e} {y - 18 * e} L {x + 22 * e} {y + 18 * e} L {x + 10 * e} {y + 8 * e} L {x} {y + 8 * e} Z' "
         f"fill='{TINTA}'/>")
    if tachado:
        s += linea(x + 28 * e, y - 10 * e, x + 44 * e, y + 10 * e, ROJO, 5) + linea(x + 44 * e, y - 10 * e, x + 28 * e, y + 10 * e, ROJO, 5)
    else:
        s += f"<path d='M {x + 30 * e} {y - 10 * e} Q {x + 40 * e} {y} {x + 30 * e} {y + 10 * e}' fill='none' stroke='{TINTA}' stroke-width='4'/>"
    return s


def tecla(x, y, t, w=110, marcada=False, h=60):
    s = rect(x, y, w, h, fill='#ffffff', stroke=ROJO if marcada else TINTA, sw=6 if marcada else 3, r=8)
    return s + texto(x + w / 2, y + h / 2 + 10, t, 26 if len(t) > 4 else 30, ROJO if marcada else TINTA)


# ------------------------------------------------------------------ dibujos
D = {}

D['computer'] = lienzo(
    aio(40, 110, 220, 150) + texto(150, 360, 'Todo en uno')
    + monitor(300, 110, 170, 115) + torre(490, 105, 70, 170) + texto(430, 360, 'De torre')
    + monitor(600, 110, 150, 105) + rect(620, 260, 110, 26, fill='#e2e8f0', stroke=TINTA, sw=3, r=6)
    + texto(675, 360, 'NUC')
    + texto(400, 440, '¿Cuál es la tuya?', TG, AZUL))

D['computer_power_button'] = lienzo(
    aio(60, 110, 340, 210)
    + circulo(378, 290, 10, fill='#e2e8f0') + resalte(378, 290, 30)
    + rect(500, 120, 250, 250, fill='#ffffff', stroke='#94a3b8', sw=2, r=16)
    + circulo(625, 230, 60, fill='#e2e8f0') + simbolo_encendido(625, 240, 28)
    + texto(625, 340, 'Este símbolo', T)
    + flecha(412, 290, 495, 250)
    + texto(230, 440, 'Abajo o al costado', T, ROJO))

D['computer_power_cable'] = lienzo(
    rect(90, 100, 380, 240, fill='#e2e8f0', stroke=TINTA, sw=4, r=12) + texto(280, 160, 'Parte de atrás', T, GRIS, peso='normal')
    + rect(380, 260, 50, 34, fill='#ffffff', stroke=TINTA, sw=3, r=4)
    + f"<path d='M 405 294 C 405 350, 600 340, 650 430' fill='none' stroke='{TINTA}' stroke-width='10'/>"
    + resalte(405, 277, 38) + flecha(620, 200, 445, 268)
    + texto(640, 175, 'Empújalo', TG, ROJO))

D['power_strip'] = lienzo(
    rect(60, 180, 560, 110, fill='#f1f5f9', stroke=TINTA, sw=4, r=18)
    + ''.join(circulo(140 + i * 100, 235, 28, fill='#ffffff') + circulo(131 + i * 100, 235, 4, fill=TINTA) + circulo(149 + i * 100, 235, 4, fill=TINTA) for i in range(4))
    + rect(540, 205, 60, 60, fill=NARANJA, stroke='#9a3412', sw=3, r=8)
    + resalte(570, 235, 46) + linea(60, 235, 30, 330, TINTA, 10)
    + texto(640, 360, 'Interruptor', TG, ROJO) + flecha(650, 320, 600, 275)
    + texto(330, 430, 'Prendido: se ilumina', T, NARANJA))

D['projector'] = lienzo(
    proyector(230, 150, 340, 120)
    + circulo(520, 175, 12, fill='#e2e8f0') + resalte(520, 175, 30)
    + texto(400, 330, 'Botón del proyector', TG, ROJO)
    + texto(400, 410, 'No te subas a nada', T, NARANJA))

D['projector_leds'] = lienzo(
    proyector(40, 140, 260, 100)
    + circulo(240, 168, 10, fill=ROJO, stroke='none') + circulo(268, 168, 10, fill=VERDE, stroke='none') + resalte(254, 168, 34)
    + circulo(380, 140, 18, fill=ROJO, stroke='none') + texto(412, 151, 'Roja: apagado', T, anchor='start')
    + circulo(380, 215, 18, fill=VERDE, stroke='none') + texto(412, 226, 'Verde: prendido', T, anchor='start')
    + circulo(380, 290, 18, fill='#fbbf24', stroke=NARANJA, sw=3) + texto(412, 301, 'Parpadea: espera', T, anchor='start')
    + circulo(380, 365, 18, fill='#ffffff', stroke='#94a3b8', sw=3) + texto(412, 376, 'Sin luz: sin corriente', T, anchor='start'))

D['projector_remote'] = lienzo(
    proyector(460, 140, 280, 100)
    + control(90, 150, 110, 250, resaltar=True)
    + linea(215, 190, 490, 190, ROJO, 5, '14 12')
    + texto(350, 300, 'Apunta al frente', TG, ROJO)
    + texto(350, 350, 'sin nada en medio', T, TINTA))

D['remote_batteries'] = lienzo(
    rect(90, 90, 180, 340, fill='#1f2937', stroke=TINTA, sw=4, r=34)
    + rect(115, 180, 130, 230, fill='#e5e7eb', stroke=TINTA, sw=3, r=12)
    + rect(130, 196, 44, 196, fill='#fbbf24', stroke='#92400e', sw=3, r=10) + texto(152, 230, '+', 36, '#7c2d12') + texto(152, 380, '−', 40, '#7c2d12')
    + rect(186, 196, 44, 196, fill='#fbbf24', stroke='#92400e', sw=3, r=10) + texto(208, 230, '−', 40, '#7c2d12') + texto(208, 380, '+', 36, '#7c2d12')
    + texto(540, 180, 'Respeta', TG) + texto(540, 222, 'el + y el −', TG)
    + rect(350, 270, 380, 150, fill='#fef2f2', stroke=ROJO, sw=3, r=14)
    + texto(540, 320, 'Polvo blanco o', T, ROJO) + texto(540, 360, 'pila hinchada:', T, ROJO) + texto(540, 400, 'no las toques', T, ROJO))

D['remote_buttons'] = lienzo(
    rect(190, 90, 420, 380, fill='#1f2937', stroke=TINTA, sw=4, r=40)
    + circulo(400, 130, 18, fill=ROJO, stroke='#7f1d1d', sw=2)
    + ''.join(rect(220, 165 + i * 74, 360, 58, fill='#4b5563', stroke=ROJO, sw=5, r=12)
              + texto(400, 205 + i * 74, t, T, '#ffffff')
              for i, t in enumerate(['SOURCE / INPUT', 'AUTO', 'BLANK / AV MUTE', 'FOCUS'])))

D['hdmi_port'] = lienzo(
    rect(60, 100, 680, 240, fill='#e2e8f0', stroke=TINTA, sw=4, r=12) + texto(400, 150, 'Costado de la computadora', 28, GRIS, peso='normal')
    + puerto_hdmi(150, 190, 'HDMI IN') + puerto_hdmi(380, 190, 'HDMI OUT') + resalte(425, 204, 48)
    + puerto_usb(600, 205)
    + texto(400, 420, 'El proyector va en OUT', TG, ROJO))

D['projector_no_signal'] = lienzo(
    rect(80, 100, 640, 340, fill='#0f172a', stroke=TINTA, sw=4, r=6)
    + rect(220, 200, 360, 130, fill='#1e3a8a', stroke='#93c5fd', sw=3, r=6)
    + texto(400, 260, 'Sin señal', 44, '#ffffff') + texto(400, 305, 'No signal', 32, '#bfdbfe', peso='normal'))

D['screen_switch'] = lienzo(
    rect(120, 90, 240, 370, fill='#ffffff', stroke=TINTA, sw=4, r=16)
    + rect(160, 120, 160, 80, fill='#e2e8f0', stroke=TINTA, sw=3, r=10) + texto(240, 172, '▲ Subir', T)
    + rect(160, 225, 160, 80, fill='#e2e8f0', stroke=TINTA, sw=3, r=10) + texto(240, 277, '■ Stop', T)
    + rect(160, 330, 160, 80, fill='#fee2e2', stroke=ROJO, sw=6, r=10) + texto(240, 382, '▼ Bajar', T, ROJO)
    + texto(580, 330, 'Mantenlo', TG, ROJO) + texto(580, 375, 'presionado', TG, ROJO)
    + flecha(430, 370, 335, 370))

D['screen'] = lienzo(
    rect(120, 86, 560, 28, fill='#cbd5e1', stroke=TINTA, sw=3, r=12)
    + rect(140, 114, 520, 230, fill='#ffffff', stroke=TINTA, sw=3, r=2)
    + rect(140, 338, 520, 20, fill='#64748b', stroke=TINTA, sw=2, r=4)
    + resalte(400, 348, 36) + texto(400, 430, 'Del centro del borde', TG, ROJO)
    + flecha(730, 130, 730, 320, AZUL))

D['tower_pc'] = lienzo(
    monitor(80, 110, 300, 190) + circulo(352, 288, 9, fill='#e2e8f0') + resalte(352, 288, 28)
    + torre(540, 110, 110, 230)
    + f"<path d='M 230 350 C 270 420, 560 420, 595 340' fill='none' stroke='{AZUL}' stroke-width='8'/>"
    + texto(400, 455, 'Cable de video', T, AZUL)
    + texto(230, 70 + 0, '', 10))

D['usb_ports'] = lienzo(
    rect(80, 100, 640, 240, fill='#e2e8f0', stroke=TINTA, sw=4, r=12) + texto(400, 150, 'Costado de la computadora', 28, GRIS, peso='normal')
    + puerto_usb(180, 210) + puerto_usb(280, 210) + puerto_usb(380, 210) + resalte(208, 221, 48)
    + rect(500, 200, 150, 40, fill='#ffffff', stroke=TINTA, sw=3, r=6)
    + texto(400, 420, 'Las entradas USB', TG, ROJO))

D['mouse'] = lienzo(
    f"<path d='M 120 140 C 120 95, 240 95, 240 140 L 240 320 C 240 385, 120 385, 120 320 Z' fill='#ffffff' stroke='{TINTA}' stroke-width='4'/>"
    + linea(180, 105, 180, 200, TINTA, 3) + texto(180, 435, 'Arriba', T, GRIS)
    + f"<path d='M 330 140 C 330 95, 450 95, 450 140 L 450 320 C 450 385, 330 385, 330 320 Z' fill='#e2e8f0' stroke='{TINTA}' stroke-width='4'/>"
    + rect(360, 270, 60, 30, fill='#ffffff', stroke=ROJO, sw=5, r=4) + texto(390, 255, 'ON', 28, ROJO)
    + texto(390, 435, 'Abajo', T, GRIS)
    + rect(540, 230, 90, 36, fill='#1f2937', stroke=TINTA, sw=3, r=6) + rect(630, 238, 40, 20, fill='#94a3b8', stroke=TINTA, sw=2, r=2)
    + texto(610, 320, 'Receptor', T) + texto(610, 358, 'USB', T))

D['keyboard'] = lienzo(
    rect(30, 100, 740, 340, fill='#ffffff', stroke=TINTA, sw=4, r=16)
    + tecla(60, 130, 'Bloq Mayús', 190, True) + tecla(550, 130, 'Bloq Num', 190, True)
    + tecla(60, 350, 'Ctrl', 110) + tecla(185, 350, '⊞', 90, True) + tecla(290, 350, 'Alt', 90)
    + rect(395, 350, 250, 60, fill='#ffffff', stroke=TINTA, sw=3, r=8) + texto(520, 390, 'Espacio', 26, TINTA)
    + tecla(660, 350, 'Supr', 90)
    + texto(400, 270, 'Las marcadas en rojo', TG, ROJO))

D['keyboard_language'] = lienzo(
    tecla(150, 120, '⊞', 110, True) + texto(305, 162, '+', 44) + rect(350, 120, 300, 60, fill='#ffffff', stroke=ROJO, sw=6, r=8) + texto(500, 160, 'Espacio', T, ROJO)
    + barra_tareas(260) + rect(540, 274, 90, 38, fill='#ffffff', stroke=ROJO, sw=5, r=4) + texto(585, 302, 'ESP', 28, ROJO)
    + resalte(585, 293, 56)
    + texto(400, 420, 'Tiene que decir ESP', TG, ROJO))

D['task_manager'] = lienzo(
    rect(100, 90, 600, 370, fill='#1e3a8a', stroke=TINTA, sw=3, r=6)
    + texto(400, 160, 'Bloquear', T, '#ffffff', peso='normal') + texto(400, 215, 'Cambiar de usuario', T, '#ffffff', peso='normal')
    + texto(400, 270, 'Cerrar sesión', T, '#ffffff', peso='normal')
    + rect(160, 300, 480, 66, fill='#1d4ed8', stroke=ROJO, sw=6, r=8) + texto(400, 344, 'Administrador de tareas', T, '#ffffff')
    + texto(400, 425, 'Ctrl + Alt + Supr', 28, '#bfdbfe'))

D['win_p'] = lienzo(
    rect(360, 85, 410, 380, fill='#1f2937', stroke=TINTA, sw=3, r=10)
    + texto(565, 128, 'Proyectar', T, '#ffffff')
    + ''.join(rect(380, 150 + i * 76, 370, 62, fill='#374151' if i != 1 else '#1d4ed8', stroke=ROJO if i == 1 else '#4b5563', sw=6 if i == 1 else 2, r=8)
              + texto(565, 190 + i * 76, t, 28, '#ffffff', peso='bold' if i == 1 else 'normal')
              for i, t in enumerate(['Solo pantalla de PC', 'Duplicar', 'Extender', 'Solo 2.ª pantalla']))
    + tecla(40, 220, '⊞', 110, True) + texto(185, 262, '+', 44) + tecla(220, 220, 'P', 110, True))

D['volume_icon'] = lienzo(
    rect(40, 90, 720, 240, fill='#334155', stroke=TINTA, sw=3, r=6) + texto(400, 220, 'Pantalla', T, '#cbd5e1', peso='normal')
    + barra_tareas(340) + parlante_icono(560, 372, tachado=True) + resalte(595, 372, 50)
    + texto(220, 440, 'Abajo a la derecha', T, ROJO))

D['audio_output'] = lienzo(
    rect(370, 85, 400, 300, fill='#ffffff', stroke=TINTA, sw=3, r=12)
    + parlante_icono(400, 130) + rect(470, 122, 270, 14, fill=CLARO, stroke='none', sw=0, r=7) + rect(470, 122, 160, 14, fill=AZUL, stroke='none', sw=0, r=7)
    + rect(390, 175, 360, 76, fill='#dbeafe', stroke=ROJO, sw=6, r=10) + texto(570, 223, 'Parlantes', T)
    + rect(390, 268, 360, 76, fill='#f1f5f9', stroke='#94a3b8', sw=2, r=10) + texto(570, 316, 'Proyector (HDMI)', 28, GRIS, peso='normal')
    + texto(180, 200, 'Elige los', TG, ROJO) + texto(180, 245, 'parlantes', TG, ROJO)
    + barra_tareas(400))

D['speaker'] = lienzo(
    rect(170, 85, 220, 370, fill='#1f2937', stroke=TINTA, sw=4, r=18)
    + circulo(280, 190, 70, fill='#374151', stroke='#111827') + circulo(280, 190, 28, fill='#4b5563', stroke='#111827')
    + circulo(280, 360, 36, fill='#9ca3af', stroke='#111827') + linea(280, 360, 280, 330, '#111827', 5)
    + resalte(280, 360, 54) + circulo(355, 430, 8, fill=VERDE, stroke='none')
    + texto(590, 330, 'Gírala hacia', TG, ROJO) + texto(590, 375, 'la derecha', TG, ROJO)
    + flecha(470, 360, 345, 360))

D['audio_cable'] = lienzo(
    rect(60, 100, 680, 230, fill='#e2e8f0', stroke=TINTA, sw=4, r=12) + texto(400, 150, 'Entradas de audio', 28, GRIS, peso='normal')
    + circulo(280, 225, 24, fill='#ffffff', stroke='#ec4899', sw=8) + texto(280, 300, 'Rosado', T, '#ec4899', peso='normal')
    + circulo(500, 225, 24, fill='#ffffff', stroke=VERDE, sw=8) + texto(500, 300, 'Verde', T, VERDE)
    + resalte(500, 225, 44)
    + rect(560, 395, 160, 22, fill=VERDE, stroke=TINTA, sw=2, r=8) + linea(530, 406, 560, 406, TINTA, 8)
    + texto(640, 450, 'Punta verde', T, VERDE)
    + flecha(650, 385, 530, 255))


def main():
    for clave, svg in D.items():
        with io.open(os.path.join(AQUI, f'{clave}.svg'), 'w', encoding='utf-8', newline='\n') as f:
            f.write(svg + '\n')
    print(f'{len(D)} ilustraciones generadas en {AQUI}')


if __name__ == '__main__':
    main()
