<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Almacen;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConsultaFormularioRecepcionFisicaPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{
     *     resguardo: array<string, mixed>,
     *     almacenes: list<array{id: int, codigo: string, nombre: string}>,
     *     catalogos: array<string, mixed>,
     *     puede_recibir: bool
     * }
     */
    public function obtener(User $user, ResguardoPdv $resguardo): array
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR);

        $activaId = $this->alcance->sucursalActivaId($user);
        if ($activaId === null || (int) $resguardo->sucursal_id !== $activaId) {
            throw (new ModelNotFoundException)->setModel(ResguardoPdv::class, [$resguardo->id]);
        }

        $resguardo->load([
            'sucursal:id,nombre',
            'cliente:id,numero_cliente',
            'pedido:id,folio,folio_remision',
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
                'tipos_bulto' => EtiquetasResguardoPdv::tiposBulto(),
                'condiciones_bulto' => EtiquetasResguardoPdv::condicionesBulto(),
                'estados' => EtiquetasResguardoPdv::estados(),
            ],
            'puede_recibir' => $resguardo->estado === ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
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
            'referencia_cliente' => $this->referenciaCliente($resguardo),
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
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
