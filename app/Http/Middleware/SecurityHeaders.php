<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en toda respuesta HTML (plan 16.2).
 *
 * POR QUE LA CSP NO LLEVA 'unsafe-inline' EN script-src
 *
 * Es la unica decision que hace que esta cabecera valga de algo. Si se
 * permite JavaScript incrustado en el HTML, el navegador no puede distinguir
 * el que escribimos nosotros del que inyecta un atacante, y la politica pasa
 * a ser decorativa. Por eso ningun `onclick=""` sobrevive en las vistas: el
 * comportamiento vive en resources/js/interactions.js.
 *
 * Esto importa mas de lo normal en este sistema por dos motivos concretos:
 *
 * 1. El docente entra SIN autenticarse y escribe texto libre que despues se
 *    muestra en el panel de soporte. Un tecnico autenticado leyendo la
 *    descripcion de una incidencia es exactamente el objetivo de un XSS
 *    almacenado.
 * 2. Parte del texto que se muestra proviene de un modelo de lenguaje, que
 *    se trata como entrada no confiable (plan 16.2).
 *
 * En style-src SI se permite 'unsafe-inline', y conviene decir por que en
 * lugar de disimularlo: el cartel imprimible y las anotaciones sobre las
 * imagenes usan estilos incrustados. El riesgo de CSS inyectado es real pero
 * mucho menor que el de script, y quitarlo exigiria rehacer el cartel — que
 * lleva sus estilos dentro precisamente para no romperse en la impresora.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Las descargas (CSV, imagenes, QR) no renderizan nada: aplicarles
        // una CSP no aporta y solo confunde al depurar.
        $isHtml = str_contains((string) $response->headers->get('Content-Type'), 'text/html');

        $headers = [
            // Impide que el navegador adivine el tipo de un archivo servido.
            // Sin esto, una imagen manipulada puede acabar ejecutandose como
            // script — y el banco de imagenes acepta subidas.
            'X-Content-Type-Options' => 'nosniff',

            // Nadie deberia enmarcar este sistema: evita el secuestro de
            // clics sobre acciones del panel (asignar, cerrar, cancelar).
            'X-Frame-Options' => 'DENY',

            // No filtrar la ruta completa —que puede llevar el identificador
            // de una incidencia— al navegar hacia fuera.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // El sistema no necesita ninguna de estas capacidades.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if ($isHtml) {
            $headers['Content-Security-Policy'] = $this->policy();
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function policy(): string
    {
        $scriptSrc = "'self'";
        $connectSrc = "'self'";
        $styleSrc = "'self' 'unsafe-inline'";

        /*
         * En desarrollo, Vite sirve los assets desde su propio servidor y
         * mantiene un socket abierto para recargar en caliente. Sin abrir la
         * politica para ese origen, la aplicacion se veria rota en local y el
         * equipo acabaria desactivando la cabecera entera — que es el peor
         * resultado posible. La excepcion se limita a `local`.
         */
        if (app()->environment('local')) {
            /*
             * Los TRES orígenes hacen falta. Vite anuncia su servidor por el
             * nombre que resuelva el sistema, y en Windows `localhost` suele
             * resolver primero a IPv6: sin `[::1]` la aplicación se ve sin
             * estilos en desarrollo y la consola se llena de violaciones de
             * CSP. Lo encontró el navegador, no las pruebas — que no cargan
             * assets.
             */
            $vite = 'http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173';
            $scriptSrc .= " {$vite}";
            $styleSrc .= " {$vite}";
            $connectSrc .= " {$vite} ws://localhost:5173 ws://127.0.0.1:5173 ws://[::1]:5173";
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            // data: es necesario para el QR, que se genera y se incrusta.
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src {$connectSrc}",
            // El sistema no incrusta nada de terceros ni usa Flash/applets.
            "object-src 'none'",
            "base-uri 'self'",
            // Los formularios solo pueden enviarse al propio sistema: cierra
            // la via de exfiltrar datos hacia un servidor externo.
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
