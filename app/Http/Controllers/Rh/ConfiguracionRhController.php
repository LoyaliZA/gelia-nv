<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rh\UpdateConfiguracionRhRequest;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoBono;
use App\Models\CatalogoReglaIncidencia;
use App\Models\Departamento;
use App\Models\RhConfiguracion;
use App\Services\Rh\GenerarFolioColaboradorService;
use App\Services\Rh\GenerarFolioHorasExtraService;
use App\Services\Rh\GenerarFolioDeduccionService;
use App\Services\Rh\GenerarFolioPrestamoService;
use App\Services\Rh\RecalcularSalariosRhService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Services\Rh\GenerarFolioSalidaService;

class ConfiguracionRhController extends Controller
{
    public function index(): Response
    {
        $config = RhConfiguracion::obtener();

        return Inertia::render('Rh/Configuracion/Index', [
            'configuracion' => $config,
            'folioPreview' => app(GenerarFolioColaboradorService::class)->preview($config),
            'heFolioPreview' => app(GenerarFolioHorasExtraService::class)->ejecutar($config),
            'incFolioPreview' => app(GenerarFolioDeduccionService::class)->ejecutar($config),
            'preFolioPreview' => app(GenerarFolioPrestamoService::class)->ejecutar($config),
            'salFolioPreview' => app(GenerarFolioSalidaService::class)->ejecutar($config),
            'puestos' => CatalogoPuesto::with('bonos')->orderBy('nombre')->get(),
            'bonos' => CatalogoBono::withCount('colaboradores')->orderBy('nombre')->get(),
            'reglasIncidencia' => CatalogoReglaIncidencia::with([
                'bono',
                'departamentosAplicables',
                'areasAplicables',
                'departamentosVisibilidad',
                'areasVisibilidad',
            ])->orderBy('nombre')->get(),
            'departamentos' => Departamento::where('activo', true)->with('areas')->orderBy('nombre')->get(),
            'colaboradores' => \App\Models\RhColaborador::where('activo', true)->with(['departamento', 'area'])->get(),
            'turnos' => \App\Models\CatalogoTurno::orderBy('nombre')->get(),
        ]);
    }

    public function update(
        UpdateConfiguracionRhRequest $request,
        RecalcularSalariosRhService $recalcularService,
    ): RedirectResponse {
        $config = RhConfiguracion::obtener();
        $diasAnterior = $config->dias_periodo_pago;

        $config->update([
            'folio_prefijo' => strtoupper(trim($request->folio_prefijo)),
            'folio_separador' => $request->folio_separador,
            'folio_padding' => $request->folio_padding,
            'folio_incluir_anio' => $request->boolean('folio_incluir_anio'),
            'dias_periodo_pago' => $request->dias_periodo_pago,
            'decimales_salario_minuto' => $request->decimales_salario_minuto,
            'he_folio_prefijo' => strtoupper(trim($request->he_folio_prefijo)),
            'he_folio_padding' => $request->he_folio_padding,
            'he_multiplicador_pago' => $request->he_multiplicador_pago,
            'he_minutos_minimos' => $request->he_minutos_minimos,
            'he_tarifa_hora_fija' => $request->he_tarifa_hora_fija,
            'he_usar_tarifa_fija' => $request->boolean('he_usar_tarifa_fija'),
            'he_gracia_minutos_despues_salida' => $request->he_gracia_minutos_despues_salida,
            'inc_folio_prefijo' => strtoupper(trim($request->inc_folio_prefijo)),
            'inc_folio_padding' => $request->inc_folio_padding,
            'pre_folio_prefijo' => strtoupper(trim($request->pre_folio_prefijo)),
            'pre_folio_padding' => $request->pre_folio_padding,
            'sal_folio_prefijo' => strtoupper(trim($request->sal_folio_prefijo)),
            'sal_folio_padding' => $request->sal_folio_padding,
        ]);

        $mensaje = 'Configuración de RH actualizada correctamente.';
        $recalcular = $request->boolean('recalcular_salarios');

        if ($recalcular || (int) $diasAnterior !== (int) $config->dias_periodo_pago) {
            $total = $recalcularService->ejecutar($config);
            $mensaje .= " Se recalcularon {$total} colaboradores.";
        }

        return back()->with('success', $mensaje);
    }

    public function previewFolio(Request $request, GenerarFolioColaboradorService $generarFolio): JsonResponse
    {
        $datos = $request->validate([
            'folio_prefijo' => 'required|string|max:20',
            'folio_separador' => 'required|string|max:5',
            'folio_padding' => 'required|integer|min:1|max:12',
            'folio_incluir_anio' => 'nullable|boolean',
        ]);

        $config = RhConfiguracion::obtener()->replicate();
        $config->fill([
            'folio_prefijo' => strtoupper(trim($datos['folio_prefijo'])),
            'folio_separador' => $datos['folio_separador'],
            'folio_padding' => $datos['folio_padding'],
            'folio_incluir_anio' => filter_var($datos['folio_incluir_anio'] ?? false, FILTER_VALIDATE_BOOL),
        ]);

        return response()->json([
            'folio_preview' => $generarFolio->preview($config),
        ]);
    }

    public function updatePeriodoActual(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'dias_periodo' => 'required|integer|min:1',
        ]);

        $config = RhConfiguracion::obtener();
        $config->update([
            'periodo_actual_inicio' => $datos['fecha_inicio'],
            'periodo_actual_fin' => $datos['fecha_fin'],
            'dias_periodo_pago' => $datos['dias_periodo'],
        ]);

        return back()->with('success', 'Periodo de pago actual configurado correctamente.');
    }

    public function avanzarPeriodo(Request $request): RedirectResponse
    {
        $config = RhConfiguracion::obtener();
        
        if ($config->periodo_actual_fin) {
            $nuevoInicio = \Carbon\Carbon::parse($config->periodo_actual_fin)->addDay();
            $dias = $config->dias_periodo_pago ?? 15;
            $nuevoFin = $nuevoInicio->copy()->addDays($dias - 1);
            
            $config->update([
                'periodo_actual_inicio' => $nuevoInicio->toDateString(),
                'periodo_actual_fin' => $nuevoFin->toDateString(),
            ]);
        }

        return back()->with('success', 'El periodo global ha avanzado al siguiente bloque de días.');
    }
}
