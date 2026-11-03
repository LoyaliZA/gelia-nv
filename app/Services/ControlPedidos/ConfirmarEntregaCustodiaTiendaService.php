<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\User;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Entrega desde el resguardo ya recibido. Reutiliza el registro de entrega de PDV. */
final class ConfirmarEntregaCustodiaTiendaService
{
    public function __construct(
        private RegistrarEntregaResguardoPdvService $entregaPdv,
        private CompromisoInventarioTienda $inventario,
        private SincronizarCumplimientoDesdeResguardoPdvService $cumplimiento,
    ) {}

    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        string $receptor,
        UploadedFile $foto,
    ): PedidoBmaTareaPreparacion {
        $receptor = trim($receptor);
        if ($receptor === '') {
            throw ValidationException::withMessages(['receptor' => 'Indique quién recibe.']);
        }

        return DB::transaction(function () use ($tarea, $usuario, $receptor, $foto) {
            $tarea->loadMissing(['pedido', 'cumplimientoFisico']);
            $cumplimiento = $tarea->cumplimientoFisico;
            $resguardo = $cumplimiento?->resguardo_pdv_id
                ? ResguardoPdv::query()->find($cumplimiento->resguardo_pdv_id)
                : null;
            if (! $resguardo || $resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
                throw ValidationException::withMessages([
                    'custodia' => 'Primero confirme la recepción. Autorizar o pagar no entrega la mercancía.',
                ]);
            }
            if ($cumplimiento->estado !== PedidoBmaCumplimientoFisico::ESTADO_LISTA_PARA_SALIDA) {
                throw ValidationException::withMessages([
                    'estado' => 'La custodia todavía no está lista para entregar.',
                ]);
            }

            $entregado = $this->entregaPdv->ejecutar(
                $resguardo,
                $usuario,
                (int) $resguardo->version,
                'entrega-custodia:'.$resguardo->id,
                ResguardoPdvEntrega::RELACION_TITULAR,
                $receptor,
                RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA,
                $foto,
                'Entrega en sucursal con fotografía.',
                [$foto]
            );

            $entrega = $entregado->entregas()->latest('id')->first();
            if ($entrega) {
                $this->cumplimiento->reflejarEntrega($entregado, $entrega, $usuario->id);
            }
            $this->inventario->entregarPedido($tarea->pedido);

            $pedido = $tarea->pedido->fresh();
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json['entrega_custodia'] = [
                'estado' => 'entregada',
                'receptor' => $receptor,
                'resguardo_id' => $entregado->id,
                'entregada_at' => now()->toIso8601String(),
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);

            return $tarea->fresh(['cumplimientoFisico', 'pedido']);
        });
    }
}
