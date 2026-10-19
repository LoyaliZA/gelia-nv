<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AjusteComisionEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoAplicacionComisionEmpaque;
use App\Models\ComisionesEmpaque\PagoComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarPagoComisionEmpaqueService
{
    /**
     * @param  list<array{asignacion_id: int, importe: string}>  $aplicaciones
     */
    public function pagar(int $participanteId, string $fecha, string $importe, array $aplicaciones, ?string $referencia, ?string $medio, ?string $observaciones, ?string $clave, User $responsable): PagoComisionEmpaque
    {
        $centavos = DineroComisionEmpaque::centavos($importe);
        if ($centavos < 1) {
            throw ValidationException::withMessages(['importe' => 'El importe del pago debe ser mayor a cero.']);
        }

        return DB::transaction(function () use ($participanteId, $fecha, $centavos, $aplicaciones, $referencia, $medio, $observaciones, $clave, $responsable) {
            if ($clave) {
                $existente = PagoComisionEmpaque::query()->where('clave_idempotencia', $clave)->first();
                if ($existente) {
                    if ((int) $existente->participante_id === $participanteId && (int) $existente->importe_centavos === $centavos && $existente->estado === 'aplicado') {
                        return $existente;
                    }
                    throw ValidationException::withMessages([
                        'clave_idempotencia' => 'Esa clave ya se usó en otro pago. Recargue la pantalla antes de registrar uno nuevo.',
                    ]);
                }
            }

            if ($aplicaciones === []) {
                throw ValidationException::withMessages(['aplicaciones' => 'Seleccione al menos una asignación del mismo participante.']);
            }

            $ids = collect($aplicaciones)->pluck('asignacion_id')->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->count() !== count($aplicaciones)) {
                throw ValidationException::withMessages(['aplicaciones' => 'Hay una asignación repetida. Deje una sola aplicación por corte.']);
            }

            $asignaciones = AsignacionComisionEmpaque::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $suma = 0;
            $filas = [];
            foreach ($aplicaciones as $aplicacion) {
                $asignacion = $asignaciones->get((int) $aplicacion['asignacion_id']);
                if (! $asignacion || (int) $asignacion->participante_id !== $participanteId) {
                    throw ValidationException::withMessages(['aplicaciones' => 'Cada aplicación debe pertenecer al participante seleccionado.']);
                }
                $monto = DineroComisionEmpaque::centavos((string) $aplicacion['importe']);
                if ($monto < 1) {
                    throw ValidationException::withMessages(['aplicaciones' => 'Cada aplicación debe ser mayor a cero.']);
                }
                if ($monto > $asignacion->pendienteCentavos()) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => 'Una aplicación supera el pendiente de esa asignación. Reduzca el importe.',
                    ]);
                }
                $suma += $monto;
                $filas[] = [$asignacion, $monto];
            }

            if ($suma !== $centavos) {
                throw ValidationException::withMessages([
                    'importe' => 'El importe del pago debe ser igual a la suma aplicada a las asignaciones.',
                ]);
            }

            try {
                $pago = PagoComisionEmpaque::query()->create([
                    'participante_id' => $participanteId,
                    'fecha_pago' => $fecha,
                    'importe_centavos' => $centavos,
                    'referencia' => $referencia,
                    'medio' => $medio,
                    'observaciones' => $observaciones,
                    'clave_idempotencia' => $clave,
                    'estado' => 'aplicado',
                    'registrado_por_id' => $responsable->id,
                ]);
            } catch (QueryException $e) {
                $existente = $clave
                    ? PagoComisionEmpaque::query()->where('clave_idempotencia', $clave)->first()
                    : null;
                if ($existente && (int) $existente->participante_id === $participanteId && (int) $existente->importe_centavos === $centavos && $existente->estado === 'aplicado') {
                    return $existente;
                }
                if ($existente) {
                    throw ValidationException::withMessages([
                        'clave_idempotencia' => 'Esa clave ya se usó en otro pago. Recargue la pantalla antes de registrar uno nuevo.',
                    ]);
                }
                throw $e;
            }

            foreach ($filas as [$asignacion, $monto]) {
                PagoAplicacionComisionEmpaque::query()->create([
                    'pago_id' => $pago->id,
                    'asignacion_id' => $asignacion->id,
                    'importe_centavos' => $monto,
                ]);
                $asignacion->pagado_centavos = (int) $asignacion->pagado_centavos + $monto;
                $asignacion->save();
            }

            AuditoriaComisionEmpaque::anotar($responsable, 'pago', $pago->id, 'registrar', $observaciones, [
                'importe' => DineroComisionEmpaque::texto($centavos),
            ]);

            return $pago;
        });
    }

    public function revertir(PagoComisionEmpaque $pago, string $motivo, User $responsable): PagoComisionEmpaque
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo de la reversión.']);
        }

        return DB::transaction(function () use ($pago, $motivo, $responsable) {
            $pago = PagoComisionEmpaque::query()->lockForUpdate()->findOrFail($pago->id);
            if ($pago->estado !== 'aplicado') {
                throw ValidationException::withMessages(['pago' => 'Este pago ya fue revertido.']);
            }
            foreach ($pago->aplicaciones as $aplicacion) {
                $asignacion = AsignacionComisionEmpaque::query()->lockForUpdate()->findOrFail($aplicacion->asignacion_id);
                $asignacion->pagado_centavos = (int) $asignacion->pagado_centavos - (int) $aplicacion->importe_centavos;
                $asignacion->save();
            }
            $pago->estado = 'revertido';
            $pago->motivo_reversion = $motivo;
            $pago->revertido_por_id = $responsable->id;
            $pago->revertido_at = now();
            $pago->save();
            AuditoriaComisionEmpaque::anotar($responsable, 'pago', $pago->id, 'revertir', $motivo, []);

            return $pago;
        });
    }

    public function ajustar(array $datos, User $responsable): AjusteComisionEmpaque
    {
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Escriba el motivo del ajuste.']);
        }
        $centavos = DineroComisionEmpaque::centavos((string) $datos['importe']);
        if ($centavos === 0) {
            throw ValidationException::withMessages(['importe' => 'El ajuste no puede ser cero. Use un importe positivo o negativo.']);
        }

        return DB::transaction(function () use ($datos, $motivo, $centavos, $responsable) {
            $asignacion = null;
            if (! empty($datos['asignacion_id'])) {
                $asignacion = AsignacionComisionEmpaque::query()->lockForUpdate()->find($datos['asignacion_id']);
                if (! $asignacion) {
                    throw ValidationException::withMessages(['asignacion_id' => 'La asignación no existe.']);
                }
            }

            $aplicar = (bool) ($datos['aplicar_en_saldo'] ?? false);
            if ($aplicar && $asignacion && $centavos < 0 && $asignacion->pendienteCentavos() + $centavos < 0) {
                throw ValidationException::withMessages([
                    'importe' => 'El ajuste negativo dejaría el pendiente por debajo de cero. Reduzca el importe o no lo aplique al saldo.',
                ]);
            }

            $ajuste = AjusteComisionEmpaque::query()->create([
                'corte_id' => $asignacion?->corte_id ?: ($datos['corte_id'] ?? null),
                'asignacion_id' => $asignacion?->id,
                'unidad_id' => $datos['unidad_id'] ?? null,
                'participante_id' => $asignacion?->participante_id ?: ($datos['participante_id'] ?? null),
                'importe_centavos' => $centavos,
                'aplicar_en_saldo' => $aplicar,
                'motivo' => $motivo,
                'responsable_id' => $responsable->id,
            ]);
            AuditoriaComisionEmpaque::anotar($responsable, 'ajuste', $ajuste->id, 'registrar', $motivo, [
                'importe' => DineroComisionEmpaque::texto($centavos),
                'aplicar_en_saldo' => $aplicar,
            ]);

            return $ajuste;
        });
    }
}
