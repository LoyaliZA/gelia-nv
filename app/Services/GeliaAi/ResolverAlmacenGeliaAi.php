<?php

namespace App\Services\GeliaAi;

use App\Models\Almacen;
use App\Models\User;
use App\Services\Almacenes\AlcanceAlmacenesService;
use Illuminate\Support\Collection;

class ResolverAlmacenGeliaAi
{
    public function __construct(private AlcanceAlmacenesService $alcance) {}

    /** @return list<int> */
    public function idsOperables(User $user): array
    {
        return $this->alcance->almacenesOperables($user)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Resuelve un almacén dentro del alcance operable de la persona usuaria (misma regla que Almacenes → Inventarios).
     *
     * @return array{ok: true, almacen: Almacen}|array{ok: false, error_code: string, error: string, opciones?: list<array{codigo: string, nombre: string}>}
     */
    public function resolverParaUsuario(User $user, ?int $almacenId, ?string $codigoONombre): array
    {
        $res = $this->buscar($almacenId, $codigoONombre);
        $permitidos = $this->idsOperables($user);
        $opciones = fn (iterable $almacenes) => collect($almacenes)
            ->map(fn (Almacen $a) => ['codigo' => (string) $a->codigo, 'nombre' => (string) $a->nombre])
            ->values()
            ->all();

        $almacen = $res['almacen'];
        if (! $almacen) {
            $candidatos = $res['candidatos']->filter(fn (Almacen $a) => in_array((int) $a->id, $permitidos, true));
            if ($candidatos->count() > 1) {
                return [
                    'ok' => false,
                    'error_code' => 'almacen_ambiguo',
                    'error' => 'Varios almacenes coinciden; pide a la persona usuaria que elija uno.',
                    'opciones' => $opciones($candidatos),
                ];
            }

            return [
                'ok' => false,
                'error_code' => 'almacen_no_encontrado',
                'error' => 'No encontré ese almacén entre los almacenes activos.',
                'opciones' => $opciones($this->alcance->almacenesOperables($user)->take(15)),
            ];
        }

        if (! in_array((int) $almacen->id, $permitidos, true)) {
            return [
                'ok' => false,
                'error_code' => 'sin_permiso_almacen',
                'error' => "No tienes acceso operable al almacén {$almacen->codigo}.",
            ];
        }

        return ['ok' => true, 'almacen' => $almacen];
    }

    public function resolver(?int $almacenId, ?string $codigoONombre): ?Almacen
    {
        $res = $this->buscar($almacenId, $codigoONombre);

        return $res['almacen'] ?? $res['candidatos']->first();
    }

    /**
     * Código exacto o id gana; si no, devuelve candidatos por prefijo de código o nombre para que la IA pregunte.
     *
     * @return array{almacen: Almacen|null, candidatos: Collection<int, Almacen>}
     */
    public function buscar(?int $almacenId, ?string $codigoONombre): array
    {
        if ($almacenId) {
            $almacen = Almacen::query()->where('id', $almacenId)->where('activo', true)->first();

            return ['almacen' => $almacen, 'candidatos' => collect()];
        }

        $q = trim((string) $codigoONombre);
        if ($q === '') {
            return ['almacen' => null, 'candidatos' => collect()];
        }

        $exacto = Almacen::query()
            ->where('activo', true)
            ->where(fn ($b) => $b->where('codigo', $q)->orWhere('nombre', $q))
            ->first();
        if ($exacto) {
            return ['almacen' => $exacto, 'candidatos' => collect()];
        }

        $candidatos = Almacen::query()
            ->where('activo', true)
            ->where(function ($builder) use ($q) {
                $builder->where('codigo', 'like', $q.'%')
                    ->orWhere('nombre', 'like', '%'.$q.'%');
            })
            ->orderBy('codigo')
            ->limit(8)
            ->get();

        return [
            'almacen' => $candidatos->count() === 1 ? $candidatos->first() : null,
            'candidatos' => $candidatos,
        ];
    }
}
