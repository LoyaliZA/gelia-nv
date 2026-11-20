<?php

namespace App\Services\ControlPedidos\Nacional;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\ActualizarCostosCajasPedidoBmaService;
use App\Services\ControlPedidos\CalcularSeguroPedidoService;
use App\Services\ControlPedidos\Direcciones\InvalidarGuiaPorCambioDireccion;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Nacional\CapturaNacional;
use App\Support\ControlPedidos\RevisionCapturaPedido;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Cambio de paquetería de la captura nacional. La cotización sale del servidor. */
class CambiarPaqueteriaNacional
{
    public function __construct(
        private InvalidarGuiaPorCambioDireccion $invalidarGuia,
        private ActualizarCostosCajasPedidoBmaService $costos,
        private CalcularSeguroPedidoService $seguro,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    /** @param  array<string, mixed>  $contexto */
    public function ejecutar(PedidoBma $pedido, array $contexto): PedidoBma
    {
        if (! CapturaNacional::es($pedido)) {
            throw new RuntimeException('Este pedido no usa la captura nacional.');
        }

        return DB::transaction(function () use ($pedido, $contexto) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $solicitudId = (string) ($contexto['solicitud_id'] ?? '');
            if ($solicitudId !== '' && ($json['cambio_paqueteria']['solicitud_id'] ?? null) === $solicitudId) {
                return $pedido->fresh(['cajas', 'paqueteria', 'estatus', 'zona']);
            }

            RevisionCapturaPedido::comprobar($pedido, $contexto);
            $pedido->loadMissing(['estatus', 'cajas', 'documentos']);
            if ($this->yaSalio($pedido)) {
                throw new RuntimeException('No se cambia la paquetería después de la salida.');
            }

            $nueva = CatalogoPaqueteriaPedido::query()
                ->whereKey((int) ($contexto['catalogo_paqueteria_id'] ?? 0))
                ->where('activo', true)
                ->first();
            if (! $nueva) {
                throw new InvalidArgumentException('Seleccione una paquetería.');
            }

            $lineas = is_array($contexto['lineas'] ?? null) ? $contexto['lineas'] : [];
            $this->rechazarCostoCero($pedido, $lineas);

            $usuarioId = (int) $contexto['usuario_id'];
            if ($this->tieneGuiaVigente($pedido)) {
                $this->invalidarGuia->ejecutar($pedido, $usuarioId, 'Cambio de paquetería');
                $pedido = $pedido->fresh(['estatus', 'cajas', 'documentos']);
            }

            $aplica = array_key_exists('aplica_seguro', $contexto)
                ? (bool) $contexto['aplica_seguro']
                : (bool) $pedido->aplica_seguro;
            $pedido->update([
                'catalogo_paqueteria_id' => $nueva->id,
                'aplica_seguro' => $aplica,
            ]);

            $reabierta = false;
            if ($pedido->pago_validado_at) {
                $pedido->update(['pago_validado_at' => null, 'pago_validado_por_id' => null]);
                $pedido = $pedido->fresh(['cajas', 'zona', 'estatus']);
                $reabierta = true;
            }
            if ($lineas !== [] && ! $nueva->esPendienteConfirmacion()) {
                $pedido = $this->cotizar($pedido->fresh(['cajas', 'zona', 'estatus']), $lineas, $nueva, $aplica, $usuarioId);
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            if ($reabierta) {
                $json['cambio_paqueteria_cobertura'] = 'reabierta';
            }
            $json['cambio_paqueteria'] = [
                'solicitud_id' => $solicitudId,
                'catalogo_paqueteria_id' => $nueva->id,
            ];
            $pedido->update(['solicitud_preparacion_json' => $json]);
            $pedido->increment('revision_registro');
            $estatusId = $pedido->catalogo_estatus_pedido_id;
            $this->historial->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusId,
                $estatusId,
                'Paquetería actualizada a '.$nueva->nombre.'. La cobertura queda pendiente de revisión.',
                AccionesHistorialPedidoBma::COSTOS_ENVIO
            );

            return $pedido->fresh(['cajas', 'paqueteria', 'estatus', 'zona']);
        });
    }

    private function yaSalio(PedidoBma $pedido): bool
    {
        $fase = $pedido->estatus?->fase_ciclo;
        if (in_array($fase, [CatalogoEstatusPedido::FASE_ENVIADO, CatalogoEstatusPedido::FASE_ENTREGADO], true)) {
            return true;
        }

        return $pedido->cajas->contains(fn (PedidoBmaCaja $caja) => $caja->estaActiva() && $caja->estaRecolectada());
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function rechazarCostoCero(PedidoBma $pedido, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $caja = $pedido->cajas->firstWhere('uuid_operativo', (string) ($linea['uuid_operativo'] ?? ''));
            $anterior = round((float) ($caja?->costo_envio ?? 0), 2);
            $nuevo = round((float) ($linea['costo_envio'] ?? 0), 2);
            if ($caja && $anterior > 0 && $nuevo <= 0) {
                throw new RuntimeException('Un costo mayor que cero no se sustituye por cero.');
            }
        }
    }

    private function tieneGuiaVigente(PedidoBma $pedido): bool
    {
        if (filled($pedido->numero_rastreo)) {
            return true;
        }
        if ($pedido->documentos->contains(fn (PedidoBmaDocumento $doc) => $doc->tipo === PedidoBmaDocumento::TIPO_GUIA && $doc->activo)) {
            return true;
        }

        return $pedido->cajas->contains(
            fn (PedidoBmaCaja $caja) => $caja->estaActiva() && ! $caja->estaRecolectada() && filled($caja->numero_rastreo)
        );
    }

    /** @param  list<array<string, mixed>>  $lineas */
    private function cotizar(PedidoBma $pedido, array $lineas, CatalogoPaqueteriaPedido $paqueteria, bool $aplica, int $usuarioId): PedidoBma
    {
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva());
        if ($aplica && $activas->count() > 1) {
            throw new RuntimeException('El seguro de varias cajas queda bloqueado hasta asignar cada remisión a una caja.');
        }

        $flete = 0.0;
        foreach ($lineas as $linea) {
            $flete += round((float) ($linea['costo_envio'] ?? 0), 2);
        }
        $flete = round($flete, 2);
        $montoSeguro = $aplica
            ? $this->seguro->calcularCosto($paqueteria->nombre, $flete, (float) ($pedido->total_mercancia ?? 0))
            : 0.0;
        $preparadas = [];
        foreach (array_values($lineas) as $indice => $linea) {
            $preparadas[] = [
                'uuid_operativo' => (string) ($linea['uuid_operativo'] ?? ''),
                'costo_envio' => $linea['costo_envio'],
                'costo_seguro' => $indice === 0 ? $montoSeguro : 0,
                'costo_adicional' => 0,
            ];
        }
        $this->costos->ejecutar($pedido, $preparadas, $usuarioId);

        return $pedido->fresh(['cajas', 'zona', 'estatus']);
    }
}
