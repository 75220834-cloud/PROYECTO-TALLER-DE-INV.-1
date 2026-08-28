/*
 * Tema claro / oscuro.
 *
 * ORDEN DE DECISIÓN, y el motivo de cada paso:
 *
 *   1. Lo que el usuario eligió aquí antes. Manda sobre todo: si un docente
 *      forzó el claro porque le da el sol en la pantalla, el sistema no debe
 *      volver a ponérselo oscuro en la siguiente visita.
 *   2. La preferencia del sistema operativo, si nunca eligió.
 *
 * SE APLICA ANTES DE PINTAR. El script que llama a esto va en el <head> y
 * es síncrono a propósito: si se ejecutara al final, la página aparecería un
 * instante en claro antes de saltar a oscuro. Ese parpadeo blanco en un aula
 * a media luz es molesto de verdad, no un detalle.
 *
 * localStorage puede lanzar excepción —navegación privada, cookies
 * bloqueadas— así que todo va envuelto: quedarse sin tema guardado es
 * aceptable; quedarse con la página en blanco por una excepción no.
 */

const CLAVE = 'tema';

export function temaGuardado() {
    try {
        return localStorage.getItem(CLAVE);
    } catch {
        return null;
    }
}

export function aplicarTema(tema) {
    const oscuro = tema === 'oscuro';
    document.documentElement.classList.toggle('dark', oscuro);

    // Tiñe la barra del navegador en móvil del mismo color que la cabecera.
    // Sin esto queda una franja blanca encima de una interfaz oscura.
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', oscuro ? '#141316' : '#fdfbff');
    }
}

export function alternarTema() {
    const nuevo = document.documentElement.classList.contains('dark') ? 'claro' : 'oscuro';

    try {
        localStorage.setItem(CLAVE, nuevo);
    } catch {
        // Sin poder guardar, el cambio vale para esta sesión y se pierde
        // después. Es peor que nada, pero mucho mejor que no responder al
        // toque.
    }

    aplicarTema(nuevo);
    return nuevo;
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) {
        event.preventDefault();
        alternarTema();
    }
});

// Si el usuario nunca eligió, se sigue al sistema aunque cambie en caliente
// (por ejemplo, al activarse el modo oscuro automático al anochecer).
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    if (temaGuardado() === null) {
        aplicarTema(e.matches ? 'oscuro' : 'claro');
    }
});
