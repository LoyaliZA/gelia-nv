<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use App\Support\PuntoVenta\Resguardos\SerializadorIncidenciaResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaDetalleResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
        private readonly PlazosCustodiaResguardoPdvConfig $plazos,
        private readonly ConsultaAuditoriaResguardoPdvService $auditoria,
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
            'incidencias' => fn ($q) => $q
                ->with([
                    'evidencias',
                    'reportadoPor:id,username',
                    'autorizadoPor:id,username',
                ])
                ->orderByDesc('reportado_at')
                ->orderByDesc('id'),
        ]);

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

        return [
            'id' => $resguardo->id,
            'version' => (int) $resguardo->version,
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
