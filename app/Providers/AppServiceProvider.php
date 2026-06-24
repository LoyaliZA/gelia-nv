<?php

namespace App\Providers;

use App\Models\ApiAplicacion;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use App\Models\SolicitudTag;
use App\Models\SolicitudFactura;
use App\Observers\SolicitudTagObserver;
// Importaciones requeridas para el módulo de auditoría de catálogos
use App\Models\CatalogoListaDescuento;
use App\Models\RhColaborador;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoTipoFalta;
use App\Models\CatalogoBono;
use App\Models\CatalogoReglaIncidencia;
use App\Models\Producto;
use App\Models\Contabilidad\Pedido as ContabilidadPedido;
use App\Models\RhHorasExtra;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Observers\CatalogoListaDescuentoObserver;
use App\Listeners\EnviarWebPushTrasNotificacion;
use App\Listeners\PreventDestructiveDatabaseCommands;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

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
        // Cargar configuraciones del sistema desde la base de datos
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('configuraciones_sistema')) {
                // Ensure class is loaded so any lingering old cache can unserialize properly
                class_exists(\App\Models\ConfiguracionSistema::class);
                
                $configuraciones = \Illuminate\Support\Facades\Cache::rememberForever('configuraciones_sistema_globales', function () {
                    return \App\Models\ConfiguracionSistema::all()->toArray();
                });

                foreach ($configuraciones as $configuracion) {
                    if (is_object($configuracion)) {
                        $valor = $configuracion->valor ?? null;
                        $tipo = $configuracion->tipo ?? null;
                        $clave = $configuracion->clave ?? null;
                    } elseif (is_array($configuracion)) {
                        $valor = $configuracion['valor'] ?? null;
                        $tipo = $configuracion['tipo'] ?? null;
                        $clave = $configuracion['clave'] ?? null;
                    } else {
                        continue;
                    }

                    if (!$clave) continue;

                    // Castear booleanos y limpiar strings
                    if ($tipo === 'boolean') {
                        $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                    } elseif ($tipo === 'integer') {
                        $valor = (int) $valor;
                    } elseif (is_string($valor)) {
                        $valor = trim($valor);
                    }

                    config([$clave => $valor]);
                }
            }
        } catch (\Throwable $e) {
            // Ignorar errores durante la carga inicial o migraciones si la tabla no existe
            \Illuminate\Support\Facades\Log::error('AppServiceProvider config load error: ' . $e->getMessage());
        }
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

        Event::listen(NotificationSent::class, EnviarWebPushTrasNotificacion::class);
        Event::listen(CommandStarting::class, PreventDestructiveDatabaseCommands::class);

        Route::bind('factura', fn (string $value) => SolicitudFactura::where('id', $value)->orWhere('folio', $value)->firstOrFail());
        Route::bind('colaborador', fn (string $value) => RhColaborador::findOrFail($value));
        Route::bind('puesto', fn (string $value) => CatalogoPuesto::findOrFail($value));
        Route::bind('horasExtra', fn (string $value) => RhHorasExtra::findOrFail($value));
        Route::bind('tipoFalta', fn (string $value) => CatalogoTipoFalta::findOrFail($value));
        Route::bind('deduccion', fn (string $value) => RhDeduccion::findOrFail($value));
        Route::bind('incidencia', fn (string $value) => RhDeduccion::findOrFail($value));
        Route::bind('prestamo', fn (string $value) => RhPrestamoPagoFijo::findOrFail($value));
        Route::bind('bono', fn (string $value) => CatalogoBono::findOrFail($value));
        Route::bind('reglaIncidencia', fn (string $value) => CatalogoReglaIncidencia::findOrFail($value));
        Route::bind('producto', fn (string $value) => Producto::findOrFail($value));
        Route::bind('pedido', fn (string $value) => ContabilidadPedido::findOrFail($value));

        RateLimiter::for('api-externa', function (Request $request) {
            $aplicacion = $request->user();
            $limite = $aplicacion instanceof ApiAplicacion
                ? max(1, (int) $aplicacion->limite_por_minuto)
                : 30;

            $key = $aplicacion instanceof ApiAplicacion
                ? 'api-app:' . $aplicacion->id
                : 'api-ip:' . $request->ip();

            return Limit::perMinute($limite)->by($key);
        });
    }
}