<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Redirige al usuario a su panel correspondiente según su rol.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('Vendedor')) {
            return Inertia::render('Dashboards/VendedorDashboard');
        }
        
        if ($user->hasRole('Encargado de TAGS')) {
            return Inertia::render('Dashboards/EncargadoDashboard');
        }
        
        if ($user->hasRole('Auxiliar')) {
            return Inertia::render('Dashboards/AuxiliarDashboard');
        }
        
        // Manejo explícito para múltiples roles administrativos
        if ($user->hasRole(['Super Admin', 'Administrador'])) {
            return Inertia::render('Dashboards/AdminDashboard');
        }

        // Fallback de seguridad estricto
        abort(403, 'Acceso denegado: No tienes un panel de control asignado.');
    }
}