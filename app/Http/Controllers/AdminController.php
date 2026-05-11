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
use Illuminate\Support\Facades\Auth; // <-- Importante para el usuario en sesión

class AdminController extends Controller
{
    // --- VISTAS DE ENLACES Y CATÁLOGOS ---
    public function enlaces(): Response
    {
        return Inertia::render('Admin/Enlaces');
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
        ]);
    }

    // --- MÓDULO MATRICIAL DE USUARIOS (CON AISLAMIENTO DE DATOS Y PERMISOS) ---
    public function usuarios()
    {
        $user = Auth::user();
        $isGlobalAdmin = $user->hasRole(['Super Admin', 'Administrador']);

        if ($isGlobalAdmin) {
            $usuarios = User::with(['areas', 'departamentos', 'gerentes', 'roles', 'permissions'])->get();
            $departamentos = Departamento::with('areas')->where('activo', true)->get();
            $posiblesGerentes = User::role(['Super Admin', 'Administrador', 'Gerente'])
                                    ->select('id', 'name', 'apellido_paterno')
                                    ->get();
                                    
            $roles = Role::all();
            $todosLosPermisos = Permission::all();
        } else {
            $usuarios = User::whereHas('gerentes', function ($query) use ($user) {
                $query->where('gerente_id', $user->id);
            })->with(['areas', 'departamentos', 'gerentes', 'roles', 'permissions'])->get();

            $misAreasIds = $user->areas()->pluck('areas.id');
            $departamentos = $user->departamentos()->with(['areas' => function ($query) use ($misAreasIds) {
                $query->whereIn('areas.id', $misAreasIds);
            }])->where('activo', true)->get();

            $posiblesGerentes = collect([$user]); 
            
            // PREVENCIÓN DE ESCALADA DE PRIVILEGIOS
            // Un gerente no puede crear otros Administradores ni Super Admins
            $roles = Role::whereNotIn('name', ['Super Admin', 'Administrador'])->get();
            // Un gerente solo puede asignar los permisos que él mismo tiene en su sesión
            $todosLosPermisos = $user->getAllPermissions(); 
        }

        return Inertia::render('Admin/Usuarios', [
            'usuarios' => $usuarios,
            'departamentos' => $departamentos,
            'posiblesGerentes' => $posiblesGerentes,
            'roles' => $roles,
            'todosLosPermisos' => $todosLosPermisos,
            'sexos' => CatalogoSexo::all() ?? [],
        ]);
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
            'permisos_individuales' => 'array'
        ]);

        $usuario = User::create([
            'name' => $data['name'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'telefono' => $data['telefono'],
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'catalogo_sexo_id' => $data['catalogo_sexo_id'] ?? null,
        ]);

        if (isset($data['departamentos'])) $usuario->departamentos()->sync($data['departamentos']);
        if (isset($data['areas'])) $usuario->areas()->sync($data['areas']);
        if (isset($data['gerentes'])) $usuario->gerentes()->sync($data['gerentes']);
        
        $usuario->syncRoles($data['roles_asignados'] ?? []);
        $usuario->syncPermissions($data['permisos_individuales'] ?? []);

        return back()->with('success', 'Colaborador registrado exitosamente.');
    }

    public function updateUsuario(Request $request, User $user)
    {
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
            'permisos_individuales' => 'array'
        ]);

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

        $user->syncRoles($data['roles_asignados'] ?? []);
        $user->syncPermissions($data['permisos_individuales'] ?? []);

        return back()->with('success', 'Perfil actualizado.');
    }

    // --- MÓDULOS RESTANTES ---
    public function clientes(): Response
    {
        // 1. Cargamos clientes con sus relaciones para la tabla
        // 'tipo' es la relación que definimos en el modelo Cliente.php
        $clientes = Cliente::with(['vendedor', 'listaDescuento', 'tipo'])->get();

        // 2. Obtenemos las vendedoras (Asumiendo que usas Spatie Roles)
        // Si no usas roles, puedes usar User::all() o el filtro que prefieras
        $vendedores = User::all(); 

        // 3. Obtenemos el nuevo catálogo de tipos (Nuevo, Reactivado, etc.)
        // Usamos el bloque try-catch por si la tabla aún tiene detalles de migración
        try {
            $tipos_cliente = CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get();
        } catch (\Exception $e) {
            $tipos_cliente = []; // Evita que la app truene si hay error de base de datos
        }

        return Inertia::render('Admin/Clientes', [
            'clientes'      => $clientes,
            'vendedores'    => $vendedores,
            'tipos_cliente' => $tipos_cliente,
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

    public function comisiones(): Response {
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
}