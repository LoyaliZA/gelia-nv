<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhReciboNomina;
use App\Services\Rh\DesgloseReciboNominaService;
use App\Services\Rh\FiltrarColaboradoresGerenteService;
use App\Services\Rh\FirmarReciboDeduccionService;
use App\Services\Rh\FirmarReciboNominaService;
use App\Services\Rh\GenerarReciboIncidenciaService;
use App\Services\Rh\GenerarReciboNominaService;
use App\Support\RhReciboNombreArchivo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ReciboRhController extends Controller
{
    public function incidenciaVistaPrevia(
        RhDeduccion $deduccion,
        GenerarReciboIncidenciaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarRecibo($deduccion, $filtrarColaboradores);
        $this->exigirReciboIncidenciaFirmado($deduccion);

        $pdf = $generarService->individual($deduccion);

        return $pdf->stream($this->nombreIncidencia($deduccion));
    }

    public function incidenciaDescargar(
        RhDeduccion $deduccion,
        GenerarReciboIncidenciaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarRecibo($deduccion, $filtrarColaboradores);
        $this->exigirReciboIncidenciaFirmado($deduccion);

        $pdf = $generarService->individual($deduccion);

        return $pdf->download($this->nombreIncidencia($deduccion));
    }

    public function incidenciaFirmar(
        Request $request,
        RhDeduccion $deduccion,
        FirmarReciboDeduccionService $firmarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): RedirectResponse {
        abort_unless(
            Auth::user()->can('rh.recibos.generar') || Auth::user()->can('rh.incidencias.gerente.crear'),
            403,
        );

        $this->autorizarRecibo($deduccion, $filtrarColaboradores);

        $datos = $request->validate([
            'firma_gerente_data' => 'nullable|string',
            'firma_colaborador_data' => 'nullable|string',
        ]);

        $firmarService->ejecutar(Auth::user(), $deduccion, $datos);

        return back()->with('success', 'Recibo firmado correctamente.');
    }

    public function periodoVistaPrevia(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboNominaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        return $this->nominaVistaPrevia($request, $colaborador, $generarService, $filtrarColaboradores);
    }

    public function periodoDescargar(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboNominaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        return $this->nominaDescargar($request, $colaborador, $generarService, $filtrarColaboradores);
    }

    public function nominaVistaPrevia(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboNominaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $this->validarNomina($request);
        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);

        $this->exigirReciboNominaFirmado($colaborador, $fechaInicio, $fechaFin);

        $pdf = $generarService->porColaborador(
            $colaborador,
            $fechaInicio,
            $fechaFin,
            $datos['orientacion'] ?? 'portrait',
        );

        return $pdf->stream($this->nombreNomina($colaborador));
    }

    public function nominaDescargar(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboNominaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $this->validarNomina($request);
        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);

        $this->exigirReciboNominaFirmado($colaborador, $fechaInicio, $fechaFin);

        $pdf = $generarService->porColaborador(
            $colaborador,
            $fechaInicio,
            $fechaFin,
            $datos['orientacion'] ?? 'portrait',
        );

        return $pdf->download($this->nombreNomina($colaborador));
    }

    public function nominaDesglose(
        Request $request,
        RhColaborador $colaborador,
        DesgloseReciboNominaService $desgloseService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('rh.recibos.ver')
            || Auth::user()->can('rh.recibos.generar'),
            403,
        );

        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $this->validarNomina($request);
        $fechaInicio = Carbon::parse($datos['fecha_inicio']);
        $fechaFin = Carbon::parse($datos['fecha_fin']);

        return response()->json(
            $desgloseService->ejecutar($colaborador, $fechaInicio, $fechaFin),
        );
    }

    public function nominaFirmar(
        Request $request,
        RhColaborador $colaborador,
        FirmarReciboNominaService $firmarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): RedirectResponse {
        abort_unless(Auth::user()->can('rh.recibos.generar'), 403);

        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'orientacion' => 'nullable|in:portrait,landscape',
            'firma_colaborador_data' => 'required|string',
        ]);

        $firmarService->ejecutar(
            Auth::user(),
            $colaborador,
            Carbon::parse($datos['fecha_inicio']),
            Carbon::parse($datos['fecha_fin']),
            $datos['firma_colaborador_data'],
        );

        return back()->with('success', 'Recibo de nómina firmado correctamente.');
    }

    private function validarNomina(Request $request): array
    {
        return $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'orientacion' => 'nullable|in:portrait,landscape',
        ]);
    }

    private function exigirReciboNominaFirmado(
        RhColaborador $colaborador,
        Carbon $fechaInicio,
        Carbon $fechaFin,
    ): void {
        $firmado = RhReciboNomina::query()
            ->where('rh_colaborador_id', $colaborador->id)
            ->where('fecha_inicio', $fechaInicio->toDateString())
            ->where('fecha_fin', $fechaFin->toDateString())
            ->whereNotNull('firma_colaborador_path')
            ->exists();

        abort_unless($firmado, 403, 'El recibo debe firmarse antes de visualizarse o descargarse.');
    }

    private function exigirReciboIncidenciaFirmado(RhDeduccion $deduccion): void
    {
        abort_unless(
            ! empty($deduccion->firma_colaborador_path),
            403,
            'El recibo debe firmarse antes de visualizarse o descargarse.',
        );
    }

    private function nombreNomina(RhColaborador $colaborador): string
    {
        return RhReciboNombreArchivo::nomina($colaborador->nombre_completo, $colaborador->folio);
    }

    private function nombreIncidencia(RhDeduccion $deduccion): string
    {
        $deduccion->loadMissing('colaborador');
        $nombre = $deduccion->colaborador?->nombre_completo ?? 'colaborador';

        return RhReciboNombreArchivo::incidencia($nombre, $deduccion->folio);
    }

    private function autorizarRecibo(RhDeduccion $deduccion, FiltrarColaboradoresGerenteService $filtrarColaboradores): void
    {
        $usuario = Auth::user();

        abort_unless(
            $usuario->can('rh.recibos.ver')
            || $usuario->can('rh.recibos.generar')
            || $usuario->can('rh.incidencias.ver')
            || $usuario->can('rh.deducciones.ver')
            || $usuario->can('rh.incidencias.gerente.ver'),
            403,
        );

        $deduccion->loadMissing('colaborador');
        if ($deduccion->colaborador && ! $filtrarColaboradores->puedeAcceder($usuario, $deduccion->colaborador)) {
            abort(403, 'No tiene autorización para este recibo.');
        }
    }

    private function autorizarColaborador(RhColaborador $colaborador, FiltrarColaboradoresGerenteService $filtrarColaboradores): void
    {
        abort_unless(
            Auth::user()->can('rh.recibos.ver')
            || Auth::user()->can('rh.recibos.generar')
            || Auth::user()->can('rh.ver'),
            403,
        );

        if (! $filtrarColaboradores->puedeAcceder(Auth::user(), $colaborador)) {
            abort(403, 'No tiene autorización para este colaborador.');
        }
    }
}
