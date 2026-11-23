<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafEvidencia;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Support\SaldosAFavor\PoliticaUsoCreditoSaf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conciliación administrativa del origen de un saldo a favor pendiente. Es la única vía para pasar un crédito de
 * «pendiente de conciliación» a «solo transporte» o «general». No cambia montos, estados, reservas ni aplicaciones.
 */
final class ConciliarOrigenCreditoSafService
{
    /** Combinaciones que una conciliación puede acreditar. «Mixto» no se divide por conjetura: sigue pendiente. */
    private const RESOLUCIONES = [
        SafCredito::USO_SOLO_TRANSPORTE => SafCredito::ORIGEN_TRANSPORTE_CONFIRMADO,
        SafCredito::USO_GENERAL => SafCredito::ORIGEN_MERCANCIA,
    ];

    public function __construct(private RegistrarMovimientoSafService $movimientos) {}

    /** @param  list<UploadedFile>  $evidencias */
    public function ejecutar(SafCredito $credito, User $admin, string $uso, string $motivo, array $evidencias): SafCredito
    {
        if (! $admin->can('saldos_favor.autorizar_envio')) {
            throw ValidationException::withMessages(['permiso' => 'Solo Administración concilia el origen de un saldo a favor.']);
        }
        if (! array_key_exists($uso, self::RESOLUCIONES)) {
            throw ValidationException::withMessages(['uso' => 'Resuelva el crédito como solo transporte o de uso general. Un origen mixto sigue pendiente.']);
        }
        $motivo = trim($motivo);
        $archivos = array_values(array_filter($evidencias, fn ($a) => $a instanceof UploadedFile));
        if (mb_strlen($motivo) < 10) {
            throw ValidationException::withMessages(['motivo' => 'Explique cómo se comprobó el origen del saldo.']);
        }
        if ($archivos === []) {
            throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia que comprueba el origen.']);
        }

        return DB::transaction(function () use ($credito, $admin, $uso, $motivo, $archivos) {
            $credito = SafCredito::query()->lockForUpdate()->findOrFail($credito->id);
            if (PoliticaUsoCreditoSaf::uso($credito) !== SafCredito::USO_PENDIENTE_CONCILIACION) {
                throw ValidationException::withMessages(['credito' => 'Este saldo no está pendiente de conciliación.']);
            }
            if ((int) $credito->generado_por_id === (int) $admin->id) {
                throw ValidationException::withMessages(['permiso' => 'Quien generó el saldo no concilia su propio origen.']);
            }

            $anterior = PoliticaUsoCreditoSaf::uso($credito);
            $credito->update([
                'uso_permitido' => $uso,
                'origen_economico' => self::RESOLUCIONES[$uso],
                'clasificacion_fuente' => 'conciliacion_administrativa',
                'clasificacion_motivo' => $motivo,
                'clasificado_por_id' => $admin->id,
                'clasificado_at' => now(),
            ]);
            $disponible = (float) $credito->monto_disponible;
            $mov = $this->movimientos->handle($credito, SafMovimiento::TIPO_CLASIFICACION, 0, $disponible, $disponible, $admin->id, [
                'observaciones' => "Conciliación de origen: {$anterior} → {$uso}. {$motivo}",
            ]);
            foreach ($archivos as $archivo) {
                $ruta = $archivo->store("saldos_favor/evidencias/{$credito->id}", 'public');
                SafEvidencia::create([
                    'saf_credito_id' => $credito->id,
                    'saf_movimiento_id' => $mov->id,
                    'ruta_archivo' => $ruta,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'mime_type' => $archivo->getMimeType(),
                    'tamano_bytes' => $archivo->getSize(),
                    'subido_por_id' => $admin->id,
                ]);
            }

            return $credito->fresh();
        });
    }
}
