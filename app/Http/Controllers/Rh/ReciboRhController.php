<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Services\Rh\FiltrarColaboradoresGerenteService;
use App\Services\Rh\GenerarReciboIncidenciaService;
use App\Services\Rh\GenerarReciboPeriodoIncidenciasService;
use Carbon\Carbon;
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

        $pdf = $generarService->individual($deduccion);

        return $pdf->stream("Recibo_{$deduccion->folio}.pdf");
    }

    public function incidenciaDescargar(
        RhDeduccion $deduccion,
        GenerarReciboIncidenciaService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarRecibo($deduccion, $filtrarColaboradores);

        $pdf = $generarService->individual($deduccion);

        return $pdf->download("Recibo_{$deduccion->folio}.pdf");
    }

    public function periodoVistaPrevia(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboPeriodoIncidenciasService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $pdf = $generarService->porColaborador(
            $colaborador,
            Carbon::parse($datos['fecha_inicio']),
            Carbon::parse($datos['fecha_fin']),
        );

        return $pdf->stream("Recibo_Periodo_{$colaborador->folio}.pdf");
    }

    public function periodoDescargar(
        Request $request,
        RhColaborador $colaborador,
        GenerarReciboPeriodoIncidenciasService $generarService,
        FiltrarColaboradoresGerenteService $filtrarColaboradores,
    ): Response {
        $this->autorizarColaborador($colaborador, $filtrarColaboradores);

        $datos = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $pdf = $generarService->porColaborador(
            $colaborador,
            Carbon::parse($datos['fecha_inicio']),
            Carbon::parse($datos['fecha_fin']),
        );

        return $pdf->download("Recibo_Periodo_{$colaborador->folio}.pdf");
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
        if ($deduccion->colaborador && !$filtrarColaboradores->puedeAcceder($usuario, $deduccion->colaborador)) {
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

        if (!$filtrarColaboradores->puedeAcceder(Auth::user(), $colaborador)) {
            abort(403, 'No tiene autorización para este colaborador.');
        }
    }
}
