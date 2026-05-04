<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SolicitudTag;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Calcula y envía las estadísticas del dashboard basadas estrictamente en los permisos del usuario.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $estadisticas = [];

        // 1. Métricas operativas para Vendedoras
        if ($user->can('crear_solicitud')) {
            $estadisticas['mis_activas'] = SolicitudTag::where('vendedor_id', $user->id)
                ->where('catalogo_estado_solicitud_id', '!=', 2) // 2 = Respondida (Finalizada)
                ->count();

            $estadisticas['mis_pagos_pendientes'] = SolicitudTag::where('vendedor_id', $user->id)
                ->where('pago_confirmado', false)
                ->count();
        }

        // 2. Métricas de validación para el Auxiliar
        if ($user->can('verificar_auxiliar')) {
            // Cuentan las solicitudes en estado 1 (Pendiente) que requieren validación cruzada con Wizerp
            $estadisticas['auditorias_pendientes'] = SolicitudTag::where('catalogo_estado_solicitud_id', 1)->count(); 
        }

        // 3. Métricas de ejecución para la Encargada de TAGS
        if ($user->can('ejecutar_tags')) {
            // Cuentan las solicitudes en estado 3 (Verificada por auxiliar) listas para procesar
            $estadisticas['tags_pendientes'] = SolicitudTag::where('catalogo_estado_solicitud_id', 3)->count(); 
            
            // Cuentan las solicitudes en estado 4 (Incorrecta/Rebotada)
            $estadisticas['inconsistencias'] = SolicitudTag::where('catalogo_estado_solicitud_id', 4)->count(); 
        }

        // 4. Métricas Globales para Gerencia/Administración
        if ($user->can('ver_auditoria') || $user->can('gestionar_usuarios')) {
            $mesActual = now()->month;
            $anioActual = now()->year;

            $estadisticas['solicitudes_mes'] = SolicitudTag::whereMonth('created_at', $mesActual)
                                                           ->whereYear('created_at', $anioActual)
                                                           ->count();
                                                           
            $estadisticas['cotizado_global'] = SolicitudTag::whereMonth('created_at', $mesActual)
                                                           ->whereYear('created_at', $anioActual)
                                                           ->sum('monto_cotizado');
                                                           
            $estadisticas['usuarios_activos'] = User::count();
        }

        return Inertia::render('Dashboards/Index', [
            'estadisticas' => $estadisticas
        ]);
    }
}