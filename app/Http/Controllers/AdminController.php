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
use App\Models\TabuladorComision;
use Illuminate\Support\Facades\DB;
use App\Services\Clientes\ImportarClientesWizerpService;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Departamento;

class AdminController extends Controller
{
    // --- VISTAS EXISTENTES ---
    public function enlaces(): Response
    {
        return Inertia::render('Admin/Enlaces');
    }

    public function catalogos(): Response
    {
        return Inertia::render('Admin/Catalogos', [
            'procesos' => CatalogoProceso::all(),
            'listas' => CatalogoListaDescuento::all(),
            'estados' => CatalogoEstadoSolicitud::all(),
        ]);
    }

    public function usuarios()
    {
        return Inertia::render('Admin/Usuarios', [
            'usuarios' => User::with(['area.departamento', 'roles', 'permissions'])->get(),
            'departamentos' => Departamento::with('areas')->where('activo', true)->get(),
            'roles' => Role::all(),
            'todosLosPermisos' => Permission::all(), // Enviamos esto para los permisos individuales
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
            'area_id' => 'required|exists:areas,id',
            'telefono' => 'nullable|string',
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
            'area_id' => $data['area_id'],
            'telefono' => $data['telefono'],
        ]);

        $usuario->syncRoles($data['roles_asignados']);
        $usuario->syncPermissions($data['permisos_individuales']);

        return back()->with('success', 'Colaborador registrado exitosamente.');
    }

    public function updateUsuario(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'apellido_paterno' => 'required|string',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'area_id' => 'required',
            'roles_asignados' => 'array',
            'permisos_individuales' => 'array'
        ]);

        $user->update($request->only(['name', 'apellido_paterno', 'apellido_materno', 'username', 'email', 'area_id', 'telefono']));
        
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Sincronización dual: Roles y Permisos directos
        $user->syncRoles($data['roles_asignados']);
        $user->syncPermissions($data['permisos_individuales']);

        return back()->with('success', 'Perfil actualizado.');
    }

    // --- MÓDULO DE CLIENTES (WIZERP) ---
    public function clientes(): Response
    {
        $clientes = Cliente::with('listaDescuento')->get();
        return Inertia::render('Admin/Clientes', ['clientes' => $clientes]);
    }

    public function importarClientes(Request $request, ImportarClientesWizerpService $importadorService)
    {
        Gate::authorize('cargar_clientes_masivo');

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
        // Retornará el historial para mostrarlo en el frontend (ej. un modal)
        return response()->json($cliente->historialMontos()->get());
    }

    // --- MÓDULO DE COMISIONES ---
    public function comisiones(): Response {
        $tabulador = TabuladorComision::with('proceso')->get();
        return Inertia::render('Admin/Comisiones', ['tabulador' => $tabulador]);
    }

    public function actualizarComision(Request $request, $id)
    {
        // Aquí deberías tener un permiso específico, ej: Gate::authorize('configurar_comisiones');

        $request->validate([
            'monto_comision' => 'required|numeric|min:0',
            'activo' => 'boolean'
        ]);

        $comision = TabuladorComision::findOrFail($id);
        $comision->update($request->only(['monto_comision', 'activo']));

        return back()->with('success', 'Tabulador actualizado exitosamente.');
    }

    // --- NOTIFICACIONES ---
    public function marcarNotificacionLeida($id)
    {
        $notificacion = auth()->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();
        return back();
    }
}
