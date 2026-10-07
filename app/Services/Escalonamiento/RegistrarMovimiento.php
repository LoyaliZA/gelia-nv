<?php

namespace App\Services\Escalonamiento;

use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\DocumentoVentaRevision;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Services\Escalonamiento\Excepciones\AcumuladoNegativoException;
use App\Services\Escalonamiento\Excepciones\MonedaNoSoportadaException;
use App\Services\Escalonamiento\Excepciones\PeriodoNoAbiertoException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegistrarMovimiento
{
    public function __construct(
        private ParticipacionClienteEscalonamiento $participacion,
        private EvaluarListaClienteEscalonamiento $evaluarLista,
        private ListasPeriodoEscalonamiento $listasPeriodo,
        private PublicarProyeccionClienteEscalonamiento $publicarProyeccion,
    ) {}

    /**
     * Única escritura del acumulado. No modifica clientes.monto_venta_actual ni lista_actual_id.
     *
     * @param  array{
     *     periodo_id: int,
     *     cliente_id: int,
     *     tipo: string,
     *     folio: string,
     *     total: float|int|string,
     *     efecto: float|int|string,
     *     fecha_emision: string,
     *     serie?: string|null,
     *     sucursal?: string|null,
     *     moneda?: string|null,
     *     origen?: string|null,
     *     estado?: string|null,
     *     operacion?: string|null,
     *     remision_original?: string|null,
     *     datos_fuente?: array<string, string>|null
     * }  $datos
     */
    public function registrar(array $datos): EscalonamientoMovimiento
    {
        $moneda = strtoupper((string) ($datos['moneda'] ?? 'MXN'));
        if ($moneda !== 'MXN') {
            throw new MonedaNoSoportadaException('Solo se registra moneda MXN.');
        }

        $clave = $this->claveDocumento($datos);

        return DB::transaction(function () use ($datos, $clave, $moneda) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($datos['periodo_id']);
            if (! $periodo->permiteEscrituraMovimientos()) {
                throw new PeriodoNoAbiertoException('El período no admite nuevos movimientos.');
            }

            $existente = DocumentoVenta::query()
                ->where('clave_documento', $clave)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente->movimiento()->firstOrFail();
            }

            $efecto = $this->dinero($datos['efecto']);
            $efecto = $this->efectoSiParticipa($periodo, $datos, $efecto);
            $resumen = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $datos['cliente_id'])
                ->lockForUpdate()
                ->first();

            $acumulado = $resumen ? $this->dinero($resumen->acumulado) : '0.00';
            $nuevo = bcadd($acumulado, $efecto, 2);
            if (bccomp($nuevo, '0.00', 2) < 0) {
                throw new AcumuladoNegativoException('El acumulado del período no puede quedar negativo.');
            }

            try {
                $documento = DocumentoVenta::create([
                    'escalonamiento_periodo_id' => $periodo->id,
                    'cliente_id' => $datos['cliente_id'],
                    'tipo' => $datos['tipo'],
                    'folio' => (string) $datos['folio'],
                    'serie' => $datos['serie'] ?? null,
                    'sucursal' => $datos['sucursal'] ?? null,
                    'moneda' => $moneda,
                    'total' => $this->dinero($datos['total']),
                    'estado' => $datos['estado'] ?? 'activo',
                    'fecha_emision' => $datos['fecha_emision'],
                    'origen' => $datos['origen'] ?? 'manual',
                    'clave_documento' => $clave,
                    'remision_original' => $datos['remision_original'] ?? null,
                    'datos_fuente' => $datos['datos_fuente'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException) {
                return DocumentoVenta::query()
                    ->where('clave_documento', $clave)
                    ->firstOrFail()
                    ->movimiento()
                    ->firstOrFail();
            }

            $movimiento = EscalonamientoMovimiento::create([
                'escalonamiento_periodo_id' => $periodo->id,
                'cliente_id' => $datos['cliente_id'],
                'documento_venta_id' => $documento->id,
                'efecto' => $efecto,
                'operacion' => $datos['operacion'] ?? $datos['tipo'],
            ]);

            if (bccomp($efecto, '0.00', 2) !== 0 || $resumen) {
                $this->guardarResumen($periodo->id, (int) $datos['cliente_id'], $nuevo, $resumen);
            }

            return $movimiento;
        });
    }

    /**
     * Escribe el efecto de un documento que ya existe. Una devolución tiene un solo movimiento.
     */
    public function aplicarEfectoEnDocumento(
        DocumentoVenta $documento,
        string $efectoNuevo,
        string $operacion,
        ?int $aplicacionId = null,
    ): EscalonamientoMovimiento {
        return DB::transaction(function () use ($documento, $efectoNuevo, $operacion, $aplicacionId) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($documento->escalonamiento_periodo_id);
            if (! $periodo->permiteEscrituraMovimientos()) {
                throw new PeriodoNoAbiertoException('El período no admite nuevos movimientos.');
            }

            $documento = DocumentoVenta::query()->lockForUpdate()->findOrFail($documento->id);
            $movimiento = EscalonamientoMovimiento::query()
                ->where('documento_venta_id', $documento->id)
                ->lockForUpdate()
                ->first();

            $efectoActual = $movimiento ? $this->dinero($movimiento->efecto) : '0.00';
            $efectoNuevo = $this->dinero($efectoNuevo);
            $delta = bcsub($efectoNuevo, $efectoActual, 2);

            $resumen = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $documento->cliente_id)
                ->lockForUpdate()
                ->first();

            $acumulado = $resumen ? $this->dinero($resumen->acumulado) : '0.00';
            $nuevo = bcadd($acumulado, $delta, 2);
            if (bccomp($nuevo, '0.00', 2) < 0) {
                throw new AcumuladoNegativoException('El acumulado del período no puede quedar negativo.');
            }

            if ($movimiento) {
                $movimiento->efecto = $efectoNuevo;
                $movimiento->operacion = $operacion;
                $movimiento->escalonamiento_aplicacion_devolucion_id = $aplicacionId;
                $movimiento->save();
            } else {
                $movimiento = EscalonamientoMovimiento::create([
                    'escalonamiento_periodo_id' => $periodo->id,
                    'cliente_id' => $documento->cliente_id,
                    'documento_venta_id' => $documento->id,
                    'efecto' => $efectoNuevo,
                    'operacion' => $operacion,
                    'escalonamiento_aplicacion_devolucion_id' => $aplicacionId,
                ]);
            }

            $this->guardarResumen($periodo->id, (int) $documento->cliente_id, $nuevo, $resumen);

            return $movimiento;
        });
    }

    public function clasificacionBajo(EscalonamientoPeriodo $periodo, ?int $maxId, ?int $nuevaId): bool
    {
        return $this->listasPeriodo->clasificacionBajo($periodo, $maxId, $nuevaId);
    }

    /**
     * Ajusta el efecto de un documento ya registrado. No crea un segundo alta.
     */
    public function aplicarDiferencia(
        DocumentoVenta $documento,
        string $totalNuevo,
        string $efectoNuevo,
        string $estadoNuevo,
        ?int $userId = null,
    ): ?EscalonamientoMovimiento {
        return DB::transaction(function () use ($documento, $totalNuevo, $efectoNuevo, $estadoNuevo, $userId) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($documento->escalonamiento_periodo_id);
            if (! $periodo->permiteEscrituraMovimientos()) {
                throw new PeriodoNoAbiertoException('El período no admite nuevos movimientos.');
            }

            $documento = DocumentoVenta::query()->lockForUpdate()->findOrFail($documento->id);
            $movimiento = EscalonamientoMovimiento::query()
                ->where('documento_venta_id', $documento->id)
                ->lockForUpdate()
                ->first();

            $efectoActual = $movimiento ? $this->dinero($movimiento->efecto) : '0.00';
            $efectoNuevo = $this->dinero($efectoNuevo);
            $delta = bcsub($efectoNuevo, $efectoActual, 2);

            $resumen = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('cliente_id', $documento->cliente_id)
                ->lockForUpdate()
                ->first();

            $acumulado = $resumen ? $this->dinero($resumen->acumulado) : '0.00';
            $nuevo = bcadd($acumulado, $delta, 2);
            if (bccomp($nuevo, '0.00', 2) < 0) {
                throw new AcumuladoNegativoException('El acumulado del período no puede quedar negativo.');
            }

            $totalAnterior = $this->dinero($documento->total);
            $documento->total = $this->dinero($totalNuevo);
            $documento->estado = $estadoNuevo;
            $documento->save();

            if ($movimiento) {
                $movimiento->efecto = $efectoNuevo;
                $movimiento->operacion = 'revision';
                $movimiento->save();
            } elseif (bccomp($efectoNuevo, '0.00', 2) !== 0) {
                $movimiento = EscalonamientoMovimiento::create([
                    'escalonamiento_periodo_id' => $periodo->id,
                    'cliente_id' => $documento->cliente_id,
                    'documento_venta_id' => $documento->id,
                    'efecto' => $efectoNuevo,
                    'operacion' => 'revision',
                ]);
            }

            if (bccomp($delta, '0.00', 2) !== 0 || $resumen) {
                $this->guardarResumen($periodo->id, (int) $documento->cliente_id, $nuevo, $resumen);
            }

            DocumentoVentaRevision::create([
                'documento_venta_id' => $documento->id,
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $this->dinero($totalNuevo),
                'efecto_anterior' => $efectoActual,
                'efecto_nuevo' => $efectoNuevo,
                'user_id' => $userId,
            ]);

            return $movimiento;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function claveDocumento(array $datos): string
    {
        $partes = [
            strtoupper(trim((string) $datos['tipo'])),
            strtoupper(trim((string) ($datos['serie'] ?? ''))),
            strtoupper(trim((string) ($datos['sucursal'] ?? ''))),
            trim((string) $datos['folio']),
        ];

        return implode('|', $partes);
    }

    private function dinero(float|int|string $valor): string
    {
        return bcadd((string) $valor, '0', 2);
    }

    private function guardarResumen(int $periodoId, int $clienteId, string $acumulado, ?EscalonamientoResumenCliente $resumen): void
    {
        $periodo = EscalonamientoPeriodo::query()->findOrFail($periodoId);
        $cliente = Cliente::query()->findOrFail($clienteId);

        if ($resumen) {
            $this->evaluarLista->sincronizarResumen($periodo, $cliente, $resumen, $acumulado);
            $resumen->save();
            $this->publicarProyeccion->desdeResumen($periodo, $cliente, $resumen);

            return;
        }

        $nuevoResumen = $this->evaluarLista->crearResumen($periodo, $cliente, $acumulado);
        $this->publicarProyeccion->desdeResumen($periodo, $cliente, $nuevoResumen);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function efectoSiParticipa(EscalonamientoPeriodo $periodo, array $datos, string $efecto): string
    {
        if (($datos['tipo'] ?? '') !== 'remision' || bccomp($efecto, '0.00', 2) <= 0) {
            return $efecto;
        }

        $cliente = Cliente::query()->find($datos['cliente_id'] ?? 0);
        if (! $cliente || $this->participacion->clienteParticipa($periodo, $cliente)) {
            return $efecto;
        }

        return '0.00';
    }

}
