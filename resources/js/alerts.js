/*
 * Aviso de incidencias nuevas en el panel de soporte.
 *
 * EL PROBLEMA: un técnico no mira la bandeja todo el día. Sin aviso, una
 * incidencia urgente espera a que alguien recargue la página — y al otro
 * lado hay un docente de pie frente a su clase.
 *
 * TRES AVISOS A LA VEZ, y cada uno cubre un fallo del anterior:
 *
 *   1. Un contador en la pantalla. Es el único que siempre funciona.
 *   2. Un sonido. Sirve cuando el técnico está mirando otra cosa, pero el
 *      navegador lo bloquea hasta que alguien haya hecho clic en la página.
 *   3. Una notificación del sistema. Sirve con la ventana minimizada, pero
 *      hay que pedir permiso y el técnico puede negarlo.
 *
 * Ninguno es fiable por sí solo. Los tres juntos, sí.
 */

const INTERVALO_MS = 20_000;
const CLAVE_SONIDO = 'alertas_sonido';

let ultimaConsulta = null;
let vistas = new Set();
let temporizador = null;

function contenedor() {
    return document.querySelector('[data-alertas]');
}

/**
 * Un pitido corto generado en el navegador.
 *
 * Se sintetiza en lugar de servir un archivo de audio a propósito: evita una
 * descarga más y, sobre todo, evita tener que abrir la política de seguridad
 * hacia un origen de medios.
 */
function pitar(urgente) {
    if (localStorage.getItem(CLAVE_SONIDO) === 'off') return;

    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const tonos = urgente ? [880, 1180, 880] : [660];

        tonos.forEach((hz, i) => {
            const osc = ctx.createOscillator();
            const vol = ctx.createGain();
            osc.connect(vol);
            vol.connect(ctx.destination);
            osc.frequency.value = hz;
            osc.type = 'sine';

            const t = ctx.currentTime + i * 0.18;
            // Rampa en lugar de corte seco: un corte suena a chasquido.
            vol.gain.setValueAtTime(0.0001, t);
            vol.gain.exponentialRampToValueAtTime(0.18, t + 0.02);
            vol.gain.exponentialRampToValueAtTime(0.0001, t + 0.16);
            osc.start(t);
            osc.stop(t + 0.18);
        });
    } catch {
        // El navegador bloquea el audio hasta que haya habido un clic. No es
        // un error: quedan el contador y la notificación.
    }
}

function notificar(incidencia) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    try {
        const aviso = new Notification(
            incidencia.riesgo ? '⚠ RIESGO en ' + incidencia.aula : 'Nueva incidencia · ' + incidencia.aula,
            {
                body: [incidencia.categoria, incidencia.bloquea_clase ? 'Clase detenida' : null]
                    .filter(Boolean).join(' · '),
                tag: 'incidencia-' + incidencia.id,
            },
        );

        aviso.onclick = () => {
            window.focus();
            window.location.assign(incidencia.url);
        };
    } catch {
        // Sin notificación quedan el contador y el sonido.
    }
}

function pintar(datos) {
    const caja = contenedor();
    if (!caja) return;

    const nuevas = datos.incidencias.filter((i) => !vistas.has(i.id));
    if (nuevas.length === 0) return;

    nuevas.forEach((i) => vistas.add(i.id));

    const urgente = nuevas.some((i) => i.riesgo || i.prioridad === 'Crítica');

    caja.hidden = false;
    caja.dataset.urgente = urgente ? '1' : '0';
    caja.querySelector('[data-alertas-texto]').textContent =
        nuevas.length === 1
            ? `Nueva incidencia en ${nuevas[0].aula}`
            : `${nuevas.length} incidencias nuevas`;

    const enlace = caja.querySelector('[data-alertas-enlace]');
    if (enlace) enlace.href = nuevas.length === 1 ? nuevas[0].url : enlace.dataset.bandeja;

    pitar(urgente);
    nuevas.forEach(notificar);
}

async function consultar() {
    try {
        const url = new URL(contenedor().dataset.alertas, window.location.origin);
        if (ultimaConsulta) url.searchParams.set('desde', ultimaConsulta);

        const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });

        // Si la sesión caducó, la petición redirige al login. Seguir
        // sondeando sería inútil y llenaría el registro del servidor.
        if (!respuesta.ok) {
            clearInterval(temporizador);
            return;
        }

        const datos = await respuesta.json();
        pintar(datos);

        // El reloj lo manda el servidor: si el del navegador va desfasado, el
        // panel avisaría una y otra vez de lo mismo.
        ultimaConsulta = datos.ahora;
    } catch {
        // Un fallo de red puntual no debe romper el sondeo: se reintenta en
        // el siguiente ciclo.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const caja = contenedor();
    if (!caja) return;

    // La primera consulta fija el punto de partida sin avisar de lo que ya
    // estaba: al entrar al panel no deben sonar diez pitidos de incidencias
    // de hace una hora.
    ultimaConsulta = caja.dataset.alertasDesde || new Date().toISOString();

    temporizador = setInterval(consultar, INTERVALO_MS);
    consultar();
});

document.addEventListener('click', (event) => {
    // Silenciar el sonido. Es lo primero que pide alguien que comparte
    // oficina, y sin la opción acaban silenciando la pestaña entera — y con
    // ella el aviso que sí importa.
    if (event.target.closest('[data-alertas-silenciar]')) {
        const apagado = localStorage.getItem(CLAVE_SONIDO) === 'off';
        localStorage.setItem(CLAVE_SONIDO, apagado ? 'on' : 'off');
        event.target.closest('[data-alertas-silenciar]').dataset.silenciado = apagado ? '0' : '1';
    }

    // El permiso de notificaciones se pide con un botón, nunca solo al
    // cargar: un navegador que pregunta sin que nadie lo haya pedido recibe
    // un «no» automático, y ese «no» es difícil de revertir después.
    if (event.target.closest('[data-alertas-permiso]')) {
        Notification.requestPermission().then(() => window.location.reload());
    }

    if (event.target.closest('[data-alertas-cerrar]')) {
        contenedor().hidden = true;
    }
});
