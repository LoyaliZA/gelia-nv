<?php

namespace App\Providers;

use App\Contracts\Medios\AlmacenObjetosMedio;
use App\Services\Tiendanube\Gobernador\TiendanubeContextoHttpActual;
use App\Services\Tiendanube\Gobernador\TiendanubeEmisorHttp;
use App\Services\Tiendanube\Gobernador\TiendanubeEnrutadorColas;
use App\Services\Tiendanube\Gobernador\TiendanubeGobernador;
use App\Services\Tiendanube\Gobernador\TiendanubeReprogramador;
use App\Services\Tiendanube\Gobernador\TiendanubeReprogramadorDurable;
use App\Contracts\PuntoVenta\ConsultaPersonaDisponiblePdv;
use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Contracts\PuntoVenta\ResuelvePlazosCustodiaResguardoPdv;
use App\Listeners\PreventDestructiveDatabaseCommands;
use App\Listeners\PuntoVenta\Broadcast\TransmitirCambioPdvRealtimeSubscriber;
use App\Listeners\PuntoVenta\LiberarTerminalAlertasSucursalAlCerrarSesion;
use App\Listeners\PuntoVenta\NotificarTurnoPdvSubscriber;
use App\Models\ApiAplicacion;
use App\Models\CatalogoBono;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoPuesto;
use App\Models\CatalogoReglaIncidencia;
use App\Models\CatalogoTipoFalta;
use App\Models\Cliente;
// Importaciones requeridas para el módulo de auditoría de catálogos
use App\Models\ConfiguracionSistema;
use App\Models\Contabilidad\Pedido as ContabilidadPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\RhColaborador;
use App\Models\RhDeduccion;
use App\Models\RhHorasExtra;
use App\Models\RhPrestamoPagoFijo;
use App\Models\SolicitudFactura;
use App\Models\SolicitudTag;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use App\Observers\CatalogoListaDescuentoObserver;
use App\Observers\ClienteObserver;
use App\Observers\PedidoBmaTareaPreparacionCaratulaObserver;
use App\Observers\PreparacionPedidosRealtimeObserver;
use App\Observers\SolicitudTagObserver;
use App\Services\Auth\CacheWebAuthnChallengeRepository;
use App\Services\Demo\AlcanceDemo;
use App\Services\GeliaAi\Acciones\AccionRegistry;
use App\Services\GeliaAi\Acciones\GenerarListadoAccion;
use App\Services\GeliaAi\Acciones\ImportarCostosAccion;
use App\Services\GeliaAi\Acciones\ImportarInventariosAccion;
use App\Services\Medios\AlmacenObjetosMedioFake;
use App\Services\Medios\AlmacenObjetosMedioR2;
use App\Services\PuntoVenta\AlcancePdv;
use App\Services\PuntoVenta\Operacion\ConsultaPersonaDisponiblePdvService;
use App\Services\PuntoVenta\Resguardos\PlazosCustodiaResguardoPdvConfig;
use App\Support\WebAuthn\PasskeyOrigins;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laragear\WebAuthn\Contracts\WebAuthnChallengeRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El caché de config anterior a esta fase no incluye el archivo nuevo.
        if ($this->app['config']->get('productos_imagenes') === null) {
            $this->app['config']->set(
                'productos_imagenes',
                require $this->app->configPath('productos_imagenes.php')
            );
        }

        $this->app->singleton(AlcanceDemo::class);
        $this->app->singleton(TiendanubeGobernador::class);
        $this->app->singleton(TiendanubeContextoHttpActual::class);
        $this->app->singleton(TiendanubeEmisorHttp::class);
        $this->app->singleton(TiendanubeEnrutadorColas::class);
        $this->app->singleton(TiendanubeReprogramador::class, TiendanubeReprogramadorDurable::class);

        $this->app->singleton(
            ResuelveAlcancePdv::class,
            AlcancePdv::class
        );

        $this->app->singleton(
            ResuelvePlazosCustodiaResguardoPdv::class,
            PlazosCustodiaResguardoPdvConfig::class
        );

        $this->app->singleton(
            ConsultaPersonaDisponiblePdv::class,
            ConsultaPersonaDisponiblePdvService::class
        );

        $this->app->singleton(
            AlmacenObjetosMedio::class,
            function ($app) {
                $driver = (string) config('medios.driver', 'r2');
                if ($app->environment('testing') || $driver === 'fake') {
                    return new AlmacenObjetosMedioFake;
                }

                return $app->make(AlmacenObjetosMedioR2::class);
            }
        );

        $this->app->singleton(AccionRegistry::class, function ($app) {
            return new AccionRegistry([
                $app->make(ImportarCostosAccion::class),
                $app->make(ImportarInventariosAccion::class),
                $app->make(GenerarListadoAccion::class),
            ]);
        });

        $this->app->singleton(
            WebAuthnChallengeRepository::class,
            CacheWebAuthnChallengeRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cargar configuraciones del sistema desde la base de datos
        try {
            if (Schema::hasTable('configuraciones_sistema')) {
                // Ensure class is loaded so any lingering old cache can unserialize properly
                class_exists(ConfiguracionSistema::class);

                $configuraciones = Cache::rememberForever('configuraciones_sistema_globales', function () {
                    return ConfiguracionSistema::all()->toArray();
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

                    if (! $clave) {
                        continue;
                    }

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
            Log::error('AppServiceProvider config load error: '.$e->getMessage());
        }

        PasskeyOrigins::apply();

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
                $sample = Permission::query()
                    ->orderByDesc('id')
                    ->first(['id', 'name', 'guard_name']);
                if (! $sample) {
                    return;
                }
                $fromCache = Permission::findByName(
                    $sample->name,
                    $sample->guard_name
                );
                if ((int) $fromCache->id === (int) $sample->id) {
                    return;
                }
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $e) {
                try {
                    app(PermissionRegistrar::class)->forgetCachedPermissions();
                } catch (\Throwable) {
                }
            }
        });

        // 3. Registro de Observadores
        SolicitudTag::observe(SolicitudTagObserver::class);

        // CONEXIÓN DEL NUEVO OBSERVADOR PARA CATÁLOGOS
        CatalogoListaDescuento::observe(CatalogoListaDescuentoObserver::class);
        Cliente::observe(ClienteObserver::class);
        PedidoBmaTareaPreparacion::observe(PedidoBmaTareaPreparacionCaratulaObserver::class);
        PedidoBma::observe(PreparacionPedidosRealtimeObserver::class);
        PedidoBmaTareaPreparacion::observe(PreparacionPedidosRealtimeObserver::class);
        PedidoBmaCumplimientoFisico::observe(PreparacionPedidosRealtimeObserver::class);
        PedidoBmaTareaDocumento::observe(PreparacionPedidosRealtimeObserver::class);

        Broadcast::routes([
            'middleware' => ['auth:sanctum'],
            'prefix' => 'api',
        ]);

        // EnviarWebPushTrasNotificacion se registra por discovery (App\Listeners).
        // NO usar Event::listen aquí: duplicaba cada push (2x blast).
        Event::listen(CommandStarting::class, PreventDestructiveDatabaseCommands::class);
        Event::listen(
            Logout::class,
            LiberarTerminalAlertasSucursalAlCerrarSesion::class,
        );
        Event::subscribe(TransmitirCambioPdvRealtimeSubscriber::class);
        Event::subscribe(NotificarTurnoPdvSubscriber::class);

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
        Route::bind('inventario', fn (string $value) => Inventario::findOrFail($value));
        Route::bind('costo', fn (string $value) => ProductoCosto::findOrFail($value));
        Route::bind('pedido', fn (string $value) => ContabilidadPedido::findOrFail($value));
        Route::bind('pedidoBma', fn (string $value) => PedidoBma::findOrFail($value));
        Route::bind('tarea', fn (string $value) => PedidoBmaTareaPreparacion::findOrFail($value));
        Route::bind('tareaDocumento', fn (string $value) => PedidoBmaTareaDocumento::findOrFail($value));
        Route::bind('caratula', fn (string $value) => PedidoBmaCaratula::findOrFail($value));

        RateLimiter::for('api-externa', function (Request $request) {
            $aplicacion = $request->user();
            $limite = $aplicacion instanceof ApiAplicacion
                ? max(1, (int) $aplicacion->limite_por_minuto)
                : 30;

            $key = $aplicacion instanceof ApiAplicacion
                ? 'api-app:'.$aplicacion->id
                : 'api-ip:'.$request->ip();

            return Limit::perMinute($limite)->by($key);
        });

        RateLimiter::for('api-mobile', function (Request $request) {
            $user = $request->user();
            $key = $user instanceof User
                ? 'api-mobile:'.$user->id
                : 'api-mobile-ip:'.$request->ip();

            // El bootstrap pagina ~70 veces. Un tope de 60/min lo cortaba cerca de los 6000 clientes.
            if (str_contains($request->path(), 'api/v1/mobile/sync')) {
                return Limit::perMinute(180)->by($key.':sync');
            }

            return Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('passkeys-login', function (Request $request) {
            $login = strtolower(trim((string) $request->input('login', '')));

            return [
                Limit::perMinute(10)->by('passkeys-ip:'.$request->ip()),
                Limit::perMinute(10)->by('passkeys-login:'.$login),
            ];
        });

        RateLimiter::for('mobile-login', function (Request $request) {
            $login = strtolower(trim((string) $request->input('login', '')));

            return [
                Limit::perMinute(10)->by('mobile-login-ip:'.$request->ip()),
                Limit::perMinute(10)->by('mobile-login:'.$login),
            ];
        });
    }
}
