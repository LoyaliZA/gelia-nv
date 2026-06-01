<?php

namespace App\Services\WebPush;

class ConstruirPayloadDesdeNotificacionService
{
    public function desdeArray(array $data): array
    {
        $titulo = $data['titulo'] ?? $data['title'] ?? 'GELIA ERP';
        $cuerpo = $data['mensaje_visible'] ?? $data['mensaje'] ?? $data['body'] ?? '';
        $tipo = $data['tipo'] ?? null;

        return [
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => $this->resolverUrl($data),
            'tag' => $this->resolverTag($data),
            'tipo' => $tipo,
            'data' => $data,
        ];
    }

    private function resolverUrl(array $data): string
    {
        if (!empty($data['url'])) {
            return str_starts_with($data['url'], 'http') ? $data['url'] : url($data['url']);
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

        return url('/dashboard');
    }

    private function resolverTag(array $data): string
    {
        if (!empty($data['conversacion_id'])) {
            return 'mensaje-conv-' . $data['conversacion_id'];
        }

        if (!empty($data['solicitud_id'])) {
            return 'solicitud-' . $data['solicitud_id'];
        }

        if (!empty($data['activo_id'])) {
            return 'activo-' . $data['activo_id'];
        }

        return 'gelia-' . ($data['tipo'] ?? 'general') . '-' . now()->format('Ymd');
    }
}
