<?php

namespace Tests\Feature\PuntoVenta;

use App\Jobs\GenerarExportacionResguardoPdvJob;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvExportacion;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\ResguardoPdvExportacionTipo;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\ColumnasExportacionResguardoPdv;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportacionResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $exportador;

    private User $otroExportador;

    private Sucursal $sucursalA;

    private Sucursal $sucursalB;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ]);
        $this->activarModulo();
        $this->seedPermisos();
        Storage::fake('local');

        $this->sucursalA = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->sucursalB = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->exportador = $this->crearExportador($this->sucursalA);
        $this->otroExportador = $this->crearExportador($this->sucursalB);
    }

    public function test_exportacion_listado_sincrona_respeta_filtros_y_columnas_csv(): void
    {
        config(['punto_venta.resguardos.exportacion.pesado_registros' => 50]);

        $incluido = $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-EXP-001',
            'snapshot_cliente_nombre' => 'Cliente Exportable',
        ]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'REM-OTRO-002',
            'recepcion_fisica_at' => now()->subDay(),
        ]);
        $this->crearResguardo($this->sucursalB, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-SUR-003',
        ]);

        $response = $this->actingAs($this->exportador)->post(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::LISTADO,
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
            'q' => 'EXP-001',
        ]);

        $response->assertOk();

        $exportacion = ResguardoPdvExportacion::query()->firstOrFail();
        $contenido = Storage::disk('local')->get($exportacion->ruta_archivo);
        $this->assertNotNull($contenido);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);

        $lineas = preg_split('/\r\n|\n|\r/', ltrim($contenido, "\xEF\xBB\xBF"));
        $encabezados = str_getcsv($lineas[0]);
        $this->assertSame(array_values(ColumnasExportacionResguardoPdv::listado()), $encabezados);

        $datos = str_getcsv($lineas[1]);
        $this->assertSame((string) $incluido->id, $datos[0]);
        $this->assertSame('REM-EXP-001', $datos[2]);
        $this->assertSame('Cliente Exportable', $datos[6]);
        $this->assertCount(2, array_filter($lineas, static fn ($linea) => $linea !== ''));
    }

    public function test_exportacion_listado_pesada_encola_job(): void
    {
        Queue::fake();
        config(['punto_venta.resguardos.exportacion.pesado_registros' => 1]);

        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-A-001',
        ]);
        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-A-002',
        ]);

        $response = $this->actingAs($this->exportador)->postJson(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::LISTADO,
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('modo', 'asincrono')
            ->assertJsonPath('exportacion.estado', ResguardoPdvExportacion::ESTADO_PENDING);

        Queue::assertPushed(GenerarExportacionResguardoPdvJob::class);
    }

    public function test_descarga_no_disponible_para_otro_usuario(): void
    {
        config(['punto_venta.resguardos.exportacion.pesado_registros' => 50]);

        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-DESC-001',
        ]);

        $this->actingAs($this->exportador)->post(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::LISTADO,
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ]);

        $exportacion = ResguardoPdvExportacion::query()
            ->where('user_id', $this->exportador->id)
            ->firstOrFail();

        $this->actingAs($this->otroExportador)
            ->get(route('punto_venta.resguardos.exportaciones.descargar', ['exportacion' => $exportacion->id]))
            ->assertNotFound();
    }

    public function test_get_descarga_no_genera_nueva_exportacion(): void
    {
        config(['punto_venta.resguardos.exportacion.pesado_registros' => 50]);

        $this->crearResguardo($this->sucursalA, [
            'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'snapshot_folio' => 'REM-GET-001',
        ]);

        $this->actingAs($this->exportador)->post(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::LISTADO,
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ]);

        $exportacion = ResguardoPdvExportacion::query()->firstOrFail();
        $ruta = $exportacion->ruta_archivo;
        $this->assertNotNull($ruta);

        $this->actingAs($this->exportador)
            ->get(route('punto_venta.resguardos.exportaciones.descargar', ['exportacion' => $exportacion->id]))
            ->assertOk();

        $this->assertSame(1, ResguardoPdvExportacion::query()->count());
        $this->assertTrue(Storage::disk('local')->exists($ruta));
    }

    public function test_exportacion_auditoria_respeta_filtros_y_omite_sucursal_ajena(): void
    {
        config(['punto_venta.resguardos.exportacion.pesado_registros' => 50]);

        $base = Carbon::parse('2026-08-10 09:00:00');
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursalA->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'REM-AUD-001',
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->exportador->id,
            'ocurrido_at' => $base,
            'snapshot_json' => ['cantidad_recibida' => 1],
        ]);
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_DANO,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->exportador->id,
            'ocurrido_at' => $base->copy()->addDay(),
            'snapshot_json' => ['descripcion' => 'Caja golpeada'],
        ]);

        $response = $this->actingAs($this->exportador)->post(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::AUDITORIA,
            'resguardo_id' => $resguardo->id,
            'categoria' => 'recepcion',
        ]);

        $response->assertOk();

        $exportacion = ResguardoPdvExportacion::query()
            ->where('user_id', $this->exportador->id)
            ->latest('created_at')
            ->firstOrFail();
        $contenido = Storage::disk('local')->get($exportacion->ruta_archivo);
        $this->assertNotNull($contenido);

        $lineas = preg_split('/\r\n|\n|\r/', ltrim($contenido, "\xEF\xBB\xBF"));
        $this->assertSame(array_values(ColumnasExportacionResguardoPdv::auditoria()), str_getcsv($lineas[0]));
        $this->assertCount(2, array_filter($lineas, static fn ($linea) => $linea !== ''));
        $this->assertStringContainsString('Recepción completa', $lineas[1]);

        $this->actingAs($this->otroExportador)->post(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::AUDITORIA,
            'resguardo_id' => $resguardo->id,
        ])->assertNotFound();
    }

    public function test_sin_permiso_exportar_rechaza(): void
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $usuario->concederAccesoSucursal($this->sucursalA, esPrincipal: true);

        $this->actingAs($usuario)->postJson(route('punto_venta.resguardos.exportaciones.store'), [
            'tipo' => ResguardoPdvExportacionTipo::LISTADO,
            'bandeja' => BandejaResguardoPdv::POR_RECIBIR,
        ])->assertForbidden();
    }

    private function crearExportador(Sucursal $sucursal): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_REPORTES_EXPORTAR,
        ]);
        $usuario->concederAccesoSucursal($sucursal, esPrincipal: true);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardo(Sucursal $sucursal, array $overrides = []): ResguardoPdv
    {
        return ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $sucursal->id,
            'salida_cedis_at' => now(),
        ], $overrides));
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => '1']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
