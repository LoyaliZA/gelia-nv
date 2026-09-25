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
        $explicita = $this->rutaExplicita($data['url'] ?? null);

        if (! empty($data['ticket_id']) && $this->esListado($explicita, ['/soporte/agente/tickets', '/soporte/mis-tickets'])) {
            $portal = ($explicita && str_contains((string) parse_url($explicita, PHP_URL_PATH), '/mis-tickets'))
                ? '/soporte/mis-tickets'
                : '/soporte/agente/tickets';

            return url($portal.'/'.$data['ticket_id']);
        }

        if (! empty($data['conversacion_id']) && $this->esListado($explicita, ['/mensajeria'])) {
            return url('/mensajeria?conversacion='.$data['conversacion_id']);
        }

        if (! empty($data['solicitud_id']) && $this->esListado($explicita, ['/solicitudes'])) {
            return url('/solicitudes?q='.$data['solicitud_id']);
        }

        if (! empty($data['activo_id']) && $this->esListado($explicita, ['/activos'])) {
            return url('/activos/'.$data['activo_id']);
        }

        if (($data['modulo'] ?? null) === 'facturas' && ! empty($data['folio']) && $this->esListado($explicita, ['/facturas'])) {
            return url('/facturas?q='.rawurlencode((string) $data['folio']));
        }

        if (($data['modulo'] ?? null) === 'traspasos') {
            if (($data['tipo'] ?? null) === 'listo_cedis') {
                return url('/traspasos/cedis');
            }
            if (! empty($data['folio']) && $this->esListado($explicita, ['/traspasos'])) {
                return url('/traspasos?folio='.rawurlencode((string) $data['folio']));
            }
        }

        if (($data['modulo'] ?? null) === 'cobranza') {
            if ($explicita && ! $this->esListado($explicita, ['/auto-cobranza'])) {
                return $explicita;
            }
            $q = $data['clientes_busqueda'] ?? $data['numero_cliente'] ?? null;
            if (! $q && ! empty($data['cliente']) && ! in_array($data['cliente'], ['Varios Clientes', 'Carga Credibox'], true)) {
                $q = $data['cliente'];
            }

            return $q ? url('/auto-cobranza?q='.rawurlencode((string) $q)) : url('/auto-cobranza');
        }

        if ((($data['modulo'] ?? null) === 'control_pedidos' || ! empty($data['pedido_bma_id']))
            && $this->esListado($explicita, ['/control-pedidos'])) {
            $q = $data['folio'] ?? $data['pedido_bma_id'] ?? null;

            return $q ? url('/control-pedidos?q='.rawurlencode((string) $q)) : url('/control-pedidos');
        }

        if ($explicita) {
            return $explicita;
        }

        if (! empty($data['turno_id']) && ($data['modulo'] ?? null) === 'punto_venta') {
            return url('/punto-venta/turnos/ventas');
        }

        if (! empty($data['resguardo_id']) && ($data['modulo'] ?? null) === 'punto_venta') {
            return url('/punto-venta/resguardos/'.$data['resguardo_id']);
        }

        return url('/dashboard');
    }

    private function rutaExplicita(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }

    /**
     * @param  array<int, string>  $bases
     */
    private function esListado(?string $url, array $bases): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = rtrim((string) $path, '/') ?: '/';

        return in_array($path, $bases, true);
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
        $notificationId = $data['notification_id'] ?? null;

        if (($data['modulo'] ?? null) !== 'punto_venta') {
            return $data;
        }

        $filtrado = array_filter([
            'tipo' => $data['tipo'] ?? null,
            'turno_id' => $data['turno_id'] ?? null,
            'resguardo_id' => $data['resguardo_id'] ?? null,
            'folio' => $data['folio'] ?? null,
            'sucursal_id' => $data['sucursal_id'] ?? null,
            'url' => $data['url'] ?? null,
            'notification_id' => $notificationId,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return $filtrado;
    }
}
