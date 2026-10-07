<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\Excepciones\CapacidadRemisionException;
use App\Services\Escalonamiento\Excepciones\PeriodoNoAbiertoException;
use App\Services\Escalonamiento\Excepciones\VinculoDevolucionException;
use Illuminate\Support\Facades\DB;

class VincularDevolucionARemision
{
    public function __construct(
        private RegistrarMovimiento $registrar,
        private CapacidadRemisionVinculada $capacidad,
    ) {}

    public function vincular(
        DocumentoVenta $devolucion,
        DocumentoVenta $remision,
        string $evidencia,
        ?User $usuario,
    ): EscalonamientoAplicacionDevolucion {
        $evidencia = trim($evidencia);
        if ($evidencia === '') {
            throw new VinculoDevolucionException('La evidencia del vínculo es obligatoria.');
        }

        return DB::transaction(function () use ($devolucion, $remision, $evidencia, $usuario) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($devolucion->escalonamiento_periodo_id);
            if (! $periodo->permiteEscrituraMovimientos()) {
                throw new PeriodoNoAbiertoException('El período no admite nuevos movimientos.');
            }

            $devolucion = DocumentoVenta::query()->lockForUpdate()->findOrFail($devolucion->id);
            $remision = DocumentoVenta::query()->lockForUpdate()->findOrFail($remision->id);
            $this->validar($periodo, $devolucion, $remision);

            $importe = bcadd((string) $devolucion->total, '0', 2);
            $suma = $this->capacidad->sumaActiva($remision->id, true);
            if (! $this->capacidad->acepta((string) $remision->total, $suma, $importe)) {
                throw new CapacidadRemisionException(
                    'La remisión '.$remision->folio.' no cubre la devolución: la suma de devoluciones debe ser estrictamente menor que su total.'
                );
            }

            $resumen = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $devolucion->cliente_id)
                ->lockForUpdate()
                ->first();
            $maxAntes = $resumen?->clasificacion_mes_max_id;

            $aplicacion = EscalonamientoAplicacionDevolucion::create([
                'escalonamiento_periodo_id' => $periodo->id,
                'documento_devolucion_id' => $devolucion->id,
                'documento_venta_original_id' => $this->ventaOriginalId($devolucion, $remision->id),
                'documento_remision_vinculada_id' => $remision->id,
                'importe' => $importe,
                'estado' => EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA,
                'evidencia' => $evidencia,
                'user_id' => $usuario?->id,
            ]);

            try {
                $this->registrar->aplicarEfectoEnDocumento(
                    $devolucion,
                    bcmul($importe, '-1', 2),
                    'devolucion_aplicada',
                    $aplicacion->id,
                );
            } catch (AcumuladoNegativoException $excepcion) {
                throw new AcumuladoNegativoException(
                    'La devolución dejaría el acumulado del período en negativo. No se aplicó.',
                    previous: $excepcion,
                );
            }

            $devolucion->estado = 'aplicada';
            $devolucion->save();
            $this->cerrarPendiente($devolucion, $usuario);
            $this->evaluarIrregularidad($periodo, $devolucion, $maxAntes, $usuario);

            return $aplicacion;
        });
    }

    private function validar(EscalonamientoPeriodo $periodo, DocumentoVenta $devolucion, DocumentoVenta $remision): void
    {
        if ($devolucion->tipo !== 'devolucion' || $devolucion->estado !== 'pendiente_de_vinculacion') {
            throw new VinculoDevolucionException('La devolución no está pendiente de vinculación.');
        }
        if ((int) $devolucion->escalonamiento_periodo_id !== (int) $periodo->id) {
            throw new VinculoDevolucionException('La devolución no pertenece al período abierto.');
        }
        if ($remision->tipo !== 'remision' || $remision->estado !== 'activo') {
            throw new VinculoDevolucionException('La remisión vinculada debe estar activa.');
        }
        if ((int) $remision->escalonamiento_periodo_id !== (int) $periodo->id) {
            throw new VinculoDevolucionException('La remisión vinculada debe ser del mismo período que la devolución.');
        }
        if ((int) $remision->cliente_id !== (int) $devolucion->cliente_id) {
            throw new VinculoDevolucionException('La remisión vinculada es de otro cliente.');
        }
        if (strtoupper((string) $remision->moneda) !== strtoupper((string) $devolucion->moneda)) {
            throw new VinculoDevolucionException('La moneda de la remisión no coincide con la devolución.');
        }
        if ((int) $remision->id === (int) $devolucion->id) {
            throw new VinculoDevolucionException('La devolución no puede vincularse a sí misma.');
        }

        $folioOriginal = trim((string) $devolucion->remision_original);
        if ($folioOriginal !== '' && strcasecmp($folioOriginal, (string) $remision->folio) === 0) {
            // ponytail: el folio de la venta original se compara sin serie ni sucursal; si el folio se reutiliza en el mes, esa remisión no se ofrece como compra nueva.
            throw new VinculoDevolucionException('La venta original no puede usarse como la compra nueva que absorbe la devolución.');
        }

        $yaActiva = EscalonamientoAplicacionDevolucion::query()
            ->where('documento_devolucion_id', $devolucion->id)
            ->where('estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->lockForUpdate()
            ->exists();
        if ($yaActiva) {
            throw new VinculoDevolucionException('La devolución ya tiene un vínculo activo.');
        }

        $efecto = $remision->movimiento()->lockForUpdate()->value('efecto');
        $efecto = $efecto === null ? '0.00' : bcadd((string) $efecto, '0', 2);
        if (bccomp($efecto, '0.00', 2) <= 0) {
            throw new VinculoDevolucionException('La remisión no aporta una compra computable. El acumulado previo no sustituye esa compra.');
        }
    }

    private function ventaOriginalId(DocumentoVenta $devolucion, int $remisionId): ?int
    {
        $folio = trim((string) $devolucion->remision_original);
        if ($folio === '') {
            return null;
        }

        $id = DocumentoVenta::query()
            ->where('cliente_id', $devolucion->cliente_id)
            ->where('tipo', 'remision')
            ->where('folio', $folio)
            ->where('id', '!=', $remisionId)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function cerrarPendiente(DocumentoVenta $devolucion, ?User $usuario): void
    {
        $abiertas = EscalonamientoIncidencia::query()
            ->where('documento_venta_id', $devolucion->id)
            ->where('codigo', 'pendiente_vinculacion')
            ->where('estado', 'abierta')
            ->lockForUpdate()
            ->get();

        foreach ($abiertas as $incidencia) {
            $incidencia->estado = 'resuelta';
            $incidencia->resolucion = 'Vínculo confirmado contra la remisión de compra nueva.';
            $incidencia->resuelto_en = now();
            $incidencia->resuelto_por_user_id = $usuario?->id;
            $incidencia->save();
        }
    }

    private function evaluarIrregularidad(
        EscalonamientoPeriodo $periodo,
        DocumentoVenta $devolucion,
        ?int $maxAntes,
        ?User $usuario,
    ): void {
        $resumen = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('cliente_id', $devolucion->cliente_id)
            ->first();

        if (! $this->registrar->clasificacionBajo($periodo, $maxAntes, $resumen?->clasificacion_mes_id)) {
            return;
        }

        $antes = $this->nombreLista($maxAntes);
        $despues = $this->nombreLista($resumen?->clasificacion_mes_id);
        $motivo = 'La devolución '.$devolucion->folio.' redujo la clasificación del mes de '.$antes.' a '.$despues.'. El beneficio operativo del cliente no se modifica en este período.';

        $existe = EscalonamientoIncidencia::query()
            ->where('documento_venta_id', $devolucion->id)
            ->where('codigo', 'irregularidad_mantenimiento')
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
            'codigo' => 'irregularidad_mantenimiento',
            'motivo' => $motivo,
            'estado' => 'abierta',
            'user_id' => $usuario?->id,
        ]);
    }

    private function nombreLista(?int $listaId): string
    {
        if (! $listaId) {
            return 'sin lista participante';
        }

        return (string) (CatalogoListaDescuento::query()->whereKey($listaId)->value('nombre') ?: 'sin lista participante');
    }
}
