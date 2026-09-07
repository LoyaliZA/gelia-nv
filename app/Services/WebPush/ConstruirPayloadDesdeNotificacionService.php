<?php

namespace App\Services\WebPush;

class ConstruirPayloadDesdeNotificacionService
{
    public function desdeArray(array $data): array
    {
        $titulo = $data['titulo'] ?? $data['title'] ?? 'GELIA ERP';
        $cuerpo = $data['mensaje_visible'] ?? $data['mensaje'] ?? $data['body'] ?? '';
        $tipo = $data['tipo'] ?? null;

        if (($data['modulo'] ?? null) === 'punto_venta') {
            $cuerpo = $this->cuerpoMinimoPdv($data, $cuerpo);
        }

        return [
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => $this->resolverUrl($data),
            'tag' => $this->resolverTag($data),
            'tipo' => $tipo,
            'data' => $this->resolverDataPush($data),
        ];
    }

    private function resolverUrl(array $data): string
    {
        if (! empty($data['url'])) {
            return str_starts_with($data['url'], 'http') ? $data['url'] : url($data['url']);
        }

        if (! empty($data['turno_id']) && ($data['modulo'] ?? null) === 'punto_venta') {
            return url('/punto-venta/turnos/ventas');
        }

        if (! empty($data['resguardo_id']) && ($data['modulo'] ?? null) === 'punto_venta') {
            return url('/punto-venta/resguardos/'.$data['resguardo_id']);
        }

        if (!empty($data['conversacion_id'])) {
            return url('/mensajeria?conversacion=' . $data['conversacion_id']);
        }

        if (!empty($data['solicitud_id'])) {
            return url('/solicitudes?folio=' . $data['solicitud_id']);
        }

        if (!empty($data['activo_id'])) {
            return url('/activos');
        }

        if (!empty($data['ticket_id'])) {
            $url = $data['url'] ?? '/soporte/mis-tickets';
            return str_starts_with($url, 'http') ? $url : url($url);
        }

        return url('/dashboard');
    }

    private function resolverTag(array $data): string
    {
        if (! empty($data['idempotency_key'])) {
            return 'gelia-'.sha1((string) $data['idempotency_key']);
        }

        if (! empty($data['conversacion_id'])) {
            return 'mensaje-conv-' . $data['conversacion_id'];
        }

        if (!empty($data['solicitud_id'])) {
            return 'solicitud-' . $data['solicitud_id'];
        }

        if (!empty($data['activo_id'])) {
            return 'activo-' . $data['activo_id'];
        }

        if (!empty($data['ticket_id'])) {
            return 'soporte-ticket-' . $data['ticket_id'];
        }

        return 'gelia-'.($data['tipo'] ?? 'general').'-'.now()->format('Ymd');
    }

    private function cuerpoMinimoPdv(array $data, string $cuerpo): string
    {
        $folio = trim((string) ($data['folio'] ?? ''));
        $tipo = (string) ($data['tipo'] ?? '');

        if ($folio !== '' && str_starts_with($tipo, 'pdv.turno.')) {
            return match ($tipo) {
                'pdv.turno.reatencion' => "Turno {$folio} listo para reatención.",
                'pdv.turno.transferido' => "Turno {$folio} transferido a este puesto.",
                default => "Turno {$folio} asignado para atención.",
            };
        }

        if ($folio !== '' && str_starts_with($tipo, 'pdv.resguardo.')) {
            return mb_substr(trim($cuerpo), 0, 120);
        }

        return mb_substr(trim($cuerpo), 0, 120);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverDataPush(array $data): array
    {
        if (($data['modulo'] ?? null) !== 'punto_venta') {
            return $data;
        }

        return array_filter([
            'tipo' => $data['tipo'] ?? null,
            'turno_id' => $data['turno_id'] ?? null,
            'resguardo_id' => $data['resguardo_id'] ?? null,
            'folio' => $data['folio'] ?? null,
            'sucursal_id' => $data['sucursal_id'] ?? null,
            'url' => $data['url'] ?? null,
        ], fn ($valor) => $valor !== null && $valor !== '');
    }
}
