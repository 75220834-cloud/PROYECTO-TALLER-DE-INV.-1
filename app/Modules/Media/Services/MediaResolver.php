<?php

declare(strict_types=1);

namespace App\Modules\Media\Services;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
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
 * Dentro de cada escalon, la foto real gana a la provisional y la mas
 * reciente a la anterior. Sin ese orden, subir la foto real no servia de
 * nada: la consulta devolvia la primera fila, que era la provisional.
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
            $byComponent = $this->byComponent($componentKey)->first();

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
    public function alternatesFor(Collection $stepAssets, ?MediaAsset $primary, ?string $componentKey = null): Collection
    {
        // Ademas de las asignadas al paso, las otras fotos del mismo
        // componente: el boton de encendido de una «todo en uno», de una
        // torre y de un NUC se suben con la misma clave, y el docente ve la
        // de su equipo en «Ver otra vista». Las provisionales no se mezclan
        // con las reales: si ya hay una foto real, sobran.
        $sameComponent = $componentKey === null
            ? collect()
            : $this->byComponent($componentKey)
                ->when($primary !== null && ! $primary->is_demo, fn ($q) => $q->where('is_demo', false))
                ->get();

        return $stepAssets
            ->merge($sameComponent)
            ->filter(fn (MediaAsset $a) => $a->is_active && $a->id !== $primary?->id)
            ->unique('id')
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

        return $this->byComponent($componentKey)->first();
    }

    /**
     * Imagenes activas de un componente: primero las reales, luego la mas
     * reciente.
     *
     * @return Builder<MediaAsset>
     */
    private function byComponent(string $componentKey): Builder
    {
        return MediaAsset::query()
            ->active()
            ->where('scope_type', 'component')
            ->where('scope_key', $componentKey)
            ->orderBy('is_demo')
            ->orderByDesc('id');
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
