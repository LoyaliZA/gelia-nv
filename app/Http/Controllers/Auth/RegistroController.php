<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\CatalogoSexo;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class RegistroController extends Controller
{
    /**
     * Función exclusiva para el Administrador.
     * Genera un enlace válido por 48 horas para un rol específico.
     */
    public function generarEnlaceInvitacion(Request $request)
    {
        $request->validate(['role_name' => 'required|exists:roles,name']);

        $url = URL::temporarySignedRoute(
            'registro.formulario', 
            now()->addHours(48), 
            ['rol' => $request->role_name]
        );

        return response()->json(['enlace' => $url]);
    }

    /**
     * Muestra el formulario de registro solo si la firma de la URL es válida.
     */
    public function mostrarFormulario(Request $request, $rol)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de registro es inválido o ha expirado.');
        }

        $sexos = CatalogoSexo::all();

        return Inertia::render('Auth/Registro', [
            'rol_asignado' => $rol,
            'catalogos' => ['sexos' => $sexos]
        ]);
    }

    /**
     * Procesa la creación del usuario y le asigna el rol.
     */
    public function almacenar(Request $request)
    {
        // El rol viene del input oculto que se pasa desde el enlace
        $datos = $request->validate([
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'telefono' => 'nullable|string',
            'edad' => 'nullable|integer|min:18',
            'catalogo_sexo_id' => 'required|exists:catalogo_sexos,id',
            'rol_asignado' => 'required|exists:roles,name',
            'foto_perfil' => 'nullable|image|max:2048' // Si se envía archivo
        ]);

        // Procesamiento de imagen si existe
        $rutaFoto = null;
        if ($request->hasFile('foto_perfil')) {
            $rutaFoto = $request->file('foto_perfil')->store('perfiles', 'public');
        }

        $user = User::create([
            'name' => $datos['name'],
            'apellido_paterno' => $datos['apellido_paterno'],
            'apellido_materno' => $datos['apellido_materno'],
            'username' => $datos['username'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'telefono' => $datos['telefono'],
            'edad' => $datos['edad'],
            'catalogo_sexo_id' => $datos['catalogo_sexo_id'],
            'foto_perfil' => $rutaFoto,
        ]);

        $user->assignRole($datos['rol_asignado']);

        // Opcional: Autenticar inmediatamente
        // Auth::login($user);

        return redirect()->route('login')->with('success', 'Registro completado. Por favor inicia sesión.');
    }
}