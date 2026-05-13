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
use Illuminate\Support\Facades\Log; // <-- AGREGADO: Fachada de Logs
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class RegistroController extends Controller
{
    /**
     * Genera un enlace firmado válido por 48 horas.
     */
    public function generarEnlaceInvitacion(Request $request)
    {
        $request->validate(['role_name' => 'required|exists:roles,name']);

        $user = auth()->user();
        if (!$user->hasRole('Administrador') && in_array($request->role_name, ['Administrador', 'Gerente'])) {
            abort(403, 'No tienes la autoridad suficiente para generar enlaces de este nivel.');
        }

        $url = URL::temporarySignedRoute(
            'registro.formulario',
            now()->addHours(48),
            [
                'rol' => $request->role_name,
                'supervisor_id' => $user->id
            ]
        );

        return response()->json(['enlace' => $url]);
    }

    /**
     * Muestra el formulario de registro.
     */
    public function mostrarFormulario(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'El enlace de registro de GELIA es inválido o ha expirado.');
        }

        $sexos = CatalogoSexo::all();

        return Inertia::render('Auth/Registro', [
            'rol_asignado' => $request->query('rol'),
            'catalogos' => ['sexos' => $sexos]
        ]);
    }

    /**
     * Procesa la creación del usuario con trazabilidad inyectada.
     */
    public function almacenar(Request $request)
    {
        // 1. TRAZABILIDAD: Verificamos si la petición logra tocar el controlador
        Log::channel('single')->info('--- INICIO DE REGISTRO GELIA ---');
        Log::channel('single')->info('Parámetros de URL recibidos:', $request->query());
        Log::channel('single')->info('Datos POST recibidos (Sin contraseña):', $request->except(['password', 'foto_perfil']));
        Log::channel('single')->info('¿Contiene archivo de imagen?: ' . ($request->hasFile('foto_perfil') ? 'Sí' : 'No'));

        try {
            // 2. Validación de Datos Personales
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
                'foto_perfil' => 'nullable|image|max:2048' // 2048 KB = 2MB
            ]);

            Log::channel('single')->info('Validación superada correctamente.');

            // 3. Verificación de Seguridad de la URL
            $rolFirmado = $request->query('rol');
            $supervisorFirmado = $request->query('supervisor_id');

            if (!$rolFirmado) {
                Log::channel('single')->error('Fallo de seguridad: Rol no proporcionado en la firma.');
                abort(403, 'Rol de seguridad no proporcionado en la firma.');
            }

            DB::beginTransaction();

            $rutaFoto = null;
            if ($request->hasFile('foto_perfil')) {
                $rutaFoto = $request->file('foto_perfil')->store('perfiles', 'public');
                Log::channel('single')->info('Fotografía almacenada en: ' . $rutaFoto);
            }

            // 4. Creación en Base de Datos
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

            Log::channel('single')->info('Usuario insertado en DB con ID: ' . $user->id);

            // 5. Acoplamiento matricial
            if ($supervisorFirmado) {
                $user->gerentes()->attach($supervisorFirmado);
                Log::channel('single')->info('Acoplado al gerente ID: ' . $supervisorFirmado);
            }

            // 6. Asignación de Roles
            $user->assignRole($rolFirmado);
            Log::channel('single')->info('Rol asignado: ' . $rolFirmado);

            // 7. Configuración Visual Base
            DB::table('configuraciones_usuarios')->insert([
                'user_id' => $user->id,
                'tema_visual' => json_encode(['modo' => 'light', 'color_nombre' => 'azul']),
                'dashboard_prefs' => json_encode(['ocultos' => []]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            Log::channel('single')->info('--- REGISTRO COMPLETADO EXITOSAMENTE ---');

            return redirect()->route('login')->with('success', 'Identidad operativa registrada correctamente.');
            
        } catch (ValidationException $e) {
            // Manejo específico para errores de validación
            Log::channel('single')->warning('Fallo de Validación: ', $e->errors());
            throw $e; // Dejamos que Laravel maneje el retorno al frontend
            
        } catch (\Throwable $e) {
            // Manejo de errores críticos
            DB::rollBack();
            Log::channel('single')->error('Fallo Crítico en Matriz de Registro: ' . $e->getMessage() . ' en la línea ' . $e->getLine() . ' del archivo ' . $e->getFile());
            
            return back()->withErrors([
                'error' => 'Falla en la matriz de registro: Revisa el archivo de logs para más detalles.'
            ]);
        }
    }
}