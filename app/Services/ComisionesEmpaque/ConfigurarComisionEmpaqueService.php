<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AuditoriaComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\TarifaComisionEmpaque;
use App\Models\ComisionesEmpaque\UsuarioAreaComisionEmpaque;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfigurarComisionEmpaqueService
{
    public function guardarArea(array $datos, ?AreaComisionEmpaque $area, User $responsable): AreaComisionEmpaque
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Escriba el nombre del área.']);
        }

        if ($area) {
            if ($area->sistema && array_key_exists('activo', $datos) && ! $datos['activo']) {
                throw ValidationException::withMessages(['activo' => 'CEDIS y Tienda permanecen activas porque el empaque las usa.']);
            }
            $area->fill([
                'nombre' => $nombre,
                'observaciones' => $datos['observaciones'] ?? $area->observaciones,
                'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : $area->activo,
            ]);
            $area->save();
            AuditoriaComisionEmpaque::anotar($responsable, 'area', $area->id, 'actualizar', null, []);

            return $area;
        }

        $codigo = trim((string) ($datos['codigo'] ?? ''));
        if (! preg_match('/^[a-z0-9_]+$/', $codigo)) {
            throw ValidationException::withMessages(['codigo' => 'Use un código en minúsculas, sin espacios.']);
        }

        $area = AreaComisionEmpaque::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => true,
            'sistema' => false,
            'observaciones' => $datos['observaciones'] ?? null,
        ]);
        AuditoriaComisionEmpaque::anotar($responsable, 'area', $area->id, 'crear', null, []);

        return $area;
    }

    public function guardarParticipante(array $datos, ?ParticipanteComisionEmpaque $participante, User $responsable): ParticipanteComisionEmpaque
    {
        $usuario = User::query()->find($datos['user_id'] ?? 0);
        if (! $usuario) {
            throw ValidationException::withMessages(['user_id' => 'Vincule una cuenta de usuario. Quien participa debe tener cuenta.']);
        }

        if ($participante && (int) $participante->user_id !== (int) $usuario->id) {
            throw ValidationException::withMessages(['user_id' => 'La cuenta vinculada se conserva para proteger sus cortes y saldos. Agregue otra persona como un nuevo colaborador.']);
        }

        $duplicado = ParticipanteComisionEmpaque::query()
            ->where('user_id', $usuario->id)
            ->when($participante, fn ($q) => $q->where('id', '!=', $participante->id))
            ->exists();
        if ($duplicado) {
            throw ValidationException::withMessages(['user_id' => 'Esa cuenta ya está vinculada a otro participante.']);
        }

        $nombre = trim((string) ($datos['nombre'] ?? '')) ?: $usuario->name;
        $atributos = [
            'nombre' => $nombre,
            'referencia_interna' => $datos['referencia_interna'] ?? null,
            'user_id' => $usuario->id,
            'observaciones' => $datos['observaciones'] ?? null,
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : true,
        ];

        if ($participante) {
            $participante->update($atributos);
            AuditoriaComisionEmpaque::anotar($responsable, 'participante', $participante->id, 'actualizar', null, ['activo' => $participante->activo]);

            return $participante;
        }

        $participante = ParticipanteComisionEmpaque::query()->create($atributos);
        AuditoriaComisionEmpaque::anotar($responsable, 'participante', $participante->id, 'crear', null, []);

        return $participante;
    }

    /** @param  list<array{user_id: int, nombre?: string, activo?: bool}>  $filas */
    public function guardarParticipantes(array $filas, User $responsable): void
    {
        DB::transaction(function () use ($filas, $responsable) {
            foreach ($filas as $indice => $fila) {
                try {
                    $this->guardarParticipante([
                        'user_id' => $fila['user_id'] ?? null,
                        'nombre' => $fila['nombre'] ?? '',
                        'activo' => array_key_exists('activo', $fila) ? (bool) $fila['activo'] : true,
                    ], null, $responsable);
                } catch (ValidationException $e) {
                    $mensaje = collect($e->errors())->flatten()->first() ?: 'No se pudo agregar este colaborador.';
                    throw ValidationException::withMessages([
                        "colaboradores.$indice.user_id" => $mensaje,
                    ]);
                }
            }
        });
    }

    public function guardarTarifa(int $areaId, string $importe, string $vigenteDesde, User $responsable): TarifaComisionEmpaque
    {
        $centavos = DineroComisionEmpaque::centavos($importe);
        if ($centavos < 1) {
            throw ValidationException::withMessages(['importe' => 'La tarifa debe ser mayor a cero.']);
        }
        $desde = Carbon::parse($vigenteDesde);
        $this->assertSinSolape(TarifaComisionEmpaque::class, $areaId, $desde);

        return DB::transaction(function () use ($areaId, $centavos, $desde, $responsable) {
            TarifaComisionEmpaque::query()
                ->where('area_id', $areaId)
                ->whereNull('vigente_hasta')
                ->where('vigente_desde', '<=', $desde)
                ->update(['vigente_hasta' => $desde]);

            $tarifa = TarifaComisionEmpaque::query()->create([
                'area_id' => $areaId,
                'importe_centavos' => $centavos,
                'vigente_desde' => $desde,
                'creado_por_id' => $responsable->id,
            ]);
            AuditoriaComisionEmpaque::anotar($responsable, 'tarifa', $tarifa->id, 'crear', null, [
                'importe' => DineroComisionEmpaque::texto($centavos),
            ]);

            return $tarifa;
        });
    }

    /** @param  list<array{participante_id: int, porcentaje: string}>  $lineas */
    public function guardarReparto(int $areaId, string $vigenteDesde, array $lineas, User $responsable): RepartoComisionEmpaque
    {
        return DB::transaction(function () use ($areaId, $vigenteDesde, $lineas, $responsable) {
            AreaComisionEmpaque::query()->whereKey($areaId)->lockForUpdate()->firstOrFail();
            if (RepartoComisionEmpaque::query()->where('area_id', $areaId)->where(fn ($q) => $q->whereNotNull('nombre')->orWhere('permanente', true))->exists()) {
                throw ValidationException::withMessages(['lineas' => 'Administre el reparto permanente desde Equipo y reparto.']);
            }

            return $this->guardarRepartoLegado($areaId, $vigenteDesde, $lineas, $responsable);
        });
    }

    private function guardarRepartoLegado(int $areaId, string $vigenteDesde, array $lineas, User $responsable): RepartoComisionEmpaque
    {
        if ($lineas === []) {
            throw ValidationException::withMessages(['lineas' => 'Agregue al menos un participante.']);
        }

        $vistos = [];
        $suma = 0;
        $normalizadas = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['participante_id'] ?? 0);
            if (isset($vistos[$id])) {
                throw ValidationException::withMessages(['lineas' => 'Hay un participante repetido en el reparto.']);
            }
            $vistos[$id] = true;
            $participante = ParticipanteComisionEmpaque::query()->find($id);
            if (! $participante || ! $participante->activo) {
                throw ValidationException::withMessages(['lineas' => 'Solo puede incluir participantes activos.']);
            }
            $puntos = DineroComisionEmpaque::puntosBase((string) ($linea['porcentaje'] ?? ''));
            if ($puntos <= 0) {
                throw ValidationException::withMessages(['lineas' => 'El porcentaje debe ser mayor a cero. El reparto no se redistribuye solo al retirar a alguien.']);
            }
            $suma += $puntos;
            $normalizadas[] = ['participante_id' => $id, 'puntos_base' => $puntos];
        }

        if ($suma !== 10000) {
            throw ValidationException::withMessages([
                'lineas' => 'La suma debe ser exactamente 100%. Ahora suma '.DineroComisionEmpaque::porcentaje($suma).'%.',
            ]);
        }

        $desde = Carbon::parse($vigenteDesde);
        $this->assertSinSolape(RepartoComisionEmpaque::class, $areaId, $desde);

        return DB::transaction(function () use ($areaId, $desde, $normalizadas, $responsable) {
            RepartoComisionEmpaque::query()
                ->where('area_id', $areaId)
                ->whereNull('vigente_hasta')
                ->where('vigente_desde', '<=', $desde)
                ->update(['vigente_hasta' => $desde]);

            $reparto = RepartoComisionEmpaque::query()->create([
                'area_id' => $areaId,
                'vigente_desde' => $desde,
                'creado_por_id' => $responsable->id,
            ]);
            foreach ($normalizadas as $linea) {
                RepartoParticipanteComisionEmpaque::query()->create([
                    'reparto_id' => $reparto->id,
                    'participante_id' => $linea['participante_id'],
                    'puntos_base' => $linea['puntos_base'],
                ]);
            }
            AuditoriaComisionEmpaque::anotar($responsable, 'reparto', $reparto->id, 'crear', null, $normalizadas);

            return $reparto->load('lineas');
        });
    }

    /** @param  list<int>  $areaIds */
    /** @param  list<int>|null  $areasEditables  null = puede reemplazar cualquier área */
    public function guardarAlcance(int $userId, array $areaIds, User $responsable, ?array $areasEditables = null): void
    {
        DB::transaction(function () use ($userId, $areaIds, $responsable, $areasEditables) {
            $borrado = UsuarioAreaComisionEmpaque::query()->where('user_id', $userId);
            if (is_array($areasEditables)) {
                $borrado->whereIn('area_id', $areasEditables);
                $areaIds = array_values(array_intersect(array_map('intval', $areaIds), $areasEditables));
            }
            $borrado->delete();
            foreach (array_unique($areaIds) as $areaId) {
                UsuarioAreaComisionEmpaque::query()->create([
                    'user_id' => $userId,
                    'area_id' => (int) $areaId,
                ]);
            }
            AuditoriaComisionEmpaque::anotar($responsable, 'alcance', $userId, 'actualizar', null, ['areas' => $areaIds]);
        });
    }

    /** @param  class-string<TarifaComisionEmpaque|RepartoComisionEmpaque>  $modelo */
    private function assertSinSolape(string $modelo, int $areaId, Carbon $desde): void
    {
        $solapa = $modelo::query()
            ->where('area_id', $areaId)
            ->where('vigente_desde', '<', $desde)
            ->whereNotNull('vigente_hasta')
            ->where('vigente_hasta', '>', $desde)
            ->exists();

        $posterior = $modelo::query()
            ->where('area_id', $areaId)
            ->where('vigente_desde', '>=', $desde)
            ->exists();

        if ($solapa || $posterior) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'Esa vigencia se cruza con una ya registrada. Elija un inicio posterior al tramo abierto, sin reescribir los anteriores.',
            ]);
        }
    }
}
