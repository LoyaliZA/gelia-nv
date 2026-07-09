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
use App\Services\Clientes\RegistrarImportacionClienteService;
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
use App\Models\CatalogoCategoriaActivo;
use App\Models\CatalogoTipoActivo;
use App\Models\Sucursal;
use App\Models\CatalogoTipoAlmacen;
use App\Models\CatalogoMarcaProducto;
use App\Models\Almacen;
use App\Models\CatalogoCategoriaProducto;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoAlmacenSalida;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\CatalogoTipoGuiaPedido;
use App\Models\ControlPedidos\CatalogoEnvioTienda;
use App\Models\ControlPedidos\CatalogoZonaPedido;
use App\Models\CatalogoPorcentajeEscalonamientoLista;
use App\Models\CatalogoPorcentajeListadoLista;
use Illuminate\Support\Facades\Auth; // <-- Importante para el usuario en sesión
use App\Services\Permisos\AsignarPermisosUsuarioService;
use App\Services\Permisos\ValidarAsignacionPermisosService;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function panel(): Response
    {
        $user = Auth::user();

        if (! ValidarAsignacionPermisosService::esSuperAdmin($user)) {
            $permisosPanel = config('admin_modules', []);
            $tieneAcceso = collect($permisosPanel)->contains(
                fn (string $permiso) => $user->can($permiso)
            );

            abort_unless($tieneAcceso, 403);
        }

        return Inertia::render('Admin/Index');
    }

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
            'categorias_activo' => CatalogoCategoriaActivo::orderBy('nombre')->get(),
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'tipos_almacen' => CatalogoTipoAlmacen::orderBy('nombre')->get(),
            'marcas_producto' => CatalogoMarcaProducto::orderBy('nombre')->get(),
            'almacenes' => Almacen::with(['sucursal', 'tipoAlmacen'])->orderBy('nombre')->get(),
            'categorias_producto' => CatalogoCategoriaProducto::orderBy('nombre')->get(),
            'estatus_pedidos' => CatalogoEstatusPedido::orderBy('orden')->get(),
            'almacenes_salida' => CatalogoAlmacenSalida::orderBy('nombre')->get(),
            'paqueterias_pedido' => CatalogoPaqueteriaPedido::orderBy('nombre')->get(),
            'tipos_caja_pedido' => CatalogoTipoCajaPedido::orderBy('nombre')->get(),
            'tipos_guia_pedido' => CatalogoTipoGuiaPedido::orderBy('nombre')->get(),
            'zonas_pedido' => CatalogoZonaPedido::orderBy('nombre')->get(),
            'envios_tienda' => CatalogoEnvioTienda::orderBy('nombre')->get(),
        ]);
    }

    // --- MÓDULO MATRICIAL DE USUARIOS (CON AISLAMIENTO DE DATOS Y PERMISOS) ---
    public function usuarios()
    {
        $user = Auth::user();
        $isGlobalAdmin = $user->hasRole(['Super Admin', 'Administrador']);

        $relaciones = [
            'areas',
            'area.departamento',
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

        \App\Services\Auditoria\RegistrarAuditoriaConfiguracionService::ejecutar(
            'Roles y Permisos',
            'Actualización de plantilla de rol',
            [
                'descripcion' => "Se modificaron los permisos de la plantilla '{$role->name}'.",
                'rol_modificado' => $role->name,
                'nuevos_permisos' => $data['permisos_heredados'] ?? [],
            ]
        );

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

        \App\Services\Auditoria\RegistrarAuditoriaConfiguracionService::ejecutar(
            'Roles y Permisos',
            'Creación de grupo predefinido',
            [
                'descripcion' => "Se creó el grupo '{$nombreGrupo}' con sus permisos base.",
                'nombre_grupo' => $nombreGrupo,
                'permisos' => $data['permisos_heredados'] ?? [],
            ]
        );

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
            'area_id' => 'nullable|integer|exists:areas,id',
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

        $areaPrincipalId = $this->resolverAreaPrincipal($data);

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
            'area_id' => $areaPrincipalId,
        ]);

        if (isset($data['departamentos'])) $usuario->departamentos()->sync($data['departamentos']);
        if (isset($data['areas'])) $usuario->areas()->sync($data['areas']);
        if (isset($data['gerentes'])) $usuario->gerentes()->sync($data['gerentes']);

        $this->sincronizarAreaRhColaborador($usuario, $areaPrincipalId);

        $usuario->syncRoles($rolesJerarquicos);

        AsignarPermisosUsuarioService::asignar(
            $usuario,
            $data['permisos_individuales'] ?? [],
            Auth::user(),
            $data['plantilla_origen'] ?? null,
            $data['plantilla_por_permiso'] ?? null
        );

        $obtenerNombresAreas = fn($ids) => \App\Models\Area::whereIn('id', $ids)->pluck('nombre')->toArray();
        $obtenerNombresDeptos = fn($ids) => \App\Models\Departamento::whereIn('id', $ids)->pluck('nombre')->toArray();
        $nombreArea = fn($id) => $id ? \App\Models\Area::find($id)?->nombre : null;

        $detallesAuditoria = [
            'descripcion' => 'Se creó el usuario y se asignaron sus roles y permisos.',
            'roles' => [
                'asignados' => $rolesJerarquicos,
            ],
            'permisos' => [
                'actuales' => $data['permisos_individuales'] ?? [],
                'asignados' => $data['permisos_individuales'] ?? [],
                'retirados' => [],
            ],
        ];

        if ($areaPrincipalId) {
            $detallesAuditoria['area_principal'] = [
                'nueva' => $nombreArea($areaPrincipalId)
            ];
        }

        if (!empty($data['areas'])) {
            $detallesAuditoria['areas'] = [
                'asignadas' => $obtenerNombresAreas($data['areas'])
            ];
        }

        if (!empty($data['departamentos'])) {
            $detallesAuditoria['departamentos'] = [
                'asignados' => $obtenerNombresDeptos($data['departamentos'])
            ];
        }

        \App\Services\Auditoria\RegistrarAuditoriaConfiguracionService::ejecutar(
            'Usuarios',
            'Creación de usuario y asignación inicial',
            $detallesAuditoria,
            $usuario->id
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
            'area_id' => 'nullable|integer|exists:areas,id',
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

        $user->loadMissing(['permissions', 'areas', 'departamentos', 'roles']);
        
        // --- CAPTURA DE ESTADO ANTERIOR PARA AUDITORÍA ---
        $permisosActuales = $user->permissions->pluck('name')->toArray();
        $areasActualesIds = $user->areas->pluck('id')->toArray();
        $deptosActualesIds = $user->departamentos->pluck('id')->toArray();
        $rolesActuales = $user->roles->pluck('name')->toArray();
        $areaPrincipalActualId = $user->area_id;

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

        $areaPrincipalId = $this->resolverAreaPrincipal($data);

        $user->update([
            'name' => $data['name'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'catalogo_sexo_id' => $data['catalogo_sexo_id'] ?? null,
            'area_id' => $areaPrincipalId,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->departamentos()->sync($data['departamentos'] ?? []);
        $user->areas()->sync($data['areas'] ?? []);
        $user->gerentes()->sync($data['gerentes'] ?? []);

        $this->sincronizarAreaRhColaborador($user, $areaPrincipalId);

        $user->syncRoles($rolesJerarquicos);

        AsignarPermisosUsuarioService::asignar(
            $user,
            $permisosFinales,
            $asignador,
            $data['plantilla_origen'] ?? null,
            $data['plantilla_por_permiso'] ?? null,
            ValidarAsignacionPermisosService::esSuperAdmin($asignador) ? null : $permisosGestionables
        );

        // --- CÁLCULO DE DIFERENCIAS PARA AUDITORÍA ---
        $obtenerNombresAreas = fn($ids) => \App\Models\Area::whereIn('id', $ids)->pluck('nombre')->toArray();
        $obtenerNombresDeptos = fn($ids) => \App\Models\Departamento::whereIn('id', $ids)->pluck('nombre')->toArray();
        $nombreArea = fn($id) => $id ? \App\Models\Area::find($id)?->nombre : null;

        $areasAsignadasIds = array_diff($data['areas'] ?? [], $areasActualesIds);
        $areasRetiradasIds = array_diff($areasActualesIds, $data['areas'] ?? []);
        $deptosAsignadosIds = array_diff($data['departamentos'] ?? [], $deptosActualesIds);
        $deptosRetiradosIds = array_diff($deptosActualesIds, $data['departamentos'] ?? []);
        
        $permisosAsignados = array_diff($permisosFinales, $permisosActuales);
        $permisosRetirados = array_diff($permisosActuales, $permisosFinales);
        
        $rolesAsignados = array_diff($rolesJerarquicos, $rolesActuales);
        $rolesRetirados = array_diff($rolesActuales, $rolesJerarquicos);

        $detallesAuditoria = [
            'descripcion' => 'Se actualizaron los datos, roles o permisos del usuario.',
            'permisos' => [
                'actuales' => array_values($permisosFinales),
                'asignados' => array_values($permisosAsignados),
                'retirados' => array_values($permisosRetirados),
            ]
        ];

        if (!empty($rolesAsignados) || !empty($rolesRetirados)) {
            $detallesAuditoria['roles'] = [
                'actuales' => array_values($rolesJerarquicos),
                'asignados' => array_values($rolesAsignados),
                'retirados' => array_values($rolesRetirados),
            ];
        }

        if ($areaPrincipalActualId !== $areaPrincipalId) {
            $detallesAuditoria['area_principal'] = [
                'anterior' => $nombreArea($areaPrincipalActualId),
                'nueva' => $nombreArea($areaPrincipalId)
            ];
        }

        if (!empty($areasAsignadasIds) || !empty($areasRetiradasIds)) {
            $detallesAuditoria['areas'] = [
                'asignadas' => $obtenerNombresAreas($areasAsignadasIds),
                'retiradas' => $obtenerNombresAreas($areasRetiradasIds),
            ];
        }

        if (!empty($deptosAsignadosIds) || !empty($deptosRetiradosIds)) {
            $detallesAuditoria['departamentos'] = [
                'asignados' => $obtenerNombresDeptos($deptosAsignadosIds),
                'retirados' => $obtenerNombresDeptos($deptosRetiradosIds),
            ];
        }

        \App\Services\Auditoria\RegistrarAuditoriaConfiguracionService::ejecutar(
            'Usuarios',
            'Actualización de perfil y/o permisos',
            $detallesAuditoria,
            $user->id
        );

        return back()->with('success', 'Perfil actualizado.');
    }

    // --- MÓDULOS RESTANTES ---
    public function clientes(Request $request): Response
    {
        $query = Cliente::with(['vendedor', 'listaDescuento', 'tipo']);

        if ($request->filled('q')) {
            $termino = trim($request->q);
            $query->where(function ($sub) use ($termino) {
                if (preg_match('/^\d/', $termino)) {
                    $sub->where('numero_cliente', 'like', "{$termino}%");
                }
                $sub->orWhere('nombre', 'like', "{$termino}%")
                    ->orWhere('nombre', 'like', "%{$termino}%");
            });
        }

        if ($request->filled('lista_id')) {
            $query->where('lista_actual_id', $request->integer('lista_id'));
        }

        if ($request->tipo === 'heredados') {
            $query->where('es_heredado', true);
        } elseif ($request->tipo === 'directos') {
            $query->where('es_heredado', false);
        }

        if ($request->estado === 'inactivos') {
            $query->where('es_inactivo', true);
        } elseif ($request->estado === 'activos') {
            $query->where('es_inactivo', false);
        }

        $orden = $request->input('orden', 'numero_asc');
        match ($orden) {
            'numero_desc' => $query->ordenarPorNumeroCliente('desc'),
            'monto_asc'   => $query->orderBy('monto_venta_actual'),
            'monto_desc'  => $query->orderByDesc('monto_venta_actual'),
            default       => $query->ordenarPorNumeroCliente('asc'),
        };

        $vendedores = User::whereHas('roles', function ($q) {
            $q->where('name', 'colaborador');
        })
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        try {
            $tipos_cliente = CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get();
        } catch (\Exception $e) {
            $tipos_cliente = [];
        }

        $listas = CatalogoListaDescuento::where('activo', true)
            ->orderBy('monto_requerido', 'desc')
            ->get();

        return Inertia::render('Admin/Clientes', [
            'clientes'      => $query->paginate(25)->withQueryString(),
            'vendedores'    => $vendedores,
            'tipos_cliente' => $tipos_cliente,
            'listas'        => $listas,
            'filtros'       => $request->only(['q', 'lista_id', 'tipo', 'estado', 'orden', 'tab']),
            'puedeDescargarImportaciones' => $request->user()?->can('clientes.carga_masiva') ?? false,
        ]);
    }

    public function importarClientes(
        Request $request,
        ImportarClientesWizerpService $importadorService,
        RegistrarImportacionClienteService $registrarImportacion,
    ) {
        Gate::authorize('clientes.carga_masiva');

        $request->validate(['archivo' => 'required|mimes:csv,txt']);

        @set_time_limit(0);

        try {
            $importacion = $registrarImportacion->iniciar($request->file('archivo'));
            $resultado = $importadorService->ejecutar($request->file('archivo'), $importacion);
            $registrarImportacion->finalizar($importacion, $resultado['stats'] ?? []);

            $inactivos = $resultado['clientes_marcados_inactivos'] ?? 0;
            $mensaje = 'Base de datos de clientes actualizada correctamente.';
            if ($inactivos > 0) {
                $mensaje .= " {$inactivos} cliente(s) marcado(s) como inactivo(s).";
            }

            return back()
                ->with('success', $mensaje)
                ->with('reporte_importacion', $resultado['ascensos'] ?? []);
        } catch (\Throwable $e) {
            $mensajeSeguro = is_string($e->getMessage())
                ? (function_exists('mb_scrub') ? mb_scrub($e->getMessage(), 'UTF-8') : @iconv('UTF-8', 'UTF-8//IGNORE', $e->getMessage()))
                : 'Error desconocido';

            return back()->withErrors(['archivo' => 'Error procesando archivo. Verifique el formato de las cabeceras. Detalles: ' . ($mensajeSeguro ?: 'Error desconocido')]);
        }
    }

    public function historialCliente(Cliente $cliente)
    {
        return response()->json(
            $cliente->historialMontos()->latest()->paginate(50)
        );
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

    private function resolverAreaPrincipal(array $data): ?int
    {
        $areas = collect($data['areas'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values();

        $areaId = isset($data['area_id']) && $data['area_id'] !== ''
            ? (int) $data['area_id']
            : null;

        if ($areaId !== null && !$areas->contains($areaId)) {
            throw ValidationException::withMessages([
                'area_id' => 'El área principal debe estar incluida en las áreas asignadas.',
            ]);
        }

        if ($areaId === null && $areas->count() === 1) {
            return $areas->first();
        }

        if ($areas->count() > 1 && $areaId === null) {
            throw ValidationException::withMessages([
                'area_id' => 'Selecciona el área principal cuando el colaborador tiene varias áreas asignadas.',
            ]);
        }

        if ($areas->isEmpty()) {
            return null;
        }

        return $areaId;
    }

    private function sincronizarAreaRhColaborador(User $usuario, ?int $areaId): void
    {
        $usuario->loadMissing('perfilRh');

        if ($usuario->perfilRh && $areaId !== null) {
            $usuario->perfilRh->update(['area_id' => $areaId]);
        }
    }

    public function archivarUsuario(Request $request, User $user)
    {
        Gate::authorize('usuarios.archivar');

        $request->validate([
            'motivo' => 'required|string|max:1000'
        ]);

        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'No puedes archivar tu propia cuenta.']);
        }

        if ($user->hasRole('Super Admin')) {
            return back()->withErrors(['error' => 'No se puede archivar a un Super Admin.']);
        }

        $timestamp = time();
        $suffix = "_archived_{$timestamp}";

        $oldData = [
            'email' => $user->email,
            'username' => $user->username,
            'telefono' => $user->telefono,
        ];

        $user->update([
            'email' => $user->email . $suffix,
            'username' => $user->username . $suffix,
            'telefono' => $user->telefono ? $user->telefono . $suffix : null,
        ]);

        $user->delete();

        \App\Services\Auditoria\RegistrarAuditoriaConfiguracionService::ejecutar(
            'Usuarios',
            'Archivado de cuenta (Deshabilitación)',
            [
                'descripcion' => 'Se archivó el usuario, desvinculando sus credenciales de acceso para liberar los correos y teléfonos.',
                'motivo_archivado' => $request->motivo,
                'datos_anteriores' => $oldData,
                'usuario_afectado' => [
                    'id' => $user->id,
                    'nombre' => "{$user->name} {$user->apellido_paterno} {$user->apellido_materno}",
                ]
            ],
            $user->id
        );

        return back()->with('success', 'El colaborador ha sido archivado exitosamente. Se ha registrado el motivo en la auditoría y sus datos (correo, teléfono) han quedado libres para reasignación.');
    }
}
