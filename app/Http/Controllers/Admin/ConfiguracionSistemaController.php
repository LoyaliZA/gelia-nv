<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionSistema;
use App\Services\Auditoria\RegistrarAuditoriaConfiguracionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ConfiguracionSistemaController extends Controller
{
    private const CLAVES_SESION = [
        'session.lifetime',
        'session.expire_on_close',
        'sesiones.jornada_cierre_activo',
        'sesiones.jornada_hora_fin',
        'sesiones.jornada_zona_horaria',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $configuraciones = ConfiguracionSistema::orderBy('grupo')->orderBy('clave')->get();
        
        $grupos = $configuraciones->groupBy('grupo');

        return Inertia::render('Admin/ConfiguracionSistema/Index', [
            'configuracionesGrupos' => $grupos,
            'configuracionesRaw' => $configuraciones,
            'sessionDriver' => config('session.driver'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|unique:configuraciones_sistema,clave',
            'valor' => 'nullable|string',
            'tipo' => 'required|string|in:string,integer,boolean,json',
            'descripcion' => 'nullable|string',
            'grupo' => 'nullable|string',
        ]);

        ConfiguracionSistema::create($validated);

        Cache::forget('configuraciones_sistema_globales');

        return redirect()->back()->with('success', 'Configuración creada correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConfiguracionSistema $configuracion)
    {
        $validated = $request->validate([
            'valor' => 'nullable|string',
            'tipo' => 'required|string|in:string,integer,boolean,json',
            'descripcion' => 'nullable|string',
            'grupo' => 'nullable|string',
        ]);

        $valorAnterior = $configuracion->valor;

        $configuracion->update($validated);

        if (in_array($configuracion->clave, self::CLAVES_SESION, true)) {
            RegistrarAuditoriaConfiguracionService::ejecutar(
                'Sesiones',
                'Actualización de política de sesión',
                [
                    'clave' => $configuracion->clave,
                    'valor_anterior' => $valorAnterior,
                    'valor_nuevo' => $validated['valor'],
                ]
            );
        }

        Cache::forget('configuraciones_sistema_globales');

        return redirect()->back()->with('success', 'Configuración actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConfiguracionSistema $configuracion)
    {
        $configuracion->delete();

        Cache::forget('configuraciones_sistema_globales');

        return redirect()->back()->with('success', 'Configuración eliminada correctamente.');
    }

    /**
     * Envía un correo de prueba para verificar las configuraciones de mail actuales.
     */
    public function testMail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Mail::raw('Este es un correo de prueba enviado desde el módulo de configuración del sistema de Gelia. Si estás leyendo esto, la configuración es correcta.', function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Correo de Prueba - Sistema Gelia');
            });

            return redirect()->back()->with('success', 'Correo de prueba enviado correctamente a ' . $request->email);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Error al enviar el correo: ' . $e->getMessage()]);
        }
    }
}
