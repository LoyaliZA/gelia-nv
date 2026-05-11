<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\CatalogoSexo;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RegistroController extends Controller
{
    /**
     * Función exclusiva para el Administrador.
     * Genera un enlace firmado válido por 48 horas.
     * El rol se pasa como parámetro de consulta para evitar errores de firma con caracteres especiales.
     */
    public function generarEnlaceInvitacion(Request $request)
    {
        // Validamos que el rol realmente exista en la base de datos
        $request->validate(['role_name' => 'required|exists:roles,name']);

        // Generamos la URL firmada. Pasamos el rol en el array de parámetros, 
        // lo que lo convierte en un query string (?rol=...)
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
    public function mostrarFormulario(Request $request)
    {
        // El middleware 'signed' en web.php ya hace la validación inicial, 
        // pero esta verificación manual asegura la integridad en la respuesta
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de registro de GELIANV es inválido o ha expirado.');
        }

        $sexos = CatalogoSexo::all();

        return Inertia::render('Auth/Registro', [
            // Leemos el rol directamente desde el parámetro de consulta
            'rol_asignado' => $request->query('rol'),
            'catalogos' => ['sexos' => $sexos]
        ]);
    }

    /**
     * Procesa la creación del usuario, le asigna el rol y genera su configuración visual base.
     */
    public function almacenar(Request $request)
    {
        // 1. Validamos ÚNICAMENTE los datos personales del usuario.
        // Hemos eliminado 'rol_asignado' de aquí por seguridad.
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
            'foto_perfil' => 'nullable|image|max:2048'
        ]);

        // 2. SEGURIDAD EXTREMA: Leemos el rol directamente de la URL firmada.
        // Como pasó el middleware 'signed', sabemos que este valor es 100% auténtico
        // y que no fue alterado por el usuario en el frontend.
        $rolFirmado = $request->query('rol');

        if (!$rolFirmado) {
            abort(403, 'Rol de seguridad no proporcionado en la firma.');
        }

        DB::beginTransaction();

        try {
            $rutaFoto = null;
            if ($request->hasFile('foto_perfil')) {
                $rutaFoto = $request->file('foto_perfil')->store('perfiles', 'public');
            }

            // 3. Crear al usuario en la base de datos
            $user = User::create([
                'name' => $datos['name'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'apellido_materno' => $datos['apellido_materno'],
                'username' => $datos['username'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
                'telefono' => $datos['telefono'],
                'fecha_nacimiento' => $datos['fecha_nacimiento'],
                'catalogo_sexo_id' => $datos['catalogo_sexo_id'] ?? null,
                'foto_perfil' => $rutaFoto,
            ]);

            // 4. Asignación automática de permisos mediante el rol FIRMADO
            $user->assignRole($rolFirmado);

            // 5. Registro de configuración visual para prevenir errores en AppLayout
            DB::table('configuraciones_usuarios')->insert([
                'user_id' => $user->id,
                'tema_visual' => json_encode(['modo' => 'light', 'color_nombre' => 'azul']),
                'dashboard_prefs' => json_encode(['ocultos' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('login')->with('success', 'Identidad operativa registrada correctamente. Ya puedes iniciar sesión.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error en la matriz de registro: ' . $e->getMessage()]);
        }
    }
}