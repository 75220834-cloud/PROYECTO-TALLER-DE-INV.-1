<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\AbuseRejection;
use App\Modules\Incidents\Models\Incident;
use App\Shared\Enums\AbuseReason;

/**
 * Guarda antiabuso de la creacion de tickets (plan 16.4).
 *
 * PRINCIPIO RECTOR: mirar es gratis, movilizar a un tecnico no.
 *
 * El diagnostico guiado es completamente abierto: navegar el catalogo y
 * seguir los pasos no le cuesta nada a la institucion. Lo que se protege
 * es la ACCION costosa: crear un ticket que hace caminar a una persona
 * hasta un aula.
 *
 * Esto sustituye a la URL firmada del diseno anterior. Una URL firmada
 * fija no era un control real: quien escanea el cartel una vez conserva el
 * enlace para siempre y puede compartirlo. Se protege el efecto, no la
 * puerta.
 *
 * ORDEN DE LOS CONTROLES: primero los que no tocan la base de datos. Un
 * bot que dispara mil peticiones no debe costar mil consultas.
 */
final class AbuseGuard
{
    public function check(AbuseContext $ctx): AbuseVerdict
    {
        // --- Controles baratos, sin base de datos -----------------------

        // 1. Confirmacion explicita. Ademas de antiabuso, evita la causa
        //    mas frecuente de ruido: el toque accidental.
        if (! $ctx->confirmed) {
            return $this->reject($ctx, AbuseReason::Unconfirmed);
        }

        // 2. Senales de no-humano. Sin CAPTCHA: un CAPTCHA contradice de
        //    frente el requisito de que el sistema sea usable por docentes
        //    con poca familiaridad tecnologica (plan 8).
        if ($this->looksAutomated($ctx)) {
            return $this->reject($ctx, AbuseReason::BotSignal);
        }

        // --- Controles con base de datos --------------------------------

        // 3. Antiduplicados: mismo aula + misma categoria con ticket
        //    abierto. Varios docentes reportando la misma falla es lo
        //    NORMAL, no un ataque: por eso se ofrece sumarse en lugar de
        //    rechazar a secas.
        if (($existing = $this->openDuplicate($ctx)) !== null) {
            return $this->reject($ctx, AbuseReason::Duplicate, $existing);
        }

        // 4. Tope de tickets abiertos por aula.
        if (($blocking = $this->roomAtCapacity($ctx)) !== null) {
            return $this->reject($ctx, AbuseReason::RoomActiveLimit, $blocking);
        }

        // 5. Limite por dispositivo.
        if ($this->deviceOverLimit($ctx)) {
            return $this->reject($ctx, AbuseReason::DeviceRate);
        }

        // 6. Limite por IP. Deliberadamente LAXO y nunca el control
        //    principal: en la WiFi institucional muchos docentes comparten
        //    IP de salida, asi que un umbral estrecho bloquearia a usuarios
        //    legitimos (riesgo R18 del plan).
        if ($this->ipOverLimit($ctx)) {
            return $this->reject($ctx, AbuseReason::IpRate);
        }

        // 7. Similitud: se MARCA para revision, no se bloquea. Dos docentes
        //    pueden describir el mismo sintoma con palabras casi iguales
        //    sin que ninguno mienta.
        return AbuseVerdict::allow(flagged: $this->looksRepetitive($ctx));
    }

    // ------------------------------------------------------- controles

    private function looksAutomated(AbuseContext $ctx): bool
    {
        // Campo trampa oculto por CSS: una persona no lo ve ni lo rellena.
        if (filled($ctx->honeypot)) {
            return true;
        }

        $minSeconds = (int) config('incidencias.abuse.min_form_seconds');

        // Nulo = no se pudo medir. No se penaliza: preferimos dejar pasar
        // un bot antes que bloquear a un docente porque su navegador no
        // envio el campo.
        return $ctx->formElapsedSeconds !== null && $ctx->formElapsedSeconds < $minSeconds;
    }

    private function openDuplicate(AbuseContext $ctx): ?Incident
    {
        if ($ctx->category === null) {
            return null;
        }

        $windowMinutes = (int) config('incidencias.abuse.duplicate_window_minutes');

        return Incident::query()
            ->visibleToSupport()
            ->open()
            ->where('room_id', $ctx->room->id)
            ->where('category_id', $ctx->category->id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->latest('created_at')
            ->first();
    }

    private function roomAtCapacity(AbuseContext $ctx): ?Incident
    {
        $max = (int) config('incidencias.abuse.max_active_per_room');

        $open = Incident::query()
            ->visibleToSupport()
            ->open()
            ->where('room_id', $ctx->room->id)
            ->latest('created_at')
            ->get();

        return $open->count() >= $max ? $open->first() : null;
    }

    private function deviceOverLimit(AbuseContext $ctx): bool
    {
        if (blank($ctx->deviceKey)) {
            return false;
        }

        $max = (int) config('incidencias.abuse.max_tickets_per_device_hour');

        return Incident::query()
            ->visibleToSupport()
            ->where('device_key', $ctx->deviceKey)
            ->where('created_at', '>=', now()->subHour())
            ->count() >= $max;
    }

    private function ipOverLimit(AbuseContext $ctx): bool
    {
        if (blank($ctx->ipHash)) {
            return false;
        }

        $max = (int) config('incidencias.abuse.max_tickets_per_ip_hour');

        return Incident::query()
            ->visibleToSupport()
            ->where('ip_hash', $ctx->ipHash)
            ->where('created_at', '>=', now()->subHour())
            ->count() >= $max;
    }

    /**
     * Descripciones casi identicas en poco tiempo desde el mismo
     * dispositivo. Solo marca; nunca bloquea.
     */
    private function looksRepetitive(AbuseContext $ctx): bool
    {
        if (blank($ctx->description) || blank($ctx->deviceKey)) {
            return false;
        }

        $threshold = (float) config('incidencias.abuse.similarity_threshold');

        $recent = Incident::query()
            ->visibleToSupport()
            ->where('device_key', $ctx->deviceKey)
            ->where('created_at', '>=', now()->subHour())
            ->pluck('reported_description')
            ->filter();

        foreach ($recent as $previous) {
            similar_text(
                mb_strtolower((string) $previous),
                mb_strtolower($ctx->description),
                $percent
            );

            if ($percent / 100 >= $threshold) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------- registro

    private function reject(AbuseContext $ctx, AbuseReason $reason, ?Incident $related = null): AbuseVerdict
    {
        AbuseRejection::create([
            'room_id' => $ctx->room->id,
            'category_id' => $ctx->category?->id,
            'reason' => $reason->value,
            'ip_hash' => $ctx->ipHash,
            'device_key' => $ctx->deviceKey,

            // Fragmento acotado: suficiente para investigar, insuficiente
            // para acumular datos que el sistema no necesita.
            'payload_excerpt' => $ctx->description !== null
                ? mb_substr($ctx->description, 0, 255)
                : null,

            'related_incident_id' => $related?->id,
            'occurred_at' => now(),
        ]);

        return AbuseVerdict::reject($reason, $related);
    }
}
