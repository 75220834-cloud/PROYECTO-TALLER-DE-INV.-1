<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desde internet solo existe la parte del docente.
 *
 * El sistema se publica con un tunel o un proxy para que el docente entre
 * con sus datos moviles. Eso abre la puerta a todo el mundo, y el panel de
 * soporte no tiene por que estar detras de ella: quien lo usa trabaja en la
 * universidad. Asi, la pantalla de inicio de sesion ni siquiera es visible
 * desde fuera, y no hay contrasena que adivinar.
 *
 * LISTA DE PERMITIDOS, NO DE PROHIBIDOS
 *
 * Se enumera lo que el docente necesita y todo lo demas se cierra. Al
 * reves, cada pantalla nueva del panel naceria expuesta hasta que alguien
 * recordara anadirla a la lista.
 *
 * COMO SE SABE QUE UNA PETICION VIENE DE FUERA
 *
 * El tunel entrega las peticiones desde 127.0.0.1, asi que la IP de
 * conexion sola no basta: cualquier visitante de internet pareceria estar
 * sentado frente al servidor. Se considera externa toda peticion que:
 *
 *   - traiga cabeceras de reenvio (Cloudflare, proxy inverso), o
 *   - venga dirigida a la direccion publica, o
 *   - llegue desde una IP fuera de las redes internas configuradas.
 *
 * Las cabeceras de reenvio las anade el tunel en su extremo; un visitante
 * no puede quitarlas. Alguien de la red interna si podria anadirlas, pero
 * con eso solo consigue cerrarse la puerta a si mismo.
 */
final class RestrictPanelToInternalNetwork
{
    /** Primer segmento de las rutas que el docente usa desde su celular. */
    private const PUBLIC_SEGMENTS = ['', 'reportar', 'imagen', 'salud', 'up'];

    private const FORWARDING_HEADERS = [
        'CF-Connecting-IP',
        'CF-Ray',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Real-IP',
        'Forwarded',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('incidencias.panel.solo_red_interna')
            || $this->isTeacherPath($request)
            || $this->isInternal($request)) {
            return $next($request);
        }

        // 404 y no 403: un 403 confirma que ahi hay algo que proteger.
        abort(404);
    }

    private function isTeacherPath(Request $request): bool
    {
        $first = explode('/', trim($request->path(), '/'))[0];

        return in_array($first, self::PUBLIC_SEGMENTS, true);
    }

    private function isInternal(Request $request): bool
    {
        foreach (self::FORWARDING_HEADERS as $header) {
            if ($request->headers->has($header)) {
                return false;
            }
        }

        $publicHost = parse_url((string) config('incidencias.public_url'), PHP_URL_HOST);

        if (is_string($publicHost) && strcasecmp($request->getHost(), $publicHost) === 0) {
            return false;
        }

        // La IP de la conexion tal cual, sin resolver proxies: aqui interesa
        // quien abrio el socket, no quien dice ser.
        $remote = (string) $request->server('REMOTE_ADDR', '');

        return $remote !== '' && IpUtils::checkIp($remote, config('incidencias.panel.redes'));
    }
}
