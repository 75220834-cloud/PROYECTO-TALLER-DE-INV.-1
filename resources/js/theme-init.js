/*
 * Se compila aparte y se sirve como archivo propio, NO en linea.
 *
 * Un script en linea obligaria a abrir la CSP con 'unsafe-inline' o con un
 * hash, y abrirla por una comodidad de estilo seria justo lo contrario de
 * lo que la politica existe para hacer. El coste es una peticion mas, que
 * el navegador resuelve desde cache a partir de la segunda pantalla.
 */
try {
    var guardado = localStorage.getItem('tema');
    var oscuro = guardado === 'oscuro'
        || (guardado === null && window.matchMedia('(prefers-color-scheme: dark)').matches);

    if (oscuro) {
        document.documentElement.classList.add('dark');
    }
} catch (e) {
    // Sin acceso a localStorage se queda en claro. Es el caso raro y el
    // menos dañino.
}
