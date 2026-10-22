<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Shared\Support\Label;

/**
 * Convierte un registro de auditoria en texto legible.
 *
 * En la base queda el codigo de la accion («media.updated»), la clase
 * («MediaAsset») y los cambios en JSON con nombres de columna. Eso es lo que
 * conviene guardar; leerlo asi en el panel no. Lo que no tiene traduccion se
 * muestra tal cual: un registro nunca se oculta por falta de nombre.
 */
final class AuditText
{
    public static function action(string $action): string
    {
        return self::translate('audit.actions', $action);
    }

    public static function subject(?string $type, int|string|null $id): string
    {
        if ($type === null) {
            return '—';
        }

        $base = class_basename($type);

        return self::translate('audit.subjects', $base).' #'.$id;
    }

    /** Cambios como «Campo: valor · Campo: valor». */
    public static function changes(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? self::flatten($data) : $json;
    }

    /** @param  array<mixed>  $data */
    private static function flatten(array $data): string
    {
        $parts = [];

        foreach ($data as $field => $value) {
            $label = is_string($field) ? self::translate('audit.fields', $field) : null;
            $text = self::value((string) $field, $value);

            $parts[] = $label !== null ? "{$label}: {$text}" : $text;
        }

        return implode(' · ', $parts);
    }

    private static function value(string $field, mixed $value): string
    {
        return match (true) {
            is_array($value) => '('.self::flatten($value).')',
            is_bool($value) => $value ? 'sí' : 'no',
            $value === null => '—',
            in_array($field, ['role', 'role_from', 'role_to'], true) => User::roleLabel((string) $value),
            default => (string) $value,
        };
    }

    private static function translate(string $group, string $code): string
    {
        $text = Label::for($group, $code);

        return is_string($text) ? $text : $code;
    }
}
