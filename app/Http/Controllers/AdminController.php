<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Cliente;
use App\Models\CatalogoListaDescuento;
use App\Models\HistorialMontoCliente;
use App\Models\CatalogoProceso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoTipoCliente;
use App\Models\TabuladorComision;
use Illuminate\Support\Facades\DB;
use App\Services\Clientes\ImportarClientesWizerpService;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Departamento;
use App\Models\Area;
use App\Models\CatalogoSexo;
use App\Models\CatalogoZonaEntrega;
use App\Models\CatalogoHorarioEntrega;
use App\Models\CatalogoBanco;
use App\Models\CatalogoTipoActivo;
use App\Models\CatalogoPorcentajeEscalonamientoLista;
use App\Models\CatalogoPorcentajeListadoLista;
use Illuminate\Support\Facades\Auth; // <-- Importante para el usuario en sesión
use App\Services\Permisos\AsignarPermisosUsuarioService;
use App\Services\Permisos\ValidarAsignacionPermisosService;

class AdminController extends Controller
{
    // --- VISTAS DE ENLACES Y CATÁLOGOS ---
    public function enlaces(): Response
    {
        $user = Auth::user();
        $esSuperAdmin = ValidarAsignacionPermisosService::esSuperAdmin($user);
        $permisosUsuario = ValidarAsignacionPermisosService::permisosDelUsuario($user);

        $rolesQuery = Role::with('permissions')->whereNotIn('name', ['Super Admin']);

        if (!$esSuperAdmin && $user->hasRole('Gerente')) {
            $rolesQuery->whereNotIn('name', ['Administrador']);
        }

        $roles = ValidarAsignacionPermisosService::filtrarRolesAsignables(
            $rolesQuery->get(),
            $user
        );

        $todosLosPermisos = $esSuperAdmin
            ? Permission::all()
            : Permission::whereIn('name', $permisosUsuario)->get();

        return Inertia::render('Admin/Enlaces', [
            'roles' => $roles,
            'todosLosPermisos' => $todosLosPermisos,
            'permisosUsuario' => $permisosUsuario,
            'esSuperAdmin' => $esSuperAdmin,
        ]);
    }

    public function catalogos(): Response
    {
        return Inertia::render('Admin/Catalogos', [
            'procesos'      => CatalogoProceso::all(),
            'listas'        => CatalogoListaDescuento::all(),
            'estados'       => CatalogoEstadoSolicitud::all(),
            'departamentos' => Departamento::orderBy('nombre')->get(),
            'areas'         => Area::with('departamento')->orderBy('nombre')->get(),
            'tipos_cliente' => CatalogoTipoCliente::orderBy('nombre')->get(), // <-- Añadido
            'zonas_entrega' => CatalogoZonaEntrega::orderBy('nombre')->get(), // <-- Añadido
            'horarios_entrega' => CatalogoHorarioEntrega::with('zona')->get(),
            'porcentajes_escalonamiento' => CatalogoPorcentajeEscalonamientoLista::with('listaDescuento')->get(),
            'porcentajes_listado' => CatalogoPorcentajeListadoLista::with('listaDescuento')->get(),
            'bancos' => CatalogoBanco::orderBy('nombre')->get(),
            'tipos_activo' => CatalogoTipoActivo::orderBy('nombre')->get(),
        ]);
    }

    // --- MÓDULO MATRICIAL DE USUARIOS (CON AISLAMIENTO DE DATOS Y PERMISOS) ---
    public function usuarios()
    {
        $user = Auth::user();
        $isGlobalAdmin = $user->hasRole(['Super Admin', 'Administrador']);

        $relaciones = [
            'areas',
            'departamentos',
            'gerentes',
            'roles',
            'permissions',
            'permisoProcedencia.permission',
            'permisoProcedencia.asignadoPor',
        ];

        if ($isGlobalAdmin) {
            $queryUsuarios = User::with($relaciones);
            $departamentos = Departamento::with('areas')->where('activo', true)->get();
            $posiblesGerentes = User::role(['Super Admin', 'Administrador', 'Gerente'])
                ->select('id', 'name', 'apellido_paterno')
                ->get();

            $roles = Role::with('permissions')->get();
            $rolesConfig = Role::with('permissions')->where('name', '!=', 'Super Admin')->get();
            $todosLosPermisos = Permission::all();
            $catalogoPermisos = $todosLosPermisos;
        } else {
            $queryUsuarios = User::whereHas('gerentes', function ($query) use ($user) {
                $query->where('gerente_id', $user->id);
            })->with($relaciones);

            $misAreasIds = $user->areas()->pluck('areas.id');
            $departamentos = $user->departamentos()->with(['areas' => function ($query) use ($misAreasIds) {
                $query->whereIn('areas.id', $misAreasIds);
            }])->where('activo', true)->get();

            $posiblesGerentes = collect([$user]);

            // PREVENCIÓN DE ESCALADA DE PRIVILEGIOS
            // Un gerente no puede crear otros Administradores ni Super Admins
            $roles = Role::with('permissions')->whereNotIn('name', ['Super Admin', 'Administrador'])->get();
            $rolesConfig = Role::with('permissions')->whereNotIn('name', ['Super Admin', 'Administrador'])->get();
            $permisosAsignador = ValidarAsignacionPermisosService::permisosDelUsuario($user);
            $todosLosPermisos = Permission::whereIn('name', $permisosAsignador)->orderBy('name')->get();
            $catalogoPermisos = Permission::orderBy('name')->get(['id', 'name']);
        }

        $usuariosPaginados = $this->paginarUsuarios($queryUsuarios);

        return Inertia::render('Admin/Usuarios', [
            'usuarios' => $usuariosPaginados,
            'filtros' => [
                'busqueda' => trim((string) request('busqueda', '')),
            ],
            'departamentos' => $departamentos,
            'posiblesGerentes' => $posiblesGerentes,
            'roles' => $roles,
            'rolesConfig' => $rolesConfig,
            'todosLosPermisos' => $todosLosPermisos,
            'catalogoPermisos' => $catalogoPermisos ?? $todosLosPermisos,
            'sexos' => CatalogoSexo::all() ?? [],
            'esSuperAdmin' => $user->hasRole('Super Admin'),
            'permisosUsuario' => ValidarAsignacionPermisosService::permisosDelUsuario($user),
        ]);
    }

    /**
     * Actualiza los permisos heredados de un rol/grupo. Solo Super Admin.
     */
    public function updateRolePermisosHerencia(Request $request, Role $role)
    {
        if (!Auth::user()->hasRole('Super Admin')) {
            abort(403, 'Solo Super Admin puede modificar permisos heredados.');
        }

        if ($role->name === 'Super Admin') {
            abort(403, 'No se puede modificar la herencia del rol Super Admin.');
        }

        $data = $request->validate([
            'permisos_heredados' => 'nullable|array',
            'permisos_heredados.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($data['permisos_heredados'] ?? []);

        return back()->with('success', "Plantilla del rol «{$role->name}» actualizada.");
    }

    /**
     * Crea un grupo predefinido (rol con prefijo Grupo:). Solo Super Admin.
     */
    public function storeGrupoPredefinido(Request $request)
    {
        if (!Auth::user()->hasRole('Super Admin')) {
            abort(403, 'Solo Super Admin puede crear grupos predefinidos.');
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'permisos_heredados' => 'nullable|array',
            'permisos_heredados.*' => 'exists:permissions,name',
        ]);

        $nombreGrupo = str_starts_with($data['nombre'], 'Grupo:')
            ? $data['nombre']
            : 'Grupo: ' . trim($data['nombre']);

        if (Role::where('name', $nombreGrupo)->exists()) {
            return back()->withErrors(['nombre' => 'Ya existe un grupo con ese nombre.']);
        }

        $rol = Role::create([
            'name' => $nombreGrupo,
            'guard_name' => 'web',
        ]);

        $rol->syncPermissions($data['permisos_heredados'] ?? []);

        return back()->with('success', "Grupo «{$nombreGrupo}» creado correctamente.");
    }

    public function storeUsuario(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string',
            'apellido_materno' => 'nullable|string',
            'username' => 'required|string|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'telefono' => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'catalogo_sexo_id' => 'nullable|exists:catalogo_sexos,id',
            'departamentos' => 'nullable|array',
            'areas' => 'nullable|array',
            'gerentes' => 'nullable|array',
            'roles_asignados' => 'array',
            'permisos_individuales' => 'array',
            'plantilla_origen' => 'nullable|string|max:100',
            'plantilla_por_permiso' => 'nullable|array',
        ]);

        $rolesJerarquicos = collect($data['roles_asignados'] ?? [])
            ->reject(fn ($r) => str_contains($r, 'Grupo:'))
            ->values()
            ->all();

        ValidarAsignacionPermisosService::assertPuedeAsignar(
            Auth::user(),
            [],
            $data['permisos_individuales'] ?? []
        );

        $usuario = User::create([
            'name' => $data['name'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'catalogo_sexo_id' => $data['catalogo_sexo_id'] ?? null,
        ]);

        if (isset($data['departamentos'])) $usuario->departamentos()->sync($data['departamentos']);
        if (isset($data['areas'])) $usuario->areas()->sync($data['areas']);
        if (isset($data['gerentes'])) $usuario->gerentes()->sync($data['gerentes']);

        $usuario->syncRoles($rolesJerarquicos);

        AsignarPermisosUsuarioService::asignar(
            $usuario,
            $data['permisos_individuales'] ?? [],
            Auth::user(),
            $data['plantilla_origen'] ?? null,
            $data['plantilla_por_permiso'] ?? null
        );

        return back()->with('success', 'Colaborador registrado exitosamente.');
    }

    public function updateUsuario(Request $request, User $user)
    {
        $asignador = Auth::user();

        $data = $request->validate([
            'name' => 'required|string',
            'apellido_paterno' => 'required|string',
            'apellido_materno' => 'nullable|string',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'telefono' => 'nullable|string',
            'fecha_nacimiento' => 'nullable|date',
            'catalogo_sexo_id' => 'nullable|exists:catalogo_sexos,id',
            'departamentos' => 'nullable|array',
            'areas' => 'nullable|array',
            'gerentes' => 'nullable|array',
            'roles_asignados' => 'array',
            'permisos_individuales' => 'array',
            'plantilla_origen' => 'nullable|string|max:100',
            'plantilla_por_permiso' => 'nullable|array',
        ]);

        $rolesJerarquicos = collect($data['roles_asignados'] ?? [])
            ->reject(fn ($r) => str_contains($r, 'Grupo:'))
            ->values()
            ->all();

        $user->loadMissing('permissions');
        $permisosSolicitados = $data['permisos_individuales'] ?? [];
        $permisosFinales = ValidarAsignacionPermisosService::resolverPermisosActualizacion(
            $asignador,
            $user,
            $permisosSolicitados
        );
        $protegidos = ValidarAsignacionPermisosService::permisosProtegidosParaAsignador($asignador, $user);
        $permisosGestionables = collect($permisosSolicitados)
            ->intersect(ValidarAsignacionPermisosService::permisosDelUsuario($asignador))
            ->diff($protegidos)
            ->values()
            ->all();

        ValidarAsignacionPermisosService::assertPuedeAsignar(
            $asignador,
            [],
            $permisosGestionables
        );

        $data['permisos_individuales'] = $permisosFinales;

        $user->update([
            'name' => $data['name'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'catalogo_sexo_id' => $data['catalogo_sexo_id'] ?? null,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->departamentos()->sync($data['departamentos'] ?? []);
        $user->areas()->sync($data['areas'] ?? []);
        $user->gerentes()->sync($data['gerentes'] ?? []);

        $user->syncRoles($rolesJerarquicos);

        AsignarPermisosUsuarioService::asignar(
            $user,
            $permisosFinales,
            $asignador,
            $data['plantilla_origen'] ?? null,
            $data['plantilla_por_permiso'] ?? null,
            ValidarAsignacionPermisosService::esSuperAdmin($asignador) ? null : $permisosGestionables
        );

        return back()->with('success', 'Perfil actualizado.');
    }

    // --- MÓDULOS RESTANTES ---
    public function clientes(): Response
    {
        // 1. Cargamos clientes con sus relaciones para la tabla
        $clientes = Cliente::with(['vendedor', 'listaDescuento', 'tipo'])->get();

        // 2. Obtenemos las vendedoras
        $vendedores = User::all();

        // 3. Obtenemos el catálogo de tipos de cliente
        try {
            $tipos_cliente = CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get();
        } catch (\Exception $e) {
            $tipos_cliente = []; 
        }

        // 4. Obtenemos el catálogo de listas de descuento activas (LO NUEVO)
        $listas = CatalogoListaDescuento::where('activo', true)
            ->orderBy('monto_requerido', 'desc')
            ->get();

        // 5. Enviamos todo a la vista de React
        return Inertia::render('Admin/Clientes', [
            'clientes'      => $clientes,
            'vendedores'    => $vendedores,
            'tipos_cliente' => $tipos_cliente,
            'listas'        => $listas, // <-- INYECTADO PARA EL MODAL 360
        ]);
    }

    public function importarClientes(Request $request, ImportarClientesWizerpService $importadorService)
    {
        Gate::authorize('clientes.carga_masiva'); // <-- Se actualizó al permiso atómico real

        $request->validate(['archivo' => 'required|mimes:csv,txt']);

        try {
            $importadorService->ejecutar($request->file('archivo'));
            return back()->with('success', 'Base de datos de clientes actualizada correctamente.');
        } catch (\Exception $e) {
            return back()->withErrors(['archivo' => 'Error procesando archivo. Verifique el formato de las cabeceras. Detalles: ' . $e->getMessage()]);
        }
    }

    public function historialCliente(Cliente $cliente)
    {
        return response()->json($cliente->historialMontos()->get());
    }

    public function comisiones(): Response
    {
        $tabulador = TabuladorComision::with('proceso')->get();
        return Inertia::render('Admin/Comisiones', ['tabulador' => $tabulador]);
    }

    public function actualizarComision(Request $request, $id)
    {
        $request->validate([
            'monto_comision' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);

        $comision = TabuladorComision::findOrFail($id);
        $comision->update($request->only(['monto_comision', 'activo']));

        return back()->with('success', 'Tabulador actualizado exitosamente.');
    }

    public function marcarNotificacionLeida($id)
    {
        $notificacion = auth()->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();

        return back();
    }

    public function limpiarNotificaciones()
    {
        auth()->user()->notifications()->delete();

        return back();
    }

    private function paginarUsuarios($query): array
    {
        $busqueda = trim((string) request('busqueda', ''));
        if ($busqueda !== '') {
            $term = '%'.$busqueda.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('apellido_paterno', 'like', $term)
                    ->orWhere('apellido_materno', 'like', $term);
            });
        }

        $paginated = $query
            ->orderBy('name')
            ->orderBy('apellido_paterno')
            ->paginate(12)
            ->withQueryString();

        return [
            'data' => $paginated->getCollection()
                ->map(fn (User $u) => $this->serializarUsuarioConProcedencia($u))
                ->values()
                ->all(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'from' => $paginated->firstItem() ?? 0,
            'to' => $paginated->lastItem() ?? 0,
        ];
    }

    private function serializarUsuarioConProcedencia(User $usuario): array
    {
        $data = $usuario->toArray();
        $data['roles'] = $usuario->roles->toArray();
        $data['permissions'] = $usuario->permissions->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->values()->all();
        $data['departamentos'] = $usuario->departamentos->toArray();
        $data['areas'] = $usuario->areas->toArray();
        $data['gerentes'] = $usuario->gerentes->toArray();

        $data['permisos_procedencia'] = $usuario->permisoProcedencia->map(function ($proc) {
            $asignador = $proc->asignadoPor;

            return [
                'permiso' => $proc->permission?->name,
                'plantilla_origen' => $proc->plantilla_origen,
                'asignado_por' => $asignador ? [
                    'id' => $asignador->id,
                    'nombre' => trim("{$asignador->name} {$asignador->apellido_paterno}"),
                    'es_super_admin' => $asignador->hasRole('Super Admin'),
                    'es_administrador' => $asignador->hasRole('Administrador'),
                ] : null,
            ];
        })->filter(fn ($p) => $p['permiso'])->values()->all();

        return $data;
    }
}
