<?php

namespace App\Services\Traspasos;

use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoHorarioTraspaso;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearSolicitudTraspasoService
{
    public function __construct(
        private NotificarTraspasoService $notificar,
        private AlmacenesOrigenTraspasoService $almacenesOrigen
    ) {}

    public function ejecutar(array $datos, int $vendedorId): SolicitudTraspaso
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            $estadoPendiente = CatalogoEstadoSolicitud::where('nombre', 'Pendiente')->firstOrFail();

            $vendedor = User::with(['departamentos', 'area.departamento'])->findOrFail($vendedorId);
            $departamentoId = $vendedor->departamentos->first()?->id
                ?? $vendedor->area?->departamento_id;

            $sucursalSolicitante = $this->almacenesOrigen->resolverSucursalSolicitante($vendedor);
            $almacen = $this->almacenesOrigen->assertAlmacenPermitido($vendedor, (int) $datos['almacen_origen_id']);

            $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
            if (! $cliente) {
                throw ValidationException::withMessages([
                    'numero_cliente' => 'Debe seleccionar un cliente válido del catálogo.',
                ]);
            }

            $lineas = $datos['productos'] ?? [];
            if (count($lineas) < 1) {
                throw ValidationException::withMessages([
                    'productos' => 'Debe agregar al menos un producto.',
                ]);
            }

            $horario = CatalogoHorarioTraspaso::resolverParaHora(now()->format('H:i:s'));
            $fechaEstimada = $horario
                ? now()->startOfDay()->addDays((int) $horario->dias_para_entrega)->toDateString()
                : now()->toDateString();

            $productosDb = Producto::query()
                ->whereIn('id', collect($lineas)->pluck('producto_id'))
                ->where('activo', true)
                ->get()
                ->keyBy('id');

            $totalPiezas = 0;
            $lineasNormalizadas = [];
            foreach ($lineas as $i => $linea) {
                $producto = $productosDb->get($linea['producto_id']);
                if (! $producto) {
                    throw ValidationException::withMessages([
                        "productos.{$i}.producto_id" => 'Producto no válido o inactivo.',
                    ]);
                }
                $piezas = (int) $linea['piezas'];
                if ($piezas < 1) {
                    throw ValidationException::withMessages([
                        "productos.{$i}.piezas" => 'La cantidad debe ser al menos 1.',
                    ]);
                }
                $totalPiezas += $piezas;
                $lineasNormalizadas[] = [
                    'producto' => $producto,
                    'piezas' => $piezas,
                ];
            }

            $solicitud = SolicitudTraspaso::create([
                'folio' => SolicitudTraspaso::generarFolio(),
                'vendedor_id' => $vendedorId,
                'departamento_id' => $departamentoId,
                'sucursal_solicitante_id' => $sucursalSolicitante->id,
                'cliente_id' => $cliente->id,
                'almacen_origen_id' => $almacen->id,
                'catalogo_estado_solicitud_id' => $estadoPendiente->id,
                'catalogo_horario_traspaso_id' => $horario?->id,
                'fecha_entrega_estimada' => $fechaEstimada,
                'total_piezas' => $totalPiezas,
            ]);

            foreach ($lineasNormalizadas as $linea) {
                SolicitudTraspasoProducto::create([
                    'solicitud_traspaso_id' => $solicitud->id,
                    'producto_id' => $linea['producto']->id,
                    'sku' => $linea['producto']->sku,
                    'descripcion' => $linea['producto']->descripcion,
                    'piezas' => $linea['piezas'],
                ]);
            }

            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'usuario_id' => $vendedorId,
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $estadoPendiente->id,
                'motivo_reporte' => 'Creación de solicitud de traspaso.',
                'datos_snapshot' => [
                    'total_piezas' => $totalPiezas,
                    'lineas' => count($lineasNormalizadas),
                    'almacen_origen_id' => $almacen->id,
                    'sucursal_solicitante_id' => $sucursalSolicitante->id,
                    'fecha_entrega_estimada' => $fechaEstimada,
                ],
            ]);

            $this->notificar->nueva(
                $solicitud->load(['vendedor', 'estado', 'cliente']),
                $vendedor
            );

            return $solicitud->load([
                'vendedor',
                'estado',
                'cliente',
                'almacenOrigen',
                'horario',
                'productos',
            ]);
        });
    }
}
