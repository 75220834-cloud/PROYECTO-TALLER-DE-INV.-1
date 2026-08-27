/*
 * Comportamientos de interfaz, por delegacion de eventos.
 *
 * POR QUE ESTAN AQUI Y NO EN onclick="" DENTRO DEL HTML
 *
 * La politica de seguridad de contenido (CSP) del sistema prohibe ejecutar
 * JavaScript incrustado en el HTML. Esa prohibicion es justamente lo que
 * convierte a la CSP en una defensa real contra XSS: si un atacante logra
 * inyectar un <script> o un onclick en una pagina, el navegador lo ignora.
 *
 * Con un solo onclick="" en cualquier vista habria que abrir la politica con
 * 'unsafe-inline', y entonces la CSP dejaria de proteger de nada — no
 * distingue el onclick que escribimos nosotros del que inyecta un atacante.
 * Por eso el HTML solo declara INTENCION con atributos data-*, y el
 * comportamiento vive en este archivo.
 *
 * Se usa delegacion en document para que funcione tambien con contenido que
 * aparezca despues de cargar la pagina.
 */

// Selectores de filtro que se envian solos al cambiar.
document.addEventListener('change', (event) => {
    const control = event.target.closest('[data-auto-submit]');

    if (control && control.form) {
        control.form.submit();
    }
});

// Confirmacion antes de una accion que no se puede deshacer.
document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-confirm]');

    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) {
        window.print();
        return;
    }

    // Filas de tabla que llevan al detalle. Se ignora el clic cuando cae
    // sobre un enlace o un boton propio de la fila: de lo contrario, pulsar
    // "Asignar" navegaria al detalle en lugar de asignar.
    const row = event.target.closest('[data-row-href]');

    if (row && !event.target.closest('a, button, input, select, label')) {
        window.location.assign(row.dataset.rowHref);
    }
});
