<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaFormularioConfirmacionCustodiaPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarCantidadBultosEsperadaResguardoPdvService $sincronizarCantidadBultos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_CUSTODIA);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        if ((int) $resguardo->cantidad_bultos_esperada < 1) {
            $resguardo = $this->sincronizarCantidadBultos->ejecutar($resguardo);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision',
            'bultos' => fn ($q) => $q->orderBy('folio')->orderBy('id'),
        ]);

        $almacenes = Almacen::query()
            ->where('sucursal_id', $resguardo->sucursal_id)
            ->where('activo', true)
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn (Almacen $almacen) => [
                'id' => $almacen->id,
                'codigo' => $almacen->codigo,
                'nombre' => $almacen->nombre,
            ])
            ->values()
            ->all();

        return [
            'resguardo' => $this->serializarResguardo($resguardo),
            'almacenes' => $almacenes,
            'catalogos' => [
                'estados' => EtiquetasResguardoPdv::estados(),
            ],
            'admite_confirmacion_custodia' => EstadoResguardoPdv::admiteConfirmacionCustodia($resguardo),
            'motivo_no_confirmacion_custodia' => EstadoResguardoPdv::motivoNoConfirmacionCustodia($resguardo),
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
            'version' => (int) $resguardo->version,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'cantidad_bultos_recibida' => EstadoResguardoPdv::cantidadRecibidaGerente($resguardo),
            'cantidad_bultos_en_custodia' => EstadoResguardoPdv::cantidadEnCustodia($resguardo),
            'cantidad_bultos_pendiente_custodia' => EstadoResguardoPdv::cantidadPendienteCustodia($resguardo),
            'custodia_completa' => EstadoResguardoPdv::custodiaCompleta($resguardo),
            'bultos_pendientes_custodia' => $resguardo->bultos
                ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE)
                ->map(fn (ResguardoPdvBulto $bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'recepcion_at' => $bulto->recepcion_at?->toIso8601String(),
                ])->values()->all(),
            'bultos_en_custodia' => $resguardo->bultos
                ->filter(fn (ResguardoPdvBulto $bulto) => ResguardoPdvBulto::estaEnCustodiaOperativa($bulto->estado))
                ->map(fn (ResguardoPdvBulto $bulto) => [
                    'id' => $bulto->id,
                    'folio' => $bulto->folio,
                    'tipo' => $bulto->tipo,
                    'custodia_at' => $bulto->custodia_at?->toIso8601String(),
                ])->values()->all(),
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
        ];
    }
}
