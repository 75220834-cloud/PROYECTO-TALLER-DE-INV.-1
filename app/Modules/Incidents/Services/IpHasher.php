<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use Illuminate\Support\Facades\Config;

/**
 * Convierte una direccion IP en un identificador opaco y estable.
 *
 * La IP NUNCA se guarda en claro (plan 16.4). El hash cumple exactamente
 * lo que el sistema necesita —agrupar peticiones del mismo origen para
 * aplicar un limite— y no permite recuperar la direccion original.
 *
 * Se usa HMAC con la clave de la aplicacion, no un hash a secas. Un SHA256
 * pelado de una IP es trivial de revertir: el espacio de direcciones IPv4
 * completo cabe en una tabla precalculada de unas horas de computo. Con
 * HMAC, sin la clave no hay tabla que valga.
 *
 * Consecuencia a tener presente: si APP_KEY cambia, los hashes anteriores
 * dejan de coincidir y los contadores por IP se reinician. Es aceptable
 * (solo afecta a una ventana de una hora) y preferible a guardar la IP.
 */
final class IpHasher
{
    public function hash(?string $ip): ?string
    {
        if (blank($ip)) {
            return null;
        }

        return hash_hmac('sha256', $ip, $this->key());
    }

    private function key(): string
    {
        $key = (string) Config::get('app.key');

        // Laravel guarda la clave codificada en base64.
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
