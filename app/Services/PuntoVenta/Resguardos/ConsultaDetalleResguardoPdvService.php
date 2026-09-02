<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaDetalleResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{resguardo: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision',
            'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
            'incidencias' => fn ($q) => $q->orderByDesc('reportado_at')->orderByDesc('id'),
            'eventos' => fn ($q) => $q
                ->with('actor:id,username')
                ->orderByDesc('ocurrido_at')
                ->orderByDesc('id'),
        ]);

        return [
            'resguardo' => $this->serializarResguardo($resguardo),
            'timeline' => $resguardo->eventos
                ->map(fn (ResguardoPdvEvento $evento) => $this->serializarEvento($evento))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        return [
            'id' => $resguardo->id,
            'estado' => $resguardo->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
            'pedido_bma_id' => $resguardo->pedido_bma_id,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
            'devolucion_confirmada_at' => $resguardo->devolucion_confirmada_at?->toIso8601String(),
            'entrega_bloqueada' => $resguardo->entrega_bloqueada,
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
            'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                'id' => $bulto->id,
                'folio' => $bulto->folio,
                'tipo' => $bulto->tipo,
                'estado' => $bulto->estado,
                'recepcion_at' => $bulto->recepcion_at?->toIso8601String(),
                'entrega_at' => $bulto->entrega_at?->toIso8601String(),
            ])->values()->all(),
            'incidencias' => $resguardo->incidencias->map(fn ($incidencia) => [
                'id' => $incidencia->id,
                'tipo' => $incidencia->tipo,
                'tipo_etiqueta' => EtiquetasResguardoPdv::tiposIncidencia()[$incidencia->tipo] ?? $incidencia->tipo,
                'estado' => $incidencia->estado,
                'descripcion' => $incidencia->descripcion,
                'reportado_at' => $incidencia->reportado_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarEvento(ResguardoPdvEvento $evento): array
    {
        return [
            'id' => $evento->id,
            'tipo_evento' => $evento->tipo_evento,
            'tipo_etiqueta' => EtiquetasResguardoPdv::etiquetaEvento($evento->tipo_evento),
            'estado_anterior' => $evento->estado_anterior,
            'estado_nuevo' => $evento->estado_nuevo,
            'estado_anterior_etiqueta' => $evento->estado_anterior
                ? EtiquetasResguardoPdv::etiquetaEstado($evento->estado_anterior)
                : null,
            'estado_nuevo_etiqueta' => $evento->estado_nuevo
                ? EtiquetasResguardoPdv::etiquetaEstado($evento->estado_nuevo)
                : null,
            'ocurrido_at' => $evento->ocurrido_at?->toIso8601String(),
            'actor_referencia' => $this->referenciaActor($evento->actor),
        ];
    }

    private function referenciaCliente(ResguardoPdv $resguardo): string
    {
        $numero = $resguardo->cliente?->numero_cliente;

        if ($numero !== null && $numero !== '') {
            return '#'.(string) $numero;
        }

        return $resguardo->snapshot_folio ?: 'Sin referencia';
    }

    private function referenciaActor(?User $actor): ?string
    {
        if (! $actor instanceof User) {
            return null;
        }

        if (filled($actor->username)) {
            return '@'.$actor->username;
        }

        return 'Colaborador';
    }
}
