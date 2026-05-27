<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\CatalogoSexo;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Services\Permisos\AsignarPermisosUsuarioService;
use App\Services\Permisos\ValidarAsignacionPermisosService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class RegistroController extends Controller
{
    public function generarEnlaceInvitacion(Request $request)
    {
        $request->validate([
            'role_name' => 'required|exists:roles,name',
            'grupo_name' => 'nullable|exists:roles,name',
            'permisos_asignados' => 'nullable|array',
            'permisos_asignados.*' => 'exists:permissions,name',
            'permisos_individuales' => 'nullable|array',
            'permisos_individuales.*' => 'exists:permissions,name',
        ]);

        $user = auth()->user();

        if (!$user->can('usuarios.generar_permisos')) {
            abort(403, 'No tienes permiso para generar enlaces de acceso.');
        }

        if (!$user->hasRole('Super Admin') && in_array($request->role_name, ['Administrador', 'Gerente'])) {
            abort(403, 'No tienes la autoridad suficiente para generar enlaces de este nivel.');
        }

        if ($request->filled('grupo_name') && !str_contains($request->grupo_name, 'Grupo:')) {
            abort(422, 'El grupo seleccionado no es un grupo predefinido válido.');
        }

        $permisosCompletos = collect($request->input('permisos_asignados', $request->input('permisos_individuales', [])))
            ->unique()
            ->values()
            ->all();

        ValidarAsignacionPermisosService::assertPuedeAsignar($user, [], $permisosCompletos);

        $routeParams = [
            'rol' => $request->role_name,
            'supervisor_id' => $user->id,
        ];

        if ($request->filled('grupo_name')) {
            $routeParams['plantilla'] = $request->grupo_name;
        }

        if (!empty($permisosCompletos)) {
            $routeParams['permisos'] = $permisosCompletos;
        }

        $url = URL::temporarySignedRoute(
            'registro.formulario',
            now()->addHours(48),
            $routeParams
        );

        return response()->json(['enlace' => $url]);
    }

    public function mostrarFormulario(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'El enlace de registro de GELIA es inválido o ha expirado.');
        }

        $sexos = CatalogoSexo::all();
        $rolAsignado = $request->query('rol');
        $plantillaOrigen = $request->query('plantilla') ?? $request->query('grupo');
        $permisosAsignados = $this->permisosDesdeQuery($request);
        $supervisorId = $request->query('supervisor_id');

        $supervisor = $supervisorId ? User::find($supervisorId) : null;
        $supervisorNombre = $supervisor
            ? trim("{$supervisor->name} {$supervisor->apellido_paterno}")
            : null;

        $expiresAt = $request->query('expires')
            ? Carbon::createFromTimestamp((int) $request->query('expires'))
            : now()->addHours(48);

        $registroAction = URL::temporarySignedRoute(
            'registro.store',
            $expiresAt,
            array_filter([
                'rol' => $rolAsignado,
                'plantilla' => $plantillaOrigen,
                'supervisor_id' => $supervisorId,
                'permisos' => $permisosAsignados ?: null,
            ], fn ($v) => $v !== null && $v !== [])
        );

        session([
            'registro_invitacion' => [
                'rol' => $rolAsignado,
                'plantilla' => $plantillaOrigen,
                'supervisor_id' => $supervisorId,
                'permisos' => $permisosAsignados,
            ],
        ]);

        return Inertia::render('Auth/Registro', [
            'rol_asignado' => $rolAsignado,
            'plantilla_origen' => $plantillaOrigen,
            'supervisor_nombre' => $supervisorNombre,
            'permisos_asignados' => $permisosAsignados,
            'registro_action' => $registroAction,
            'catalogos' => ['sexos' => $sexos],
        ]);
    }

    public function almacenar(Request $request)
    {
        Log::channel('single')->info('--- INICIO DE REGISTRO GELIA ---');

        $rolFirmado = $request->query('rol') ?? session('registro_invitacion.rol');
        $plantillaFirmada = $request->query('plantilla') ?? $request->query('grupo') ?? session('registro_invitacion.plantilla');
        $supervisorFirmado = $request->query('supervisor_id') ?? session('registro_invitacion.supervisor_id');
        $permisosFirmados = $this->permisosDesdeQuery($request);
        if (empty($permisosFirmados)) {
            $permisosFirmados = session('registro_invitacion.permisos', []);
        }

        if (!$rolFirmado) {
            abort(403, 'Rol de seguridad no proporcionado en la firma.');
        }

        try {
            $datos = $request->validate([
                'name' => 'required|string|max:255',
                'apellido_paterno' => 'required|string|max:255',
                'apellido_materno' => 'nullable|string|max:255',
                'username' => 'required|string|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
                'telefono' => 'nullable|string',
                'fecha_nacimiento' => 'required|date',
                'catalogo_sexo_id' => 'nullable|exists:catalogo_sexos,id',
                'foto_perfil' => 'nullable|image|max:2048',
            ]);

            if (!Role::where('name', $rolFirmado)->exists()) {
                abort(403, 'Rol de seguridad inválido.');
            }

            if ($plantillaFirmada && !Role::where('name', $plantillaFirmada)->exists()) {
                abort(403, 'Plantilla de seguridad inválida.');
            }

            foreach ($permisosFirmados as $permiso) {
                if (!Permission::where('name', $permiso)->exists()) {
                    abort(403, "Permiso inválido en la firma: {$permiso}");
                }
            }

            DB::beginTransaction();

            $rutaFoto = null;
            if ($request->hasFile('foto_perfil')) {
                $rutaFoto = $request->file('foto_perfil')->store('perfiles', 'public');
            }

            $user = User::create([
                'name' => $datos['name'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'] ?? null,
                'username' => $datos['username'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
                'telefono' => $datos['telefono'] ?? null,
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'catalogo_sexo_id' => $datos['catalogo_sexo_id'] ?? null,
                'foto_perfil' => $rutaFoto,
            ]);

            $supervisor = null;
            if ($supervisorFirmado) {
                $supervisor = User::find($supervisorFirmado);
                if ($supervisor) {
                    $user->gerentes()->attach($supervisor->id);
                }
            }

            $user->syncRoles([$rolFirmado]);

            $plantillaPorPermiso = $this->plantillaPorPermiso($permisosFirmados, $plantillaFirmada);

            AsignarPermisosUsuarioService::asignar(
                $user,
                $permisosFirmados,
                $supervisor,
                $plantillaFirmada,
                $plantillaPorPermiso
            );

            DB::table('configuraciones_usuarios')->insert([
                'user_id' => $user->id,
                'tema_visual' => json_encode([
                    'modo' => 'light',
                    'color_nombre' => 'azul',
                    'alertas_prefs' => config('alertas.defaults'),
                ]),
                'dashboard_prefs' => json_encode(['ocultos' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            session()->forget('registro_invitacion');

            return redirect()->route('login')->with('success', 'Identidad operativa registrada correctamente.');

        } catch (ValidationException $e) {
            throw $e;

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::channel('single')->error('Fallo Crítico en Matriz de Registro: ' . $e->getMessage());

            return back()->withErrors([
                'error' => 'Falla en la matriz de registro: Revisa el archivo de logs para más detalles.',
            ]);
        }
    }

    private function permisosDesdeQuery(Request $request): array
    {
        $permisos = $request->query('permisos', []);

        if (is_string($permisos)) {
            return array_filter([$permisos]);
        }

        return is_array($permisos) ? array_values(array_filter($permisos)) : [];
    }

    /**
     * @param  array<string>  $permisos
     * @return array<string, string|null>
     */
    private function plantillaPorPermiso(array $permisos, ?string $plantillaOrigen): array
    {
        if (!$plantillaOrigen) {
            return [];
        }

        $permisosPlantilla = ValidarAsignacionPermisosService::permisosDePlantilla([$plantillaOrigen]);
        $map = [];

        foreach ($permisos as $permiso) {
            if (in_array($permiso, $permisosPlantilla, true)) {
                $map[$permiso] = $plantillaOrigen;
            }
        }

        return $map;
    }
}
