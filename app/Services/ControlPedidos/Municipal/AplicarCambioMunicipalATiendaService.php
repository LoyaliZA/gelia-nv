<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\Municipal\CambioMunicipalATienda;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\PoliticaResguardoEnvio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verificación del cambio a recolección en sucursal por el área que tiene el paquete: CEDIS si aún está allí, la sucursal
 * si ya está en custodia. Invalida la documentación de transporte municipal conservando su historial y conserva compra,
 * remisión, pagos, empaque y custodia. No crea traslados ni recepciones: si el paquete ya está en la sucursal, el cambio
 * aprovecha esa ubicación; si sigue en CEDIS, el traslado a la sucursal lo registra CEDIS con su salida de siempre.
 */
final class AplicarCambioMunicipalATiendaService
{
    public function __construct(private RegistrarHistorialPedidoService $historial) {}

    public function ejecutar(PedidoBma $pedido, User $usuario): PedidoBma
    {
        return DB::transaction(function () use ($pedido, $usuario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $datos = CambioMunicipalATienda::datos($pedido);
            if ($datos === null) {
                throw ValidationException::withMessages(['cambio' => 'Este envío no tiene un cambio a recolección en sucursal.']);
            }
            if (($datos['estado'] ?? null) === CambioMunicipalATienda::ESTADO_APLICADO) {
                return $pedido;
            }
            if ($pedido->cancelado_at) {
                throw ValidationException::withMessages(['cambio' => 'El pedido está cancelado.']);
            }
            if (RecorridoFisicoMunicipal::envioYaSalioParaAjustes($pedido)) {
                throw ValidationException::withMessages(['cambio' => RecorridoFisicoMunicipal::MENSAJE_SALIO_A_TERMINAL]);
            }
            if ($datos['requiere_autorizacion_gerencia'] ?? false) {
                throw ValidationException::withMessages(['cambio' => 'Otra sucursal con el traslado ya hecho exige autorización de gerencia y un nuevo traslado. Aún no se registra desde aquí.']);
            }

            $ubicacion = IntegrarComplementoMunicipalService::ubicacion($pedido);
            if ($ubicacion === IntegrarComplementoMunicipalService::UBICACION_TRANSITO) {
                throw ValidationException::withMessages(['ubicacion' => 'El paquete va en traslado a la sucursal. Se verifica cuando se reciba.']);
            }
            $mecanismo = $ubicacion === IntegrarComplementoMunicipalService::UBICACION_PDV
                ? PoliticaResguardoEnvio::MECANISMO_LOCAL
                : PoliticaResguardoEnvio::MECANISMO_RESGUARDO;
            if (! PoliticaResguardoEnvio::puedeConfirmarIntegracionFisica($usuario, $pedido, $mecanismo)) {
                throw ValidationException::withMessages(['permiso' => $ubicacion === IntegrarComplementoMunicipalService::UBICACION_PDV
                    ? 'Solo el personal de la sucursal donde está el paquete verifica el cambio.'
                    : 'Solo el área CEDIS verifica el cambio mientras el paquete está allí.']);
            }

            // Documentación de transporte municipal: se invalida, no se borra.
            $invalidadas = PedidoBmaCaratula::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
                ->update(['estado' => PedidoBmaCaratula::ESTADO_INVALIDADA]);

            $actualizaDestino = $ubicacion === IntegrarComplementoMunicipalService::UBICACION_CEDIS
                && (int) ($datos['sucursal_destino_id'] ?? 0) > 0
                && (int) $datos['sucursal_destino_id'] !== (int) $pedido->sucursal_destino_id;

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $json[CambioMunicipalATienda::CLAVE] = array_merge($datos, [
                'estado' => CambioMunicipalATienda::ESTADO_APLICADO,
                'aplicado_por_id' => $usuario->id,
                'aplicado_at' => now()->toIso8601String(),
                'ubicacion_al_aplicar' => $ubicacion,
                'caratulas_invalidadas' => $invalidadas,
            ]);
            $cambios = ['solicitud_preparacion_json' => $json];
            if ($actualizaDestino) {
                // Antes del primer traslado solo se actualiza la instrucción; la salida de CEDIS usa este destino.
                $cambios['sucursal_destino_id'] = (int) $datos['sucursal_destino_id'];
            }
            $pedido->update($cambios);

            $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $estatusId,
                $estatusId,
                'Cambio a recolección en sucursal verificado en '.($ubicacion === IntegrarComplementoMunicipalService::UBICACION_PDV ? 'la sucursal' : 'CEDIS')
                    .($invalidadas ? ". Documentación de transporte invalidada ({$invalidadas})" : '')
                    .'. No se creó ningún traslado ni se tocó el pago ni la remisión.',
                'cambio_a_tienda_aplicado'
            );

            return $pedido->fresh();
        });
    }
}
