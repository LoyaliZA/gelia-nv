<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\MedicionEventoPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Support\PuntoVenta\Medicion\CatalogoEventosMedicionPdv as Catalogo;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

class RegistrarTransicionMedicionPdvService
{
    /** La transacción pertenece al comando operativo; no se registra fuera de ella. */
    public function ejecutar(array $datos): MedicionEventoPdv
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('La medición requiere la transacción de la operación.');
        }
        $datos = Validator::make($datos, [
            'tipo' => ['required', Rule::in(Catalogo::TIPOS)],
            'contrato_version' => ['sometimes', 'integer', Rule::in([Catalogo::VERSION])],
            'user_id' => ['required', 'integer', 'min:1'],
            'actor_id' => ['present', 'nullable', 'integer', 'min:1'],
            'sucursal_id' => ['required', 'integer', 'min:1'],
            'jornada_id' => ['required', 'integer', 'min:1'],
            'intervalo_id' => ['required', 'integer', 'min:1'],
            'turno_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'atencion_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'efectivo_at' => ['required', 'date'],
            'origen' => ['required', Rule::in(Catalogo::ORIGENES)],
            'sesion_uuid' => ['required', 'uuid'],
            'correlacion_uuid' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'zona_horaria' => ['required', 'timezone'],
            'fecha_operativa' => ['required', 'date_format:Y-m-d'],
            'corrige_evento_id' => ['nullable', 'integer', 'min:1', 'required_if:tipo,'.Catalogo::CORRECCION],
            'contexto' => ['present', 'array:motivo'],
            'contexto.motivo' => ['required_if:tipo,'.Catalogo::CORRECCION, 'string', 'max:500'],
        ])->validate();
        foreach (['user_id', 'actor_id', 'sucursal_id', 'jornada_id', 'intervalo_id', 'turno_id', 'atencion_id', 'corrige_evento_id'] as $campo) {
            if (isset($datos[$campo])) {
                $datos[$campo] = (int) $datos[$campo];
            }
        }

        // El bloqueo de una fila estable serializa incluso el primer evento del agregado.
        User::withTrashed()->whereKey($datos['user_id'])->lockForUpdate()->firstOrFail();
        if ($datos['actor_id'] !== null) {
            User::withTrashed()->findOrFail($datos['actor_id']);
        }
        $automatico = in_array($datos['origen'], ['sistema', 'job'], true);
        if ($automatico !== ($datos['actor_id'] === null)) {
            $this->rechazar('actor_id', 'El origen automático requiere actor sistema; el humano requiere actor real.');
        }
        if (($datos['origen'] === 'personal' && $datos['actor_id'] !== $datos['user_id'])
            || ($datos['origen'] === 'gerencial' && $datos['actor_id'] === $datos['user_id'])) {
            $this->rechazar('origen', 'El origen debe corresponder al actor y empleado reales.');
        }
        $jornada = JornadaPdv::query()->findOrFail($datos['jornada_id']);
        $intervalo = IntervaloOperativoPdv::query()->findOrFail($datos['intervalo_id']);
        if ((int) $jornada->user_id !== (int) $datos['user_id']
            || (int) $jornada->sucursal_id !== (int) $datos['sucursal_id']
            || (int) $intervalo->jornada_id !== (int) $jornada->id
            || (int) $intervalo->user_id !== (int) $datos['user_id']
            || (int) $intervalo->sucursal_id !== (int) $datos['sucursal_id']) {
            $this->rechazar('jornada_id', 'Las referencias no pertenecen al mismo usuario, jornada y sucursal.');
        }
        if (! empty($datos['turno_id'])) {
            $turno = TurnoPdv::query()->findOrFail($datos['turno_id']);
            if ((int) $turno->sucursal_id !== (int) $datos['sucursal_id']) {
                $this->rechazar('turno_id', 'El turno no corresponde a la sucursal.');
            }
        }
        if (! empty($datos['atencion_id'])) {
            $atencion = TurnoPdvAtencion::query()->findOrFail($datos['atencion_id']);
            if ((int) $atencion->user_id !== (int) $datos['user_id']
                || (int) $atencion->turno_id !== (int) ($datos['turno_id'] ?? 0)) {
                $this->rechazar('atencion_id', 'La atención no corresponde al turno y empleado.');
            }
        }

        $efectivo = CarbonImmutable::parse($datos['efectivo_at'])->utc();
        $datos['efectivo_at'] = $efectivo->format('Y-m-d H:i:s.u');
        $datos['contrato_version'] = Catalogo::VERSION;
        $datos['contexto'] = $datos['contexto'] ?? [];
        $datos['corrige_evento_id'] = $datos['corrige_evento_id'] ?? null;
        foreach (['turno_id', 'atencion_id'] as $campo) {
            $datos[$campo] = $datos[$campo] ?? null;
        }
        if ($datos['tipo'] === Catalogo::APERTURA) {
            if ($datos['corrige_evento_id'] !== null
                || ! $efectivo->equalTo($jornada->apertura_at)
                || ! $efectivo->equalTo($intervalo->inicio_at)
                || $datos['contexto'] !== []) {
                $this->rechazar('efectivo_at', 'La apertura debe describir los extremos reales sin contexto artificial.');
            }
        } else {
            $original = MedicionEventoPdv::query()->findOrFail($datos['corrige_evento_id']);
            if ((int) $original->user_id !== (int) $datos['user_id']
                || (int) $original->sucursal_id !== (int) $datos['sucursal_id']
                || (int) $original->jornada_id !== (int) $datos['jornada_id']) {
                $this->rechazar('corrige_evento_id', 'La corrección debe referenciar evidencia del mismo ámbito.');
            }
        }

        $clave = hash('sha256', $datos['idempotency_key']);
        unset($datos['idempotency_key']);
        $huella = hash('sha256', json_encode($this->ordenar($datos), JSON_THROW_ON_ERROR));
        $existente = MedicionEventoPdv::query()->where('idempotency_key', $clave)->first();
        if ($existente !== null) {
            if (! hash_equals($existente->comando_hash, $huella)) {
                $this->rechazar('idempotency_key', 'La clave ya corresponde a un contenido diferente.');
            }

            return $existente;
        }
        // Lectura actual bajo el mutex de usuario: no requiere UPDATE sobre evidencia.
        // El bloqueo compartido evita reutilizar un snapshot previo de REPEATABLE READ.
        $secuencia = 1 + (int) MedicionEventoPdv::query()
            ->where('agregado_tipo', 'persona')->where('agregado_id', $datos['user_id'])
            ->orderByDesc('secuencia')->sharedLock()->value('secuencia');
        $apertura = $datos['tipo'] === Catalogo::APERTURA;

        try {
            return MedicionEventoPdv::query()->create(array_merge($datos, [
                'evento_uuid' => (string) Str::uuid(),
                'esquema_version' => Catalogo::VERSION,
                'agregado_tipo' => 'persona',
                'agregado_id' => $datos['user_id'],
                'secuencia' => $secuencia,
                'actor_tipo' => $automatico ? 'sistema' : 'usuario',
                'idempotency_key' => $clave,
                'comando_hash' => $huella,
                'estado_anterior' => null,
                'estado_nuevo' => $apertura ? ['ciclo' => 'abierta'] : null,
                'contexto' => array_merge($datos['contexto'], [
                    'cobertura' => ['transiciones' => [Catalogo::APERTURA], 'completa' => false, 'metricas_v2' => false],
                ]),
            ]))->fresh();
        } catch (UniqueConstraintViolationException $e) {
            // Otra persona pudo competir por la misma clave global entre las lecturas.
            $existente = MedicionEventoPdv::query()->where('idempotency_key', $clave)->sharedLock()->first();
            if ($existente === null) {
                throw $e;
            }
            if (! hash_equals($existente->comando_hash, $huella)) {
                $this->rechazar('idempotency_key', 'La clave ya corresponde a un contenido diferente.');
            }

            return $existente;
        }
    }

    private function ordenar(array $valor): array
    {
        if (! array_is_list($valor)) {
            ksort($valor);
        }
        foreach ($valor as &$item) {
            if (is_array($item)) {
                $item = $this->ordenar($item);
            }
        }

        return $valor;
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
