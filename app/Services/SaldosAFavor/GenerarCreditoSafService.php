<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafEvidencia;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Notifications\SaldoFavorPendienteRevisionNotification;
use App\Support\SaldosAFavor\ReglasSaf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class GenerarCreditoSafService
{
    public function __construct(
        private ObtenerOCrearCuentaSafService $cuentas,
        private RegistrarMovimientoSafService $movimientos,
        private ValidarClienteSafService $validarCliente,
    ) {}

    /**
     * @param  array{
     *   cliente_id:int,
     *   monto:float|int|string,
     *   saf_motivo_id:int,
     *   detalle_motivo?:string|null,
     *   canal_origen?:string|null,
     *   sucursal?:string|null,
     *   departamento?:string|null,
     *   pedido_bma_id?:int|null,
     *   documento_origen?:string|null,
     *   generado_por_id?:int|null,
     *   moneda?:string,
     *   fecha_generacion?:\Carbon\Carbon|string|null,
     *   fecha_vencimiento?:\Carbon\Carbon|string|null,
     *   observaciones?:string|null,
     *   origen_manual?:bool,
     *   omitir_monto_minimo?:bool,
     * }  $datos
     * @param  list<UploadedFile>  $evidencias
     */
    public function handle(array $datos, array $evidencias = []): SafCredito
    {
        $clienteId = (int) ($datos['cliente_id'] ?? 0);
        $this->validarCliente->assertTransferible($clienteId);

        $monto = round((float) ($datos['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto del saldo a favor debe ser mayor a cero.');
        }
        if (! ($datos['omitir_monto_minimo'] ?? false)) {
            ReglasSaf::assertMontoMinimo($monto);
        }

        $motivoId = (int) ($datos['saf_motivo_id'] ?? 0);
        if ($motivoId <= 0) {
            throw new InvalidArgumentException('Debe indicar un motivo del catálogo.');
        }

        $motivo = SafMotivo::find($motivoId);
        if (! $motivo || ! $motivo->activo) {
            throw new InvalidArgumentException('El motivo seleccionado no es válido.');
        }
        if ($motivo->requiere_detalle && blank($datos['detalle_motivo'] ?? null)) {
            throw new InvalidArgumentException('El motivo seleccionado requiere detalle.');
        }

        $origenManual = (bool) ($datos['origen_manual'] ?? false);
        $archivos = array_values(array_filter(
            $evidencias,
            fn ($f) => $f instanceof UploadedFile
        ));
        if ($origenManual && $archivos === []) {
            throw new InvalidArgumentException('La generación manual requiere al menos una evidencia.');
        }

        return DB::transaction(function () use ($datos, $monto, $clienteId, $archivos, $motivoId) {
            $this->assertNoDuplicado($clienteId, $monto, $datos);

            $cuenta = $this->cuentas->handle($clienteId, $datos['moneda'] ?? 'MXN');
            $fechaGeneracion = isset($datos['fecha_generacion'])
                ? \Carbon\Carbon::parse($datos['fecha_generacion'])
                : now();
            $fechaVencimientoOverride = isset($datos['fecha_vencimiento']) && $datos['fecha_vencimiento'] !== ''
                ? \Carbon\Carbon::parse($datos['fecha_vencimiento'])
                : null;
            $folio = $this->siguienteFolio();

            $credito = SafCredito::create([
                'folio' => $folio,
                'saf_cuenta_id' => $cuenta->id,
                'cliente_id' => $clienteId,
                'canal_origen' => $datos['canal_origen'] ?? null,
                'sucursal' => $datos['sucursal'] ?? null,
                'departamento' => $datos['departamento'] ?? null,
                'pedido_bma_id' => $datos['pedido_bma_id'] ?? null,
                'documento_origen' => $datos['documento_origen'] ?? null,
                'monto_original' => $monto,
                'monto_aplicado' => 0,
                'monto_reservado' => 0,
                'monto_disponible' => $monto,
                'fecha_generacion' => $fechaGeneracion,
                'fecha_vencimiento' => ReglasSaf::fechaVencimientoPara($fechaGeneracion, $fechaVencimientoOverride),
                'saf_motivo_id' => $motivoId,
                'detalle_motivo' => $datos['detalle_motivo'] ?? null,
                'generado_por_id' => $datos['generado_por_id'] ?? null,
                'estado_financiero' => SafCredito::ESTADO_DISPONIBLE,
                'estado_revision' => SafCredito::REVISION_PENDIENTE,
            ]);

            $mov = $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_GENERACION,
                $monto,
                0,
                $monto,
                $datos['generado_por_id'] ?? null,
                [
                    'pedido_bma_id' => $datos['pedido_bma_id'] ?? null,
                    'saf_motivo_id' => $motivoId,
                    'observaciones' => $datos['observaciones'] ?? null,
                ]
            );

            foreach ($archivos as $archivo) {
                $ruta = $archivo->store("saldos_favor/evidencias/{$credito->id}", 'public');
                SafEvidencia::create([
                    'saf_credito_id' => $credito->id,
                    'saf_movimiento_id' => $mov->id,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'subido_por_id' => $datos['generado_por_id'] ?? null,
                ]);
            }

            $revisores = User::permission('saldos_favor.revisar')->get();
            if ($revisores->isNotEmpty()) {
                Notification::send($revisores, new SaldoFavorPendienteRevisionNotification($credito));
            }

            return $credito->fresh(['motivo', 'generadoPor', 'cuenta']);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function assertNoDuplicado(int $clienteId, float $monto, array $datos): void
    {
        $pedidoId = isset($datos['pedido_bma_id']) ? (int) $datos['pedido_bma_id'] : 0;
        if ($pedidoId > 0) {
            $existe = SafCredito::query()
                ->where('pedido_bma_id', $pedidoId)
                ->where('monto_original', $monto)
                ->where('estado_financiero', '!=', SafCredito::ESTADO_CANCELADO)
                ->exists();
            if ($existe) {
                throw new InvalidArgumentException(
                    'Ya existe un saldo a favor no cancelado para este pedido con el mismo monto.'
                );
            }
        }

        $documento = trim((string) ($datos['documento_origen'] ?? ''));
        if ($documento !== '') {
            $existeDoc = SafCredito::query()
                ->where('cliente_id', $clienteId)
                ->where('documento_origen', $documento)
                ->where('monto_original', $monto)
                ->where('estado_financiero', '!=', SafCredito::ESTADO_CANCELADO)
                ->where('created_at', '>=', now()->subDay())
                ->exists();
            if ($existeDoc) {
                throw new InvalidArgumentException(
                    'Ya se registró un saldo a favor reciente con el mismo documento y monto (últimas 24 h).'
                );
            }
        }
    }

    private function siguienteFolio(): string
    {
        $ultimo = SafCredito::query()->lockForUpdate()->orderByDesc('id')->value('folio');
        $n = 1;
        if ($ultimo && preg_match('/SAF-(\d+)/', $ultimo, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return sprintf('SAF-%05d', $n);
    }
}
