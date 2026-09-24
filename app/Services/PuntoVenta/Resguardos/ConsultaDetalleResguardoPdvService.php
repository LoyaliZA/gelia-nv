<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\AutorizacionConsultaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoRecepcionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorBultosEmpaqueCedisPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorIncidenciaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorPedidoRevisionResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorRegistroManualResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorRetiroPedidoResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaDetalleResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
        private readonly PlazosCustodiaResguardoPdvConfig $plazos,
        private readonly ConsultaAuditoriaResguardoPdvService $auditoria,
        private readonly SincronizarCantidadBultosEsperadaResguardoPdvService $sincronizarCantidadBultos,
        private readonly AutorizacionConsultaResguardoPdv $autorizacion,
    ) {}

    /**
     * @return array{resguardo: array<string, mixed>, timeline: list<array<string, mixed>>}
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->autorizacion->asegurarDetalleResguardo($user, $resguardo);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision,envia_a_otra_persona,envia_otra_persona',
            'pedido.bultosEmpaque.documentos',
            'evidencias',
            'eventoRegistroManual.actor:id,name,username',
            'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
            'incidencias' => fn ($q) => $q
                ->with([
                    'evidencias',
                    'reportadoPor:id,username',
                    'autorizadoPor:id,username',
                ])
                ->orderByDesc('reportado_at')
                ->orderByDesc('id'),
        ]);

        if ((int) $resguardo->cantidad_bultos_esperada < 1) {
            $resguardo = $this->sincronizarCantidadBultos->ejecutar($resguardo);
            $resguardo->load([
                'sucursal:id,nombre',
                'cliente:id,numero_cliente',
                'pedido:id,folio,folio_remision,envia_a_otra_persona,envia_otra_persona',
                'pedido.bultosEmpaque.documentos',
                'evidencias',
                'eventoRegistroManual.actor:id,name,username',
                'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
                'incidencias' => fn ($q) => $q
                    ->with([
                        'evidencias',
                        'reportadoPor:id,username',
                        'autorizadoPor:id,username',
                    ])
                    ->orderByDesc('reportado_at')
                    ->orderByDesc('id'),
            ]);
        }

        $auditoria = $this->auditoria->obtener($user, $resguardo);

        return [
            'resguardo' => $this->serializarResguardo($resguardo),
            'timeline' => $auditoria['timeline'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        $antiguedadConfigurada = $this->antiguedadConfigurada();
        $evaluacion = $antiguedadConfigurada
            ? $this->antiguedad->evaluar($resguardo)
            : [
                'clasificaciones' => $this->antiguedad->clasificacionesVacias(),
                'fecha_limite_custodia' => null,
                'fecha_limite_rezago' => null,
                'plazos_snapshot' => null,
            ];

        $clasificacionesEtiquetas = [];
        foreach ($evaluacion['clasificaciones'] as $clave => $activa) {
            if ($activa) {
                $clasificacionesEtiquetas[] = EtiquetasResguardoPdv::antiguedades()[$clave] ?? $clave;
            }
        }

        $retiro = SerializadorRetiroPedidoResguardoPdv::desdeResguardo($resguardo);

        return [
            'id' => $resguardo->id,
            'version' => (int) $resguardo->version,
            'estado' => $resguardo->estado,
            'estado_etiqueta' => EtiquetasResguardoPdv::etiquetaEstado($resguardo->estado),
            'pedido_bma_id' => $resguardo->pedido_bma_id,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'envia_a_otra_persona' => $retiro['envia_a_otra_persona'],
            'envia_otra_persona' => $retiro['envia_otra_persona'],
            'etiqueta_retiro' => $retiro['etiqueta_retiro'],
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_recibida' => EstadoRecepcionResguardoPdv::cantidadRecibida($resguardo),
            'cantidad_bultos_pendiente' => EstadoRecepcionResguardoPdv::cantidadPendiente($resguardo),
            'admite_recepcion' => EstadoRecepcionResguardoPdv::admiteRecepcion($resguardo),
            'admite_pasar_a_recepcion' => EstadoResguardoPdv::admitePasarARecepcion($resguardo),
            'recepcion_completa' => EstadoRecepcionResguardoPdv::recepcionCompleta($resguardo),
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            'vencido_repuesto_at' => $resguardo->vencido_repuesto_at?->toIso8601String(),
            'entrega_completada_at' => $resguardo->entrega_completada_at?->toIso8601String(),
            'devolucion_confirmada_at' => $resguardo->devolucion_confirmada_at?->toIso8601String(),
            'entrega_bloqueada' => $resguardo->entrega_bloqueada,
            'cancelacion_recibida' => $resguardo->eventos()
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA)
                ->exists(),
            'clasificaciones' => $evaluacion['clasificaciones'],
            'clasificaciones_etiquetas' => $clasificacionesEtiquetas,
            'fecha_limite_custodia' => $evaluacion['fecha_limite_custodia'],
            'fecha_limite_rezago' => $evaluacion['fecha_limite_rezago'],
            'antiguedad_configurada' => $antiguedadConfigurada,
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
            'pedido_revision' => SerializadorPedidoRevisionResguardoPdv::desdePedido($resguardo->pedido),
            'bultos' => $resguardo->bultos->map(fn ($bulto) => [
                'id' => $bulto->id,
                'folio' => $bulto->folio,
                'codigo_etiqueta' => $bulto->codigo_etiqueta,
                'tipo' => $bulto->tipo,
                'estado' => $bulto->estado,
                'recepcion_at' => $bulto->recepcion_at?->toIso8601String(),
                'entrega_at' => $bulto->entrega_at?->toIso8601String(),
            ])->values()->all(),
            'incidencias' => $resguardo->incidencias
                ->map(fn ($incidencia) => SerializadorIncidenciaResguardoPdv::incidencia($incidencia))
                ->values()
                ->all(),
            'bultos_empaque_cedis' => SerializadorBultosEmpaqueCedisPdv::desdePedido($resguardo->pedido),
            'registro_manual' => SerializadorRegistroManualResguardoPdv::desdeResguardo($resguardo),
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

    private function antiguedadConfigurada(): bool
    {
        $global = $this->plazos->obtenerGlobal();

        return $global !== null && $global['activo'];
    }
}
