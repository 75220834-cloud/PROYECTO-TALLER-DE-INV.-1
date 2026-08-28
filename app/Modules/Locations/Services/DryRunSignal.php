<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use RuntimeException;

/**
 * Aborta la transaccion de una importacion en vista previa o con errores.
 *
 * Es una excepcion y no un `return` porque revertir una transaccion de
 * Laravel exige salir por excepcion. Se captura en el mismo servicio: nunca
 * escapa hacia arriba ni llega al usuario como un fallo.
 */
final class DryRunSignal extends RuntimeException {}
