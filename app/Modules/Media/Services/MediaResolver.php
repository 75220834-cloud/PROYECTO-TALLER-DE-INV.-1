<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Collection;

/**
 * Resuelve QUE imagen mostrar en cada paso del diagnostico (plan 13.6).
 *
 * Cascada, del mas especifico al mas generico:
 *
 *   1. Imagen asignada expresamente al paso
 *   2. Imagen del componente        (scope component + component_key)
 *   3. Imagen del tipo de equipo    (scope equipment_type)
 *   4. Sin imagen  ->  el paso se muestra solo con texto
 *
 * El ultimo escalon es tan importante como los otros: el sistema NUNCA
 * falla por ausencia de imagen ni muestra un hueco roto. Degrada a texto,
 * que es exactamente como funciona hoy sin el banco visual.
 *
 * El LLM interviene solo para SUGERIR un codigo del catalogo (funcion F4);
 * si devuelve uno que no existe, se descarta y se cae a esta cascada.
 */
final class MediaResolver
{
    /**
     * Imagen principal de un paso.
     *
     * @param  Collection<int, MediaAsset>  $stepAssets  imagenes ya asignadas al paso
     */
    public function primaryFor(
        Collection $stepAssets,
        ?string $componentKey,
        ?int $equipmentTypeAssetId = null,
    ): ?MediaAsset {
        // 1. Asignada al paso.
        $primary = $stepAssets->firstWhere('pivot.role', 'primary')
            ?? $stepAssets->first();

        if ($primary instanceof MediaAsset && $primary->is_active) {
            return $primary;
        }

        // 2. Por componente.
        if ($componentKey !== null) {
            $byComponent = MediaAsset::query()
                ->active()
                ->where('scope_type', 'component')
                ->where('scope_key', $componentKey)
                ->first();

            if ($byComponent !== null) {
                return $byComponent;
            }
        }

        // 3. Por tipo de equipo.
        if ($equipmentTypeAssetId !== null) {
            $byType = MediaAsset::query()->active()->find($equipmentTypeAssetId);

            if ($byType !== null) {
                return $byType;
            }
        }

        // 4. Sin imagen: el paso se muestra solo con texto.
        return null;
    }

    /**
     * Vistas alternativas ("Ver otra vista"). Solo se ofrece el boton si
     * existe algo distinto que mostrar.
     *
     * @param  Collection<int, MediaAsset>  $stepAssets
     * @return Collection<int, MediaAsset>
     */
    public function alternatesFor(Collection $stepAssets, ?MediaAsset $primary): Collection
    {
        return $stepAssets
            ->filter(fn (MediaAsset $a) => $a->is_active && $a->id !== $primary?->id)
            ->values();
    }

    /**
     * Imagen del componente aislado, para la ayuda "¿cuál es este cable?".
     *
     * Resuelve el problema de vocabulario sin obligar al docente a admitir
     * que no conoce el nombre: no pregunta nada, solo muestra.
     */
    public function componentReference(?string $componentKey): ?MediaAsset
    {
        if ($componentKey === null) {
            return null;
        }

        return MediaAsset::query()
            ->active()
            ->where('scope_type', 'component')
            ->where('scope_key', $componentKey)
            ->orderByRaw("CASE WHEN type = 'diagram' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * Valida un codigo sugerido por el LLM.
     *
     * Devuelve null si el codigo no existe: el modelo NUNCA puede introducir
     * una imagen que no este en el catalogo (plan 13.4, regla 9).
     */
    public function fromSuggestedCode(?string $code): ?MediaAsset
    {
        if ($code === null || $code === '') {
            return null;
        }

        return MediaAsset::query()->active()->where('code', $code)->first();
    }
}
