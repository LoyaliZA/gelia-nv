<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaFormularioEntregaResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{
     *     resguardo: array<string, mixed>,
     *     catalogos: array<string, mixed>,
     *     puede_entregar: bool,
     *     motivo_no_entregable: string|null
     * }
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR);

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
        ]);

        [$puedeEntregar, $motivo] = $this->evaluarEntregabilidad($resguardo);

        return [
            'resguardo' => $this->serializarResguardo($resguardo),
            'catalogos' => [
                'estados' => EtiquetasResguardoPdv::estados(),
                'relaciones' => EtiquetasResguardoPdv::relacionesEntrega(),
                'tipos_incidencia' => EtiquetasResguardoPdv::tiposIncidencia(),
                'metodo_validacion' => RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
            ],
            'puede_entregar' => $puedeEntregar,
            'motivo_no_entregable' => $motivo,
        ];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function evaluarEntregabilidad(ResguardoPdv $resguardo): array
    {
        if ($resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO) {
            return [false, 'Este resguardo ya fue entregado.'];
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
            $etiqueta = EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado);

            return [false, "No se puede entregar en estado «{$etiqueta}»."];
        }

        if ($resguardo->entrega_bloqueada) {
            return [false, 'La entrega está bloqueada por una incidencia o cancelación pendiente.'];
        }

        $incidenciasBloqueantes = $resguardo->incidencias
            ->filter(fn ($incidencia) => $incidencia->estado === ResguardoPdvIncidencia::ESTADO_ABIERTA
                && in_array($incidencia->tipo, [
                    ResguardoPdvIncidencia::TIPO_DANO,
                    ResguardoPdvIncidencia::TIPO_FALTANTE,
                ], true));

        if ($incidenciasBloqueantes->isNotEmpty()) {
            return [false, 'Existen incidencias abiertas de daño o faltante que bloquean la entrega.'];
        }

        $bultosRecibidos = $resguardo->bultos
            ->filter(fn ($bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO);

        if ($bultosRecibidos->isEmpty()) {
            return [false, 'No hay bultos en custodia listos para entregar.'];
        }

        return [true, null];
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
            'version' => (int) $resguardo->version,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_en_custodia' => $resguardo->bultos
                ->filter(fn ($bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO)
                ->count(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
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
            ])->values()->all(),
            'incidencias' => $resguardo->incidencias->map(fn ($incidencia) => [
                'id' => $incidencia->id,
                'tipo' => $incidencia->tipo,
                'tipo_etiqueta' => EtiquetasResguardoPdv::tiposIncidencia()[$incidencia->tipo] ?? $incidencia->tipo,
                'estado' => $incidencia->estado,
                'descripcion' => $incidencia->descripcion,
            ])->values()->all(),
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
}
