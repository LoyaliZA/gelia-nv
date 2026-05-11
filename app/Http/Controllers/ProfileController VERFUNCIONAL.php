<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    /**
     * Muestra la vista de edición del perfil.
     */
    public function edit(Request $request): Response
    {
        // Cargamos el usuario con su área y departamento para la info institucional
        $user = $request->user()->load('areas.departamento');

        // Recuperamos la configuración visual de la tabla dedicada
        $configuracion = DB::table('configuraciones_usuarios')
            ->where('user_id', $user->id)
            ->first();

        $temaVisual = $configuracion ? json_decode($configuracion->tema_visual, true) : [];

        // Tomamos la primera área asignada
        $primeraArea = $user->areas->first();

        return Inertia::render('Profile/Edit', [
            'auth' => [
                'user' => array_merge($user->toArray(), [
                    // Formateo de fecha para el input date de React
                    'fecha_nacimiento' => $user->fecha_nacimiento 
                        ? \Carbon\Carbon::parse($user->fecha_nacimiento)->format('Y-m-d') 
                        : null,
                    'area' => $primeraArea ? [
                        'nombre'       => $primeraArea->nombre,
                        'departamento' => $primeraArea->departamento ? [
                            'nombre' => $primeraArea->departamento->nombre,
                        ] : null,
                    ] : null,
                ]),
            ],
            'tema_visual' => $temaVisual,
        ]);
    }

    /**
     * Actualiza la información del perfil y la configuración visual.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 1. VALIDACIÓN
        $datos = $request->validate([
            'name'                         => 'required|string|max:255',
            'apellido_paterno'             => 'required|string|max:255',
            'apellido_materno'             => 'nullable|string|max:255',
            'telefono'                     => 'nullable|string|max:20',
            'fecha_nacimiento'             => 'nullable|date',
            'password'                     => 'nullable|string|min:8|confirmed',
            'foto_perfil'                  => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_foto'                  => 'nullable|boolean',
            'archivo_fondo'                => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'remove_fondo'                 => 'nullable|boolean',
            'tema_visual'                  => 'nullable|array',
            'tema_visual.modo'             => 'nullable|string|in:dark,light',
            'tema_visual.color_nombre'     => 'nullable|string|max:50',
            'tema_visual.fondo_base'       => 'nullable|string|max:500',
            'tema_visual.fuente_principal' => 'nullable|string|max:50',
            'tema_visual.layout_sidebar'   => 'nullable|string|max:50',
            'tema_visual.efecto_cristal'   => 'nullable|boolean',
        ]);

        // 2. CAPTURA PRIORITARIA DEL TEMA VISUAL
        // Extraemos el array del request antes de modificar el array $datos
        $configVisual = $request->input('tema_visual', []);

        // 3. GESTIÓN DE CONTRASEÑA
        if (!empty($datos['password'])) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }
        unset($datos['password_confirmation']);

        // 4. GESTIÓN DE FOTO DE PERFIL
        if ($request->hasFile('foto_perfil')) {
            // Si el usuario sube una foto nueva, borramos la anterior y guardamos la nueva
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $datos['foto_perfil'] = $request->file('foto_perfil')->store('perfiles', 'public');
        } elseif ($request->boolean('remove_foto')) {
            // Si el usuario presionó el botón "Sin Foto", borramos la actual de la BD
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $datos['foto_perfil'] = null;
        } else {
            // IMPORTANTE: Si no se subió nada y no se pidió borrar, 
            // quitamos el campo del array para que NO se guarde como null en la BD.
            unset($datos['foto_perfil']);
        }

        // 5. GESTIÓN DE FONDO DE PANTALLA (Dentro del JSON)
        if ($request->hasFile('archivo_fondo')) {
            // Eliminar fondo físico anterior si existe
            $configActual = DB::table('configuraciones_usuarios')->where('user_id', $user->id)->value('tema_visual');
            if ($configActual) {
                $fondoAnterior = json_decode($configActual, true)['fondo_base'] ?? null;
                if ($fondoAnterior && str_starts_with($fondoAnterior, '/storage/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $fondoAnterior));
                }
            }
            $rutaFondo = $request->file('archivo_fondo')->store('fondos_usuarios', 'public');
            $configVisual['fondo_base'] = '/storage/' . $rutaFondo;
        } elseif ($request->boolean('remove_fondo')) {
            $configVisual['fondo_base'] = 'none';
        }

        // 6. LIMPIEZA DE CAMPOS EXTRAS
        // Quitamos todo lo que NO existe en la tabla 'users'
        unset(
            $datos['remove_foto'],
            $datos['archivo_fondo'],
            $datos['remove_fondo'],
            $datos['tema_visual']
        );

        // 7. ACTUALIZACIÓN EN TABLA 'users'
        $user->update($datos);

        // 8. PERSISTENCIA EN TABLA 'configuraciones_usuarios'
        // Guardamos el objeto JSON completo
        if (!empty($configVisual)) {
            DB::table('configuraciones_usuarios')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'tema_visual' => json_encode($configVisual),
                    'updated_at'  => now(),
                ]
            );
        }

        return back()->with('success', 'Perfil actualizado exitosamente.');
    }
}