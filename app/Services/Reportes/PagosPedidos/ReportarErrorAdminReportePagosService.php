<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\Solicitudes\ResolverDestinatariosAlertaSolicitudService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\Reportes\AdminEstadoReportePagosPedidos;
use App\Notifications\AlertaPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class ReportarErrorAdminReportePagosService
{
    public const ALCANCE_EXHIBICION = 'exhibicion';

    public const ALCANCE_PEDIDO = 'pedido';

    public function __construct(
        private ResolverAccesoCierreReportePagosService $acceso,
        private RegistrarHistorialPedidoService $historial,
        private ResolverDestinatariosAlertaSolicitudService $destinatarios,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        User $usuario,
        int|string $cierreId,
        string $alcance,
        string $comentario,
        UploadedFile $evidencia,
        int|string|null $itemId = null,
    ): array {
        $comentario = trim($comentario);
        if (mb_strlen($comentario) < 10) {
            throw new InvalidArgumentException('El comentario debe tener al menos 10 caracteres.');
        }

        $cierre = $this->acceso->cierre($usuario, $cierreId);
        $cierre->load(['items', 'pedido.estatus', 'vendedor']);

        if ($alcance === self::ALCANCE_EXHIBICION) {
            if ($itemId === null) {
                throw new InvalidArgumentException('Debe indicar la exhibición.');
            }

            return $this->reportarExhibicion($usuario, $cierre, (int) $itemId, $comentario, $evidencia);
        }

        if ($alcance !== self::ALCANCE_PEDIDO) {
            throw new InvalidArgumentException('Alcance no válido.');
        }

        return $this->reportarPedido($usuario, $cierre, $comentario, $evidencia);
    }

    /** @return array<string, mixed> */
    private function reportarExhibicion(
        User $usuario,
        PedidoBmaCierrePago $cierre,
        int $itemId,
        string $comentario,
        UploadedFile $evidencia,
    ): array {
        $item = $cierre->items->firstWhere('id', $itemId)
            ?? PedidoBmaCierrePagoItem::query()->where('pedido_bma_cierre_pago_id', $cierre->id)->findOrFail($itemId);

        if ($item->admin_estado === AdminEstadoReportePagosPedidos::CON_ERROR) {
            throw new InvalidArgumentException('Esta exhibición ya tiene un error reportado.');
        }

        if ($cierre->tieneErrorAdminPedido()) {
            throw new InvalidArgumentException('El pedido ya tiene un error administrativo reportado.');
        }

        return DB::transaction(function () use ($usuario, $cierre, $item, $comentario, $evidencia) {
            $ev = $this->guardarEvidencia($evidencia);
            $item->update([
                'admin_estado' => AdminEstadoReportePagosPedidos::CON_ERROR,
                'admin_error_comentario' => $comentario,
                'admin_error_evidencia_ruta' => $ev['ruta'],
                'admin_error_evidencia_nombre' => $ev['nombre'],
                'admin_error_reportado_por_id' => $usuario->id,
                'admin_error_reportado_at' => now(),
            ]);

            $detalle = sprintf(
                'Error en exhibición #%d del pedido %s. %s',
                $item->numero_exhibicion,
                $cierre->folio_snapshot ?: '—',
                $comentario,
            );

            $this->registrarBitacora($cierre, $usuario, $detalle, $ev);
            $this->notificar($cierre, $usuario, $detalle);

            $item = $item->fresh(['adminErrorReportadoPor', 'cierre.items.adminErrorReportadoPor', 'cierre.adminPedidoErrorReportadoPor']);

            return [
                'item' => AdminEstadoReportePagosPedidos::payloadItem($item),
                'cierre' => AdminEstadoReportePagosPedidos::payloadCierre($item->cierre),
            ];
        });
    }

    /** @return array<string, mixed> */
    private function reportarPedido(
        User $usuario,
        PedidoBmaCierrePago $cierre,
        string $comentario,
        UploadedFile $evidencia,
    ): array {
        if ($cierre->tieneErrorAdminPedido()) {
            throw new InvalidArgumentException('El pedido ya tiene un error administrativo reportado.');
        }

        return DB::transaction(function () use ($usuario, $cierre, $comentario, $evidencia) {
            $ev = $this->guardarEvidencia($evidencia);
            $cierre->update([
                'admin_pedido_error_comentario' => $comentario,
                'admin_pedido_error_evidencia_ruta' => $ev['ruta'],
                'admin_pedido_error_evidencia_nombre' => $ev['nombre'],
                'admin_pedido_error_reportado_por_id' => $usuario->id,
                'admin_pedido_error_reportado_at' => now(),
            ]);

            $detalle = sprintf(
                'Error reportado en pedido %s (Administración). %s',
                $cierre->folio_snapshot ?: '—',
                $comentario,
            );

            $this->registrarBitacora($cierre, $usuario, $detalle, $ev);
            $this->notificar($cierre, $usuario, $detalle);

            $cierre = $cierre->fresh(['items.adminErrorReportadoPor', 'adminPedidoErrorReportadoPor']);

            return [
                'cierre' => AdminEstadoReportePagosPedidos::payloadCierre($cierre),
                'items' => $cierre->items->map(fn ($item) => AdminEstadoReportePagosPedidos::payloadItem($item))->values()->all(),
            ];
        });
    }

    /** @return array{ruta: string, nombre: string} */
    private function guardarEvidencia(UploadedFile $archivo): array
    {
        $nombre = $archivo->getClientOriginalName();
        $ruta = $archivo->store('reportes_pagos_admin_errores', 'public');

        return ['ruta' => $ruta, 'nombre' => $nombre];
    }

    /** @param  array{ruta: string, nombre: string}  $evidencia */
    private function registrarBitacora(
        PedidoBmaCierrePago $cierre,
        User $usuario,
        string $comentario,
        array $evidencia,
    ): void {
        $pedido = $cierre->pedido;
        if (! $pedido?->catalogo_estatus_pedido_id) {
            return;
        }

        $this->historial->ejecutar(
            $pedido->id,
            $usuario->id,
            $pedido->catalogo_estatus_pedido_id,
            $pedido->catalogo_estatus_pedido_id,
            $comentario,
            AccionesHistorialPedidoBma::ERROR_ADMIN_REPORTE_PAGO,
            $evidencia,
        );
    }

    private function notificar(PedidoBmaCierrePago $cierre, User $usuario, string $mensaje): void
    {
        $pedido = $cierre->pedido;
        if (! $pedido) {
            return;
        }

        $destinatarios = $this->destinatarios->conVendedorOpcional(
            $cierre->departamento_id,
            ['control_pedidos.auditar'],
            true,
            $cierre->vendedor,
            $usuario->id,
        );

        if ($destinatarios->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($destinatarios, $pedido, $mensaje, $usuario) {
            Notification::send(
                $destinatarios,
                new AlertaPedidoBma(
                    $pedido,
                    'reporte_pagos_error_admin',
                    $mensaje,
                    [
                        'actor_id' => $usuario->id,
                        'actor_nombre' => $usuario->name,
                        'modulo_origen' => 'reportes_pagos_pedidos',
                    ],
                ),
            );
        });
    }
}
