<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\PeriodoNoAbiertoException;
use App\Services\Escalonamiento\Excepciones\VinculoDevolucionException;
use Illuminate\Support\Facades\DB;

class RevertirAplicacionDevolucion
{
    public function __construct(private RegistrarMovimiento $registrar) {}

    public function revertir(
        EscalonamientoAplicacionDevolucion $aplicacion,
        ?User $usuario,
        string $estadoDocumento = 'pendiente_de_vinculacion',
    ): EscalonamientoAplicacionDevolucion {
        if (! in_array($estadoDocumento, ['pendiente_de_vinculacion', 'cancelado'], true)) {
            throw new VinculoDevolucionException('El estado de la devolución al revertir no es válido.');
        }

        return DB::transaction(function () use ($aplicacion, $usuario, $estadoDocumento) {
            $aplicacion = EscalonamientoAplicacionDevolucion::query()->lockForUpdate()->findOrFail($aplicacion->id);
            if (! $aplicacion->estaActiva()) {
                throw new VinculoDevolucionException('Esa aplicación ya no está activa. El descuento no se revirtió otra vez.');
            }

            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($aplicacion->escalonamiento_periodo_id);
            if (! $periodo->permiteEscrituraMovimientos()) {
                throw new PeriodoNoAbiertoException('El período no admite nuevos movimientos.');
            }

            $devolucion = DocumentoVenta::query()->lockForUpdate()->findOrFail($aplicacion->documento_devolucion_id);
            $this->registrar->aplicarEfectoEnDocumento(
                $devolucion,
                '0.00',
                'devolucion_revertida',
                $aplicacion->id,
            );

            $aplicacion->estado = $estadoDocumento === 'cancelado'
                ? EscalonamientoAplicacionDevolucion::ESTADO_CANCELADA
                : EscalonamientoAplicacionDevolucion::ESTADO_REVERTIDA;
            $aplicacion->save();

            $devolucion->estado = $estadoDocumento;
            $devolucion->save();

            if ($estadoDocumento === 'pendiente_de_vinculacion') {
                $this->reabrirPendiente($periodo, $devolucion, $usuario);
            }

            return $aplicacion;
        });
    }

    private function reabrirPendiente(EscalonamientoPeriodo $periodo, DocumentoVenta $devolucion, ?User $usuario): void
    {
        $existe = EscalonamientoIncidencia::query()
            ->where('documento_venta_id', $devolucion->id)
            ->where('codigo', 'pendiente_vinculacion')
            ->where('estado', 'abierta')
            ->exists();
        if ($existe) {
            return;
        }

        EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'cliente_id' => $devolucion->cliente_id,
            'documento_venta_id' => $devolucion->id,
            'gravedad' => 'aviso',
            'codigo' => 'pendiente_vinculacion',
            'motivo' => 'Devolución '.$devolucion->folio.' sin descuento. El vínculo se revirtió y falta confirmar otra vez la compra nueva.',
            'estado' => 'abierta',
            'user_id' => $usuario?->id,
        ]);
    }
}
