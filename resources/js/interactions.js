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

/*
 * Recarga periódica de una página de solo lectura.
 *
 * La usa el seguimiento del docente, que promete en pantalla «esta página se
 * actualiza sola». Una promesa así en la interfaz obliga: si no se cumple,
 * el docente mira una pantalla congelada creyendo que nada avanza.
 *
 * Se detiene cuando la pestaña no está visible. Un docente que dejó la
 * página abierta y guardó el móvil no necesita que su teléfono consulte al
 * servidor cada treinta segundos durante toda la clase.
 */
const refrescable = document.querySelector('[data-auto-refresh]');

if (refrescable) {
    const cada = Number(refrescable.dataset.autoRefresh) * 1000;

    setInterval(() => {
        if (document.visibilityState === 'visible') {
            window.location.reload();
        }
    }, Number.isFinite(cada) && cada >= 5000 ? cada : 30000);
}

/* ------------------------------------------------------------------------
   Cascada de ubicación en una sola pantalla.

   El HTML llega con TODAS las opciones dentro: los 10 pabellones, los 47
   pisos y las 212 aulas. Aquí se guardan en memoria, se vacían las listas y
   se van rellenando según lo que el docente elija.

   Por qué así y no pidiéndole los datos al servidor en cada paso: una ida y
   vuelta por pregunta, en la red de un aula que puede estar saturada, justo
   cuando algo acaba de fallar. Todo el catálogo pesa menos que una sola foto
   de las que se muestran después.

   Este archivo no sabe qué es una sede ni un aula. Solo sabe que una lista
   depende de otra (data-depends-on) y que cada opción declara a qué padre
   pertenece (data-parent). Añadir un nivel más no le obliga a cambiar.
------------------------------------------------------------------------- */
const cascada = document.querySelector('[data-cascade]');

if (cascada) {
    const pasos = [...cascada.querySelectorAll('[data-cascade-step]')];

    // Se copian las opciones ANTES de vaciar nada: son la única fuente y en
    // el DOM ya no van a estar.
    const opciones = new Map();

    for (const paso of pasos) {
        const lista = paso.querySelector('select');
        if (!lista) continue;

        opciones.set(lista.id, [...lista.querySelectorAll('option[data-parent]')]);
    }

    const refrescar = (paso) => {
        const lista = paso.querySelector('select');
        const padre = cascada.querySelector(`#${paso.dataset.dependsOn}`);
        if (!lista || !padre) return;

        const hijas = (opciones.get(lista.id) || []).filter(
            (o) => o.dataset.parent === padre.value,
        );

        // Se conserva el marcador de posición («Elige tu piso…»), que es la
        // primera opción y la única sin data-parent.
        const marcador = lista.querySelector('option:not([data-parent])');
        lista.replaceChildren(...(marcador ? [marcador] : []), ...hijas);
        lista.value = '';

        // Un solo hijo no es una decisión: se elige solo y se ahorra un
        // toque. Ocurre de verdad en los pabellones B, K y L, que tienen una
        // sola aula.
        if (hijas.length === 1) {
            lista.value = hijas[0].value;
        }

        paso.hidden = padre.value === '';

        if (paso.hidden || lista.value === '') {
            ocultarDesde(paso);
        } else {
            propagar(lista);
        }
    };

    const ocultarDesde = (desde) => {
        let siguiente = pasos.indexOf(desde) + 1;

        for (; siguiente < pasos.length; siguiente++) {
            pasos[siguiente].hidden = true;
            const lista = pasos[siguiente].querySelector('select');
            if (lista) lista.value = '';
        }
    };

    const propagar = (lista) => {
        for (const paso of pasos) {
            if (paso.dataset.dependsOn === lista.id) refrescar(paso);
        }
    };

    cascada.addEventListener('change', (event) => {
        const lista = event.target.closest('select');
        if (!lista) return;

        // El botón de continuar depende del aula pero no es una lista: se
        // muestra en cuanto hay valor.
        for (const paso of pasos) {
            if (paso.dataset.dependsOn !== lista.id) continue;

            if (paso.querySelector('select')) {
                refrescar(paso);
            } else {
                paso.hidden = lista.value === '';
            }
        }
    });

    // Estado inicial: vacía las listas dependientes y muestra lo que
    // corresponda si el navegador conservó una elección anterior.
    for (const paso of pasos) {
        if (paso.querySelector('select')) {
            refrescar(paso);
        } else {
            const padre = cascada.querySelector(`#${paso.dataset.dependsOn}`);
            paso.hidden = !padre || padre.value === '';
        }
    }
}
