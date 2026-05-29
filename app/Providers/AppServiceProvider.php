<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use App\Models\SolicitudTag;
use App\Models\SolicitudFactura;
use App\Observers\SolicitudTagObserver;
// Importaciones requeridas para el módulo de auditoría de catálogos
use App\Models\CatalogoListaDescuento;
use App\Observers\CatalogoListaDescuentoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Forzar HTTPS en entorno de producción para evitar contenido mixto
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // 2. PASE VIP UNIVERSAL: El Super Admin ignora todas las restricciones de Gate::authorize o @can
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // 3. Registro de Observadores
        SolicitudTag::observe(SolicitudTagObserver::class);
        
        // CONEXIÓN DEL NUEVO OBSERVADOR PARA CATÁLOGOS
        CatalogoListaDescuento::observe(CatalogoListaDescuentoObserver::class);

        Route::bind('factura', fn (string $value) => SolicitudFactura::where('id', $value)->orWhere('folio', $value)->firstOrFail());
    }
}