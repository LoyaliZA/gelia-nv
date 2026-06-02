<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;
use App\Services\PersonalizacionCatalogoService;
use App\Services\UserSessionService;

class ProfileController extends Controller
{
    /**
     * Mi Perfil: datos generales del usuario (contenido por migrar desde preferencias).
     */
    public function index(Request $request, UserSessionService $userSessionService): Response
    {
        $user = $request->user()->load('areas.departamento');
        $primeraArea = $user->areas->first();

        return Inertia::render('Profile/MiPerfil', [
            'perfilUsuario' => [
                'fecha_nacimiento' => $user->fecha_nacimiento
                    ? Carbon::parse($user->fecha_nacimiento)->format('Y-m-d')
                    : null,
                'area' => $primeraArea ? [
                    'nombre'       => $primeraArea->nombre,
                    'departamento' => $primeraArea->departamento ? [
                        'nombre' => $primeraArea->departamento->nombre,
                    ] : null,
                ] : null,
            ],
            'sesiones' => $userSessionService->listarParaUsuario(
                $user->id,
                $request->session()->getId()
            ),
            'sesiones_soportadas' => UserSessionService::driverSoportaListado(),
        ]);
    }

    /**
     * Cierra todas las sesiones del usuario excepto la actual.
     */
    public function destroyOtherSessions(Request $request, UserSessionService $userSessionService): RedirectResponse
    {
        $eliminadas = $userSessionService->cerrarOtrasSesiones(
            $request->user()->id,
            $request->session()->getId()
        );

        $mensaje = $eliminadas > 0
            ? "Se cerraron {$eliminadas} sesión(es) en otros dispositivos."
            : 'No había otras sesiones activas.';

        return back()->with('success', $mensaje);
    }

    /**
     * Novedades: registro de actualizaciones de la plataforma.
     */
    public function novedades(Request $request): Response
    {
        return Inertia::render('Profile/Novedades');
    }

    /**
     * Preferencias: personalización del sistema (vista Profile/Edit).
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('areas.departamento');

        $configuracion = DB::table('configuraciones_usuarios')
            ->where('user_id', $user->id)
            ->first();

        $temaVisual = $configuracion ? json_decode($configuracion->tema_visual, true) : [];

        $primeraArea = $user->areas->first();

        return Inertia::render('Profile/Edit', [
            'tema_visual' => $temaVisual,
            'perfilUsuario' => [
                'fecha_nacimiento' => $user->fecha_nacimiento
                    ? Carbon::parse($user->fecha_nacimiento)->format('Y-m-d')
                    : null,
                'area' => $primeraArea ? [
                    'nombre'       => $primeraArea->nombre,
                    'departamento' => $primeraArea->departamento ? [
                        'nombre' => $primeraArea->departamento->nombre,
                    ] : null,
                ] : null,
            ],
        ]);
    }

    /**
     * Actualiza la información del perfil y la configuración visual sin sobrescribir el Dashboard.
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
            'tema_visual'                  => 'nullable|json',
            'tema_visual.modo'             => 'nullable|string|in:dark,light',
            'tema_visual.color_nombre'     => 'nullable|string|max:50',
            'tema_visual.fondo_base'       => 'nullable|string|max:500',
            'tema_visual.fuente_principal' => 'nullable|string|max:50',
            'tema_visual.escala_fuente'    => 'nullable|numeric|min:0.875|max:1.5',
            'tema_visual.layout_sidebar'   => 'nullable|string|max:50',
            'tema_visual.sidebar_modo'     => 'nullable|string|in:collapsed,expanded',
            'tema_visual.sidebar_posicion_fija' => 'nullable|string|in:left,right,top,bottom',
            'tema_visual.efecto_cristal'   => 'nullable|boolean',
            'firma'                        => 'nullable|string',
            'remove_firma'                 => 'nullable|boolean',
        ]);

        
        // 2. CAPTURA DE PREFERENCIAS VISUALES DEL REQUEST
        // Desempaquetamos la cadena de texto segura a un array asociativo
        $configVisual = $request->filled('tema_visual') 
            ? json_decode($request->input('tema_visual'), true) 
            : [];

        if (isset($configVisual['escala_fuente'])) {
            $escala = (float) $configVisual['escala_fuente'];
            $escala = round($escala / 0.0625) * 0.0625;
            $configVisual['escala_fuente'] = max(0.875, min(1.5, $escala));
        }

        if (isset($configVisual['alertas_prefs']) && is_array($configVisual['alertas_prefs'])) {
            $configVisual['alertas_prefs'] = $this->normalizarAlertasPrefs($configVisual['alertas_prefs']);
        }

        // 3. GESTIÓN DE CONTRASEÑA
        if (!empty($datos['password'])) {
            $datos['password'] = Hash::make($datos['password']);
        } else {
            unset($datos['password']);
        }
        unset($datos['password_confirmation']);

        // 4. GESTIÓN DE FOTO DE PERFIL
        if ($request->hasFile('foto_perfil')) {
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $datos['foto_perfil'] = $request->file('foto_perfil')->store('perfiles', 'public');
        } elseif ($request->boolean('remove_foto')) {
            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
            }
            $datos['foto_perfil'] = null;
        } else {
            unset($datos['foto_perfil']);
        }

        // 5. GESTIÓN DE FONDO DE PANTALLA (Inyectado en el JSON)
        if ($request->hasFile('archivo_fondo')) {
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

        // 5.5 GESTIÓN DE FIRMA DIGITAL (Para responsiva de activos)
        if ($request->filled('firma')) {
            $base64 = $request->input('firma');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                $data = substr($base64, strpos($base64, ',') + 1);
                $type = strtolower($type[1]);
                if (in_array($type, ['png', 'jpg', 'jpeg'], true)) {
                    $data = base64_decode($data);
                    if ($data !== false) {
                        if ($user->firma_ruta) {
                            Storage::disk('public')->delete($user->firma_ruta);
                        }
                        $filename = "perfiles/firmas/{$user->id}.{$type}";
                        Storage::disk('public')->put($filename, $data);
                        $datos['firma_ruta'] = $filename;
                    }
                }
            }
        } elseif ($request->boolean('remove_firma')) {
            if ($user->firma_ruta) {
                Storage::disk('public')->delete($user->firma_ruta);
            }
            $datos['firma_ruta'] = null;
        }

        // 6. LIMPIEZA PARA ACTUALIZACIÓN DE TABLA 'users'
        unset(
            $datos['remove_foto'],
            $datos['archivo_fondo'],
            $datos['remove_fondo'],
            $datos['tema_visual'],
            $datos['firma'],
            $datos['remove_firma']
        );

        // 7. ACTUALIZACIÓN EN TABLA 'users'
        $user->update($datos);

        // 8. PERSISTENCIA EN TABLA 'configuraciones_usuarios' (LÓGICA DE FUSIÓN CRÍTICA)
        if (!empty($configVisual)) {
            // A. Leemos lo que ya existe en la BD (incluyendo dashboard_ocultos)
            $configActualDB = DB::table('configuraciones_usuarios')->where('user_id', $user->id)->first();
            $temaActualDecodificado = $configActualDB ? json_decode($configActualDB->tema_visual, true) : [];

            // B. Fusionamos: Lo nuevo del perfil pisa los colores/fondo, pero respeta lo demás
            $temaFinalMerge = array_merge($temaActualDecodificado, $configVisual);

            // C. Guardamos el resultado combinado
            DB::table('configuraciones_usuarios')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'tema_visual' => json_encode($temaFinalMerge),
                    'updated_at'  => now(),
                ]
            );
        }

        return back()->with('success', 'Perfil actualizado exitosamente.');
    }

    /**
     * Normaliza y valida las preferencias de alertas del usuario.
     */
    private function normalizarAlertasPrefs(array $prefs): array
    {
        $defaults = config('alertas.defaults', []);
        $tonosValidos = PersonalizacionCatalogoService::tonoIdsValidos();
        $tiposValidos = array_keys($defaults['tipos'] ?? []);

        $canales = array_merge(
            $defaults['canales'] ?? [],
            array_intersect_key($prefs['canales'] ?? [], array_flip(['sonido', 'voz', 'escritorio', 'app']))
        );

        foreach (['sonido', 'voz', 'escritorio', 'app'] as $canal) {
            $canales[$canal] = filter_var($canales[$canal] ?? true, FILTER_VALIDATE_BOOLEAN);
        }

        $tonoId = $prefs['tono_id'] ?? ($defaults['tono_id'] ?? 'default');
        if (!in_array($tonoId, $tonosValidos, true)) {
            $tonoId = $defaults['tono_id'] ?? 'default';
        }

        $tipos = array_merge($defaults['tipos'] ?? [], $prefs['tipos'] ?? []);
        $tipos = array_intersect_key($tipos, array_flip($tiposValidos));
        foreach ($tipos as $tipo => $valor) {
            $tipos[$tipo] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        }

        $modosMensajeria = ['desactivado', 'solo_aviso', 'leer_mensaje'];
        $mensajeriaVoz = $prefs['mensajeria_voz'] ?? ($defaults['mensajeria_voz'] ?? 'solo_aviso');
        if (! in_array($mensajeriaVoz, $modosMensajeria, true)) {
            $mensajeriaVoz = $defaults['mensajeria_voz'] ?? 'solo_aviso';
        }

        if ($canales['voz'] === false) {
            $mensajeriaVoz = 'desactivado';
        }

        return [
            'canales'         => $canales,
            'tono_id'         => $tonoId,
            'mensajeria_voz'  => $mensajeriaVoz,
            'tipos'           => $tipos,
        ];
    }
}