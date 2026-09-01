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
use App\Models\SolicitudTraspaso;
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
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\RhHorasExtra;
use App\Models\RhDeduccion;
use App\Models\RhPrestamoPagoFijo;
use App\Observers\CatalogoListaDescuentoObserver;
use App\Listeners\PreventDestructiveDatabaseCommands;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Contracts\PuntoVenta\ResuelveAlcancePdv::class,
            \App\Services\PuntoVenta\AlcancePdv::class
        );

        $this->app->singleton(\App\Services\GeliaAi\Acciones\AccionRegistry::class, function ($app) {
            return new \App\Services\GeliaAi\Acciones\AccionRegistry([
                $app->make(\App\Services\GeliaAi\Acciones\ImportarCostosAccion::class),
                $app->make(\App\Services\GeliaAi\Acciones\ImportarInventariosAccion::class),
                $app->make(\App\Services\GeliaAi\Acciones\GenerarListadoAccion::class),
            ]);
        });
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

                    // No pisar subject VAPID con URL de otro entorno (BD clonada).
                    // Los links/contact VAPID deben seguir APP_URL / WEBPUSH_VAPID_SUBJECT del .env.
                    if ($clave === 'webpush.vapid.subject') {
                        continue;
                    }

                    // Claves VAPID desde BD solo si .env no las trae (espejos/prod con config en UI).
                    if (in_array($clave, ['webpush.vapid.public_key', 'webpush.vapid.private_key'], true)) {
                        $actual = (string) config($clave, '');
                        if ($actual !== '' || $valor === '' || $valor === null) {
                            continue;
                        }
                    }

                    config([$clave => $valor]);
                }

                // Subject siempre alineado al entorno actual.
                $envSubject = env('WEBPUSH_VAPID_SUBJECT');
                config([
                    'webpush.vapid.subject' => (is_string($envSubject) && $envSubject !== '')
                        ? $envSubject
                        : (string) config('app.url'),
                ]);
            }
        } catch (\Throwable $e) {
            // Ignorar errores durante la carga inicial o migraciones si la tabla no existe
            \Illuminate\Support\Facades\Log::error('AppServiceProvider config load error: ' . $e->getMessage());
        }
        // 1. Forzar HTTPS cuando la app pública es https (o env production).
        // Evita links de paginación/Inertia en http:// detrás de Cloudflare/proxy → HttpNetworkError.
        $appUrl = (string) config('app.url');
        if (config('app.env') === 'production' || str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            if ($appUrl !== '') {
                URL::forceRootUrl(rtrim($appUrl, '/'));
            }
        }

        // 2. PASE VIP UNIVERSAL: El Super Admin ignora todas las restricciones de Gate::authorize o @can
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // Spatie cache (tabla `cache` en MySQL) sobrevive a sail down/up.
        // Si los IDs de permissions cambian y la caché no se limpia, can() falla para todos menos Super Admin.
        $this->app->booted(function () {
            try {
                $sample = \Spatie\Permission\Models\Permission::query()
                    ->orderByDesc('id')
                    ->first(['id', 'name', 'guard_name']);
                if (! $sample) {
                    return;
                }
                $fromCache = \Spatie\Permission\Models\Permission::findByName(
                    $sample->name,
                    $sample->guard_name
                );
                if ((int) $fromCache->id === (int) $sample->id) {
                    return;
                }
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                try {
                    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
                } catch (\Throwable) {
                }
            }
        });

        // 3. Registro de Observadores
        SolicitudTag::observe(SolicitudTagObserver::class);
        
        // CONEXIÓN DEL NUEVO OBSERVADOR PARA CATÁLOGOS
        CatalogoListaDescuento::observe(CatalogoListaDescuentoObserver::class);

        // EnviarWebPushTrasNotificacion se registra por discovery (App\Listeners).
        // NO usar Event::listen aquí: duplicaba cada push (2x blast).
        Event::listen(CommandStarting::class, PreventDestructiveDatabaseCommands::class);

        Route::bind('factura', fn (string $value) => SolicitudFactura::where('id', $value)->orWhere('folio', $value)->firstOrFail());
        Route::bind('traspaso', fn (string $value) => SolicitudTraspaso::where('id', $value)->orWhere('folio', $value)->firstOrFail());
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
        Route::bind('inventario', fn (string $value) => \App\Models\Inventario::findOrFail($value));
        Route::bind('costo', fn (string $value) => \App\Models\ProductoCosto::findOrFail($value));
        Route::bind('pedido', fn (string $value) => ContabilidadPedido::findOrFail($value));
        Route::bind('pedidoBma', fn (string $value) => PedidoBma::findOrFail($value));
        Route::bind('tarea', fn (string $value) => PedidoBmaTareaPreparacion::findOrFail($value));
        Route::bind('tareaDocumento', fn (string $value) => \App\Models\ControlPedidos\PedidoBmaTareaDocumento::findOrFail($value));
        Route::bind('caratula', fn (string $value) => \App\Models\ControlPedidos\PedidoBmaCaratula::findOrFail($value));

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