<?php

namespace App\Services\Usuarios;

use App\Models\Area;
use App\Models\Departamento;
use App\Models\User;
use App\Services\Mobile\MobileDeviceRevocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AlcanceGestionUsuariosService
{
    public function esAdministracionGlobal(User $actor): bool
    {
        return $actor->hasRole(['Super Admin', 'Administrador']);
    }

    public function assertPuedeGestionarUsuario(User $actor, User $objetivo): void
    {
        if ($this->esAdministracionGlobal($actor) || (int) $actor->id === (int) $objetivo->id) {
            return;
        }

        $reportaAlActor = $objetivo->gerentes()
            ->where('users.id', $actor->id)
            ->exists();

        if (! $reportaAlActor) {
            abort(403, 'No puedes gestionar una cuenta fuera de tu equipo.');
        }
    }

    public function assertPuedeArchivar(User $actor, User $objetivo): void
    {
        $this->assertPuedeGestionarUsuario($actor, $objetivo);

        if ((int) $objetivo->id === (int) $actor->id) {
            throw ValidationException::withMessages([
                'error' => 'No puedes archivar tu propia cuenta.',
            ]);
        }

        if ($objetivo->hasRole('Super Admin')) {
            throw ValidationException::withMessages([
                'error' => 'No se puede archivar a un Super Admin.',
            ]);
        }

        if ($objetivo->hasRole('Administrador') && ! $actor->hasRole('Super Admin')) {
            throw ValidationException::withMessages([
                'error' => 'Solo Super Admin puede archivar una cuenta con rol Administrador.',
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $rolesSolicitados
     * @return array<int, string>
     */
    public function resolverRoles(User $actor, ?User $objetivo, array $rolesSolicitados): array
    {
        $solicitados = collect($rolesSolicitados)
            ->map(fn ($rol) => (string) $rol)
            ->reject(fn (string $rol) => str_contains($rol, 'Grupo:'))
            ->unique()
            ->values()
            ->all();

        $existentes = $objetivo
            ? $objetivo->roles->pluck('name')->map(fn ($rol) => (string) $rol)->all()
            : [];

        foreach ($solicitados as $rol) {
            if (! $this->puedeOtorgarRol($actor, $rol) && ! in_array($rol, $existentes, true)) {
                throw ValidationException::withMessages([
                    'roles_asignados' => "No puedes asignar el rol «{$rol}».",
                ]);
            }
        }

        $conservados = array_values(array_filter(
            $existentes,
            fn (string $rol) => ! $this->puedeOtorgarRol($actor, $rol)
        ));

        $gestionados = array_values(array_filter(
            $solicitados,
            fn (string $rol) => $this->puedeOtorgarRol($actor, $rol)
        ));

        $finales = array_values(array_unique([...$conservados, ...$gestionados]));

        if ($objetivo && (int) $objetivo->id === (int) $actor->id) {
            $elevados = array_intersect(
                array_diff($finales, $existentes),
                ['Super Admin', 'Administrador']
            );

            if ($elevados !== []) {
                throw ValidationException::withMessages([
                    'roles_asignados' => 'No puedes elevar tus propios roles.',
                ]);
            }
        }

        if (
            $objetivo
            && in_array('Super Admin', $existentes, true)
            && ! in_array('Super Admin', $finales, true)
            && ! $this->quedaOtroSuperAdmin($objetivo)
        ) {
            throw ValidationException::withMessages([
                'roles_asignados' => 'No puedes quitar el rol Super Admin de la única cuenta que lo conserva.',
            ]);
        }

        if ($finales !== []) {
            $conocidos = Role::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $finales)
                ->pluck('name')
                ->all();

            if (array_diff($finales, $conocidos) !== []) {
                throw ValidationException::withMessages([
                    'roles_asignados' => 'Hay un rol que no existe.',
                ]);
            }
        }

        return $finales;
    }

    /**
     * @param  array<int, mixed>  $solicitados
     * @return array<int, int>
     */
    public function resolverDepartamentos(User $actor, ?User $objetivo, array $solicitados): array
    {
        $ids = $this->idsEnteros($solicitados);
        $this->assertExistenActivos(Departamento::class, $ids, 'departamentos', 'Hay un departamento que no existe o no está activo.');

        if ($this->esAdministracionGlobal($actor)) {
            return $ids;
        }

        $alcance = $actor->departamentos()
            ->where('departamentos.activo', true)
            ->pluck('departamentos.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->rechazarFueraDeAlcance($ids, $alcance, 'departamentos', 'Solo puedes asignar departamentos de tu alcance.');

        return $this->fusionarConExistentesFueraDeAlcance(
            $ids,
            $alcance,
            $objetivo?->departamentos()->pluck('departamentos.id')->map(fn ($id) => (int) $id)->all() ?? []
        );
    }

    /**
     * @param  array<int, mixed>  $solicitados
     * @return array<int, int>
     */
    public function resolverAreas(User $actor, ?User $objetivo, array $solicitados): array
    {
        $ids = $this->idsEnteros($solicitados);
        $this->assertExisten(Area::class, $ids, 'areas', 'Hay un área que no existe.');

        if ($this->esAdministracionGlobal($actor)) {
            return $ids;
        }

        $alcance = $actor->areas()
            ->pluck('areas.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->rechazarFueraDeAlcance($ids, $alcance, 'areas', 'Solo puedes asignar áreas de tu alcance.');

        return $this->fusionarConExistentesFueraDeAlcance(
            $ids,
            $alcance,
            $objetivo?->areas()->pluck('areas.id')->map(fn ($id) => (int) $id)->all() ?? []
        );
    }

    /**
     * @param  array<int, mixed>  $solicitados
     * @return array<int, int>
     */
    public function resolverGerentes(User $actor, ?User $objetivo, array $solicitados): array
    {
        $ids = $this->idsEnteros($solicitados);
        $this->assertUsuariosExisten($ids);

        if ($this->esAdministracionGlobal($actor)) {
            return $ids;
        }

        if ($objetivo === null) {
            $this->rechazarFueraDeAlcance(
                $ids,
                [$actor->id],
                'gerentes',
                'Solo puedes asignarte como gerente del colaborador.'
            );

            return [$actor->id];
        }

        $existentes = $objetivo->gerentes()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ((int) $objetivo->id === (int) $actor->id) {
            return $existentes;
        }

        $nuevos = array_values(array_diff($ids, $existentes));
        $this->rechazarFueraDeAlcance(
            $nuevos,
            [$actor->id],
            'gerentes',
            'Solo puedes asignarte como gerente del colaborador.'
        );

        return array_values(array_unique([...$existentes, $actor->id]));
    }

    /**
     * @param  array<int, mixed>  $solicitadas
     * @return array{0: array<int, int>, 1: ?int}
     */
    public function prepararSucursales(User $actor, ?User $objetivo, array $solicitadas, ?int $principal): array
    {
        $ids = $this->idsEnteros($solicitadas);

        if ($this->esAdministracionGlobal($actor)) {
            return [$ids, $principal];
        }

        $alcance = $actor->idsSucursalesOperables()
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->rechazarFueraDeAlcance($ids, $alcance, 'sucursales', 'Solo puedes asignar sucursales de tu alcance.');

        $existentes = [];
        $principalExistente = null;

        if ($objetivo) {
            $objetivo->loadMissing('sucursales');
            $existentes = $objetivo->idsSucursalesOperables()
                ->map(fn ($id) => (int) $id)
                ->all();
            $principalMarcada = $objetivo->sucursalPrincipal()?->id;
            $principalExistente = $principalMarcada !== null ? (int) $principalMarcada : null;
        }

        $conservar = array_values(array_diff($existentes, $alcance));
        $finales = array_values(array_unique([...$ids, ...$conservar]));

        if ($principal === null && $principalExistente !== null && in_array($principalExistente, $conservar, true)) {
            $principal = $principalExistente;
        }

        return [$finales, $principal];
    }

    public function cerrarAccesosTrasCambioDeContrasena(User $usuario): void
    {
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        $usuario->forceFill(['remember_token' => Str::random(60)])->save();
        app(MobileDeviceRevocationService::class)->revocarTodosParaUsuario($usuario);
    }

    private function puedeOtorgarRol(User $actor, string $rol): bool
    {
        if ($rol === 'Super Admin') {
            return $actor->hasRole('Super Admin');
        }

        if ($rol === 'Administrador') {
            return $actor->hasRole(['Super Admin', 'Administrador']);
        }

        return true;
    }

    private function quedaOtroSuperAdmin(User $objetivo): bool
    {
        return User::role('Super Admin')
            ->where('users.id', '!=', $objetivo->id)
            ->exists();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function idsEnteros(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @param  array<int, int>  $ids
     */
    private function assertExistenActivos(string $modelo, array $ids, string $campo, string $mensaje): void
    {
        if ($ids === []) {
            return;
        }

        $encontrados = $modelo::query()
            ->whereIn('id', $ids)
            ->where('activo', true)
            ->pluck('id');

        if ($encontrados->count() !== count($ids)) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @param  array<int, int>  $ids
     */
    private function assertExisten(string $modelo, array $ids, string $campo, string $mensaje): void
    {
        if ($ids === []) {
            return;
        }

        $encontrados = $modelo::query()->whereIn('id', $ids)->pluck('id');

        if ($encontrados->count() !== count($ids)) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function assertUsuariosExisten(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $encontrados = User::withTrashed()->whereIn('id', $ids)->pluck('id');

        if ($encontrados->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'gerentes' => 'Hay un gerente que no existe.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $solicitados
     * @param  array<int, int>  $alcance
     */
    private function rechazarFueraDeAlcance(array $solicitados, array $alcance, string $campo, string $mensaje): void
    {
        if (array_diff($solicitados, $alcance) !== []) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    /**
     * @param  array<int, int>  $solicitados
     * @param  array<int, int>  $alcance
     * @param  array<int, int>  $existentes
     * @return array<int, int>
     */
    private function fusionarConExistentesFueraDeAlcance(array $solicitados, array $alcance, array $existentes): array
    {
        $conservar = array_values(array_diff($existentes, $alcance));

        return array_values(array_unique([...$solicitados, ...$conservar]));
    }
}
