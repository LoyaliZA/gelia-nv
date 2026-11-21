<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Exceptions\ControlPedidos\CaratulaMunicipalException;
use App\Models\ClienteConsignatario;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaReferencia;
use App\Models\User;
use App\Services\ControlPedidos\GenerarCaratulaPedidoService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carátula del envío municipal generada con los datos del pedido (consignatario, municipio y leyenda de cobro).
 * No hay tarea de preparación ni captura adicional. La usa la sucursal que recibe el envío.
 */
class GenerarCaratulaMunicipalService
{
    public function __construct(
        private GenerarCaratulaPedidoService $caratulas,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function ejecutar(PedidoBma $pedido, User $usuario, ?string $motivoRegeneracion = null): PedidoBmaCaratula
    {
        $motivoRegeneracion = $motivoRegeneracion !== null ? trim($motivoRegeneracion) : null;
        if ($motivoRegeneracion === '') {
            $motivoRegeneracion = null;
        }
        if (! $usuario->can('control_pedidos.tienda.generar_caratula')
            || ($motivoRegeneracion !== null && ! $usuario->can('control_pedidos.tienda.regenerar_caratula'))) {
            throw new CaratulaMunicipalException('No tiene permiso para generar carátula.');
        }

        return DB::transaction(function () use ($pedido, $usuario, $motivoRegeneracion) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing(['estatus', 'paqueteria']);

            if (! RecorridoFisicoMunicipal::aplica($pedido)) {
                throw ValidationException::withMessages(['pedido' => 'Este pedido no usa el recorrido físico municipal.']);
            }
            if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === 'CANCELADO') {
                throw ValidationException::withMessages(['pedido' => 'El pedido está cancelado.']);
            }
            if (! $pedido->sucursal_destino_id || ! $usuario->idsSucursalesOperables()->contains((int) $pedido->sucursal_destino_id)) {
                throw new CaratulaMunicipalException('Solo el personal de la sucursal que recibe el envío genera la carátula.');
            }

            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            [$nombre, $telefono] = $this->consignatarioVigente($pedido, $json);
            if (! $pedido->paqueteria) {
                throw ValidationException::withMessages(['paqueteria' => 'El pedido no tiene transporte definido.']);
            }
            // La captura del recorrido nuevo no escribe municipio_destino; la terminal es el transporte del pedido.
            $municipio = trim((string) ($json['municipio_destino'] ?? ''));
            if ($municipio === '') {
                $municipio = (string) $pedido->paqueteria->nombre;
            }
            $cobro = $this->modalidadCobro($pedido, $json);

            $base = PedidoBmaCaratula::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereNull('pedido_bma_tarea_preparacion_id');
            $vigente = (clone $base)
                ->whereIn('estado', [PedidoBmaCaratula::ESTADO_GENERADA, PedidoBmaCaratula::ESTADO_COLOCADA])
                ->orderByDesc('version')->first();
            if ($vigente?->estado === PedidoBmaCaratula::ESTADO_COLOCADA) {
                throw ValidationException::withMessages(['caratula' => 'No se puede sobrescribir una carátula ya colocada.']);
            }
            if ($vigente && $motivoRegeneracion === null) {
                throw ValidationException::withMessages(['motivo_regeneracion' => 'Para regenerar indique el motivo.']);
            }
            $vigente?->update(['estado' => PedidoBmaCaratula::ESTADO_INVALIDADA]);

            // Versión por pedido: el índice único por tarea no cubre filas sin tarea; el bloqueo del pedido la protege.
            $version = (int) ((clone $base)->max('version') ?? 0) + 1;
            $folio = PedidoBmaReferencia::visible($pedido);
            $snapshot = [
                'destinatario_nombre' => $nombre,
                'destinatario_telefono' => $telefono,
                'municipio_destino' => $municipio,
                'direccion_referencia' => null,
                'transporte' => $pedido->paqueteria->nombre,
                'modalidad_cobro' => $cobro,
                'folio' => $folio['folio'] !== '' ? $folio['folio'] : (string) $pedido->id,
                'folio_etiqueta' => $folio['etiqueta'],
                'folio_interno' => $pedido->folio,
                'version' => $version,
                'fecha' => now()->format('d/m/Y H:i'),
            ];
            $pdf = $this->caratulas->guardarPdf('pedido-'.$pedido->id, $pedido->paqueteria->plantilla_caratula ?: 'control_pedidos.caratula', $snapshot);

            $caratula = PedidoBmaCaratula::query()->create([
                'pedido_bma_tarea_preparacion_id' => null,
                'pedido_bma_id' => $pedido->id,
                'version' => $version,
                'destinatario_nombre' => $nombre,
                'destinatario_telefono' => $telefono,
                'municipio_destino' => $municipio,
                'direccion_referencia' => null,
                'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
                // La identificación del expediente es un documento privado: no se imprime ni se vincula a la carátula (spec 10).
                'documento_identificacion_id' => null,
                'modalidad_cobro' => $cobro,
                'ruta_pdf' => $pdf['ruta'],
                'hash_sha256' => $pdf['hash'],
                'estado' => PedidoBmaCaratula::ESTADO_GENERADA,
                'generada_por_id' => $usuario->id,
                'generada_at' => now(),
                'motivo_regeneracion' => $motivoRegeneracion,
            ]);

            if ($pedido->estatus) {
                $this->historial->ejecutar(
                    $pedido->id, $usuario->id, $pedido->estatus->id, $pedido->estatus->id,
                    $motivoRegeneracion ? "Carátula v{$version} regenerada: {$motivoRegeneracion}" : "Carátula v{$version} generada.",
                    $motivoRegeneracion ? AccionesHistorialPedidoBma::CARATULA_REGENERADA : AccionesHistorialPedidoBma::CARATULA_GENERADA,
                );
            }

            return $caratula->fresh(['paqueteria']);
        });
    }

    /** @param array<string, mixed> $json @return array{0: string, 1: string} */
    private function consignatarioVigente(PedidoBma $pedido, array $json): array
    {
        $id = (int) ($json['expediente']['consignatario_id'] ?? 0);
        $consignatario = $id > 0 ? ClienteConsignatario::query()->with('versionVigente')->find($id) : null;
        $version = $consignatario?->versionVigente;
        if (! $consignatario || ! $consignatario->activo || (int) $consignatario->cliente_id !== (int) $pedido->cliente_id || ! $version || ! $version->vigente) {
            throw ValidationException::withMessages(['consignatario_id' => 'El pedido no tiene un consignatario vigente.']);
        }

        return [(string) $version->nombre, (string) $version->telefono];
    }

    /** @param array<string, mixed> $json */
    private function modalidadCobro(PedidoBma $pedido, array $json): string
    {
        $leyenda = $json['finanzas_municipales']['leyenda_terminal'] ?? null;
        if ($leyenda === null) {
            $leyenda = $pedido->envio_por_cobrar ? PedidoBmaCaratula::COBRO_POR_COBRAR : PedidoBmaCaratula::COBRO_PAGADO;
        }

        return $leyenda === PedidoBmaCaratula::COBRO_POR_COBRAR ? PedidoBmaCaratula::COBRO_POR_COBRAR : PedidoBmaCaratula::COBRO_PAGADO;
    }
}
