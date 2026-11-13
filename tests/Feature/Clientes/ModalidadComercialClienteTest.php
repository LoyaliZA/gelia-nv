<?php

namespace Tests\Feature\Clientes;

use App\Models\CambioListaImportacionCliente;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoProceso;
use App\Models\Cliente;
use App\Models\ClienteModalidadEvento;
use App\Models\ImportacionCliente;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Clientes\ClasificacionComercialCliente;
use App\Services\Clientes\ImportarClientesWizerpService;
use App\Services\Escalonamiento\EscalonamientoAutoridadConfig;
use App\Services\Mobile\MobileClienteSerializerService;
use App\Services\Solicitudes\AjustarMontoPorSolicitudService;
use App\Services\Solicitudes\CrearSolicitudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModalidadComercialClienteTest extends TestCase
{
    use RefreshDatabase;

    private ?User $usuarioImportador = null;

    private CatalogoListaDescuento $pg;

    private CatalogoListaDescuento $bronce;

    private CatalogoListaDescuento $plata;

    private CatalogoProceso $procesoTag;

    private CatalogoProceso $procesoLista;

    private int $pendiente;

    private int $respondida;

    protected function setUp(): void
    {
        parent::setUp();
        app(EscalonamientoAutoridadConfig::class)->guardar(false);
        Storage::fake('local');
        Notification::fake();
        $this->pg = CatalogoListaDescuento::create(['nombre' => 'PUBLICO GENERAL', 'monto_requerido' => 0, 'activo' => true]);
        $this->bronce = CatalogoListaDescuento::create(['nombre' => 'MAYOREO BRONCE', 'monto_requerido' => 0.01, 'activo' => true]);
        $this->plata = CatalogoListaDescuento::create(['nombre' => 'MAYOREO PLATA', 'monto_requerido' => 5001, 'activo' => true]);
        CatalogoListaDescuento::create(['nombre' => 'COLABORADORES', 'monto_requerido' => 999999, 'activo' => true]);
        foreach (['Pendiente', 'Respondida', 'Verificada', 'Cancelada'] as $estado) {
            CatalogoEstadoSolicitud::firstOrCreate(['nombre' => $estado], ['activo' => true]);
        }
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Administrador', 'web');
        CatalogoEstadoSolicitud::reiniciarCache();
        $this->pendiente = (int) CatalogoEstadoSolicitud::idDe('Pendiente');
        $this->respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $this->procesoTag = CatalogoProceso::create([
            'nombre' => 'ASIGNAR TAG',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
        $this->procesoLista = CatalogoProceso::create([
            'nombre' => 'CAMBIO DE LISTA',
            'categoria_flujo' => CatalogoProceso::CATEGORIA_FINANCIERO,
            'activo' => true,
        ]);
    }

    public function test_pg_con_acumulado_permanece_en_menudeo(): void
    {
        $cliente = $this->cliente('2030', '2030.00');
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n2030,pg,2030\n");

        $cliente->refresh();
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertTrue($cliente->es_menudeo);
        $this->assertNotSame($this->bronce->id, $cliente->lista_actual_id);
    }

    public function test_pg_con_cotizacion_no_obtiene_plata(): void
    {
        $cliente = $this->cliente('5767', '5767.00');
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n5767,pg,5767\n");
        $vendedor = User::factory()->create();
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => '5767',
            'catalogo_proceso_id' => $this->procesoLista->id,
            'monto_cotizado' => 1000,
            'convertir_menudeo_mayoreo' => false,
        ], $vendedor->id);

        $cliente->refresh();
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertTrue($cliente->es_menudeo);
        $this->assertNull($solicitud->catalogo_lista_descuento_id);
        $this->assertSame('5767.00', $cliente->monto_venta_actual);
    }

    public function test_pg_con_monto_cero_no_se_inactiva_ni_clasifica(): void
    {
        $cliente = $this->cliente('300', '10.00', menudeo: false, montoInicial: '0.00');
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n300,pg,0\n");

        $cliente->refresh();
        $this->assertFalse($cliente->es_inactivo);
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertFalse($cliente->es_menudeo);
    }

    public function test_codigo_vacio_no_altera_modalidad_ni_lista(): void
    {
        $cliente = $this->cliente('400', '80.00');
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n400,,90\n");

        $cliente->refresh();
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame(ClasificacionComercialCliente::ORIGEN_MIGRACION, $cliente->modalidad_origen);
        $this->assertSame('90.00', $cliente->monto_venta_actual);
        $this->assertFalse($cliente->es_inactivo);
    }

    public function test_inactivo_puede_consultarse_sin_activarse(): void
    {
        $cliente = $this->cliente('500', '20.00', lista: $this->bronce, inactivo: true, menudeo: false);
        $antes = $cliente->updated_at?->toDateTimeString();

        $this->actingAs(User::factory()->create())->getJson('/api/clientes/500')
            ->assertOk()
            ->assertJsonPath('es_inactivo', true)
            ->assertJsonPath('lista_almacenada_id', $this->bronce->id)
            ->assertJsonPath('lista_efectiva_nombre', 'PUBLICO GENERAL');

        $cliente->refresh();
        $this->assertTrue($cliente->es_inactivo);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame($antes, $cliente->updated_at?->toDateTimeString());
    }

    public function test_simular_mayoreo_no_escribe(): void
    {
        $cliente = $this->cliente('600', '40.00');
        $marca = $cliente->fresh()->updated_at?->toDateTimeString();

        $this->actingAs(User::factory()->create())->getJson('/api/clientes?q=600')
            ->assertOk()
            ->assertJsonPath('0.es_menudeo', true);

        $this->assertSame($marca, $cliente->fresh()->updated_at?->toDateTimeString());
        $this->assertSame($this->pg->id, $cliente->fresh()->lista_actual_id);
        $this->assertSame(0, SolicitudTag::count());
    }

    public function test_solicitud_pendiente_no_convierte(): void
    {
        $cliente = $this->cliente('700', '15.00');
        $solicitud = $this->solicitud($cliente, $this->pendiente, $this->bronce->id);

        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        $cliente->refresh();
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertSame('15.00', $cliente->monto_venta_actual);
    }

    public function test_conversion_autorizada_asigna_bronce_o_la_lista_expresa(): void
    {
        $bronce = $this->cliente('800', '15.00');
        $plata = $this->cliente('801', '15.00');
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($this->solicitud($bronce, $this->respondida, null));
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($this->solicitud($plata, $this->respondida, $this->plata->id));

        $bronce->refresh();
        $plata->refresh();
        $this->assertFalse($bronce->es_menudeo);
        $this->assertSame(ClasificacionComercialCliente::ORIGEN_CONVERSION, $bronce->modalidad_origen);
        $this->assertSame($this->bronce->id, $bronce->lista_actual_id);
        $this->assertSame($this->plata->id, $plata->lista_actual_id);
        $this->assertSame('15.00', $bronce->monto_venta_actual);
        $this->assertSame('1', SolicitudBeneficio::query()->where('cliente_id', $bronce->id)->where('campo', 'es_menudeo')->value('valor_anterior'));
    }

    public function test_importacion_pg_no_deshace_conversion_ratificada(): void
    {
        $cliente = $this->cliente('900', '80.00');
        $solicitud = $this->solicitud($cliente, $this->respondida, $this->bronce->id);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud);

        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n900,pg,120\n");
        $this->assertTrue(collect($vista['avisos'])->contains(fn ($aviso) => $aviso['codigo'] === 'conversion_autorizada'));
        $this->confirmar($vista);

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->bronce->id, $cliente->lista_actual_id);
        $this->assertSame('120.00', $cliente->monto_venta_actual);
    }

    public function test_cancelar_la_original_no_deshace_una_ratificacion(): void
    {
        $cliente = $this->cliente('1000', '30.00');
        $original = $this->solicitud($cliente, $this->respondida, $this->bronce->id);
        $ajuste = app(AjustarMontoPorSolicitudService::class);
        $ajuste->aplicarBeneficios($original);
        $ratificada = $this->solicitud($cliente, $this->respondida, $this->plata->id);
        $ajuste->aplicarBeneficios($ratificada);

        $ajuste->revertirBeneficios($original);

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertSame(
            SolicitudBeneficio::ESTADO_REEMPLAZADO,
            SolicitudBeneficio::query()->where('solicitud_tag_id', $original->id)->where('campo', 'es_menudeo')->value('estado'),
        );
    }

    public function test_reversion_recupera_la_modalidad_textual(): void
    {
        $cliente = $this->cliente('1100', '30.00');
        $solicitud = $this->solicitud($cliente, $this->respondida, $this->bronce->id);
        $ajuste = app(AjustarMontoPorSolicitudService::class);
        $ajuste->aplicarBeneficios($solicitud);
        $ajuste->revertirBeneficios($solicitud);

        $cliente->refresh();
        $this->assertTrue($cliente->es_menudeo);
        $this->assertNotSame(0, $cliente->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertSame('30.00', $cliente->monto_venta_actual);
    }

    public function test_una_accion_posterior_ratifica_la_conversion(): void
    {
        $cliente = $this->cliente('1200', '30.00');
        $solicitud = $this->solicitud($cliente, $this->respondida, $this->bronce->id);
        $ajuste = app(AjustarMontoPorSolicitudService::class);
        $ajuste->aplicarBeneficios($solicitud);
        ClienteModalidadEvento::query()->create([
            'cliente_id' => $cliente->id,
            'es_menudeo' => false,
            'origen' => ClasificacionComercialCliente::ORIGEN_ADMINISTRACION,
            'solicitud_id' => null,
            'usuario_id' => null,
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $ajuste->revertirBeneficios($solicitud);

        $this->assertFalse($cliente->fresh()->es_menudeo);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    public function test_listas_bloqueadas_y_especiales_siguen_protegidas(): void
    {
        $bloqueado = $this->cliente('1300', '10.00', lista: $this->bronce, menudeo: false);
        $bloqueado->update(['lista_bloqueada' => true]);
        $especial = $this->cliente('1301', '4000.00', lista: CatalogoListaDescuento::where('nombre', 'COLABORADORES')->first(), menudeo: false);

        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n1300,pg,5000\n1301,pg,5000\n");

        $this->assertSame($this->bronce->id, $bloqueado->fresh()->lista_actual_id);
        $this->assertFalse($bloqueado->fresh()->es_menudeo);
        $this->assertSame($especial->lista_actual_id, $especial->fresh()->lista_actual_id);
        $this->assertFalse($especial->fresh()->es_menudeo);
    }

    public function test_menudeo_con_codigo_bronce_conserva_la_clasificacion_y_avisa(): void
    {
        $cliente = $this->cliente('1400', '80.00');
        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n1400,2,90\n");

        $this->assertFalse($vista['bloqueado']);
        $this->assertTrue(collect($vista['avisos'])->contains(fn ($aviso) => $aviso['codigo'] === 'lista_mayorista_sin_conversion'));
        $this->confirmar($vista);

        $cliente->refresh();
        $this->assertSame($this->pg->id, $cliente->lista_actual_id);
        $this->assertTrue($cliente->es_menudeo);
        $this->assertSame(ClasificacionComercialCliente::ORIGEN_MIGRACION, $cliente->modalidad_origen);
        $this->assertSame('90.00', $cliente->monto_venta_actual);
    }

    public function test_codigo_explicito_en_cliente_sin_modalidad_fija_la_lista(): void
    {
        $cliente = $this->cliente('1500', '10.00', menudeo: false);
        $this->importar("numero_cliente,codigo_lista,monto_venta_actual\n1500,3,6000\n");

        $cliente->refresh();
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
        $this->assertFalse($cliente->es_menudeo);
    }

    public function test_conversion_entre_revision_y_confirmacion_invalida_la_revision(): void
    {
        $cliente = $this->cliente('1600', '10.00');
        $vista = $this->revisar("numero_cliente,codigo_lista,monto_venta_actual\n1600,pg,40\n");
        $cliente->update([
            'es_menudeo' => false,
            'modalidad_origen' => ClasificacionComercialCliente::ORIGEN_CONVERSION,
            'conversion_solicitud_id' => $this->solicitud($cliente, $this->respondida, $this->bronce->id)->id,
            'conversion_autorizada_at' => now(),
        ]);

        $this->actingAs($this->usuarioImportacion())->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
        ])->assertSessionHasErrors('archivo');

        $this->assertSame('10.00', $cliente->fresh()->monto_venta_actual);
    }

    public function test_api_separa_lista_almacenada_y_efectiva(): void
    {
        $cliente = $this->cliente('1700', '25.00', lista: $this->plata);
        $cliente->update(['es_menudeo' => true]);
        $web = $this->actingAs(User::factory()->create())->getJson('/api/clientes/1700')->assertOk();
        $movil = app(MobileClienteSerializerService::class)->serializar($cliente->fresh(), User::factory()->create());

        $web->assertJsonPath('lista_actual_id', $this->plata->id);
        $web->assertJsonPath('lista_almacenada_id', $this->plata->id);
        $web->assertJsonPath('lista_efectiva_id', $this->pg->id);
        $web->assertJsonPath('es_menudeo', true);
        $this->assertSame($this->plata->id, $movil['lista_actual_id']);
        $this->assertSame($this->pg->id, $movil['lista_efectiva_id']);
        $this->assertSame('PUBLICO GENERAL', $movil['lista_descuento']);
        $this->assertTrue($movil['es_menudeo']);
    }

    public function test_asignar_tag_con_conversion_conserva_la_lista_mayorista(): void
    {
        $cliente = $this->cliente('1800', '12.00');
        $vendedor = User::factory()->create();
        $solicitud = app(CrearSolicitudService::class)->ejecutar([
            'numero_cliente' => '1800',
            'catalogo_proceso_id' => $this->procesoTag->id,
            'compra_en_tienda_solo_tag' => true,
            'convertir_menudeo_mayoreo' => true,
            'catalogo_lista_descuento_id' => $this->plata->id,
            'monto_cotizado' => 0,
        ], $vendedor->id);

        $this->assertTrue($solicitud->convertir_menudeo_mayoreo);
        $this->assertSame($this->plata->id, $solicitud->catalogo_lista_descuento_id);
        $this->assertTrue($cliente->fresh()->es_menudeo);
        $this->assertSame($this->pg->id, $cliente->fresh()->lista_actual_id);

        $solicitud->update(['catalogo_estado_solicitud_id' => $this->respondida]);
        app(AjustarMontoPorSolicitudService::class)->aplicarBeneficios($solicitud->fresh());

        $cliente->refresh();
        $this->assertFalse($cliente->es_menudeo);
        $this->assertSame($this->plata->id, $cliente->lista_actual_id);
    }

    public function test_el_reporte_de_candidatos_no_corrige_clientes(): void
    {
        $cliente = $this->cliente('1900', '50.00', lista: $this->bronce, menudeo: false);
        CambioListaImportacionCliente::create([
            'importacion_cliente_id' => ImportacionCliente::create([
                'usuario_id' => User::factory()->create()->id,
                'nombre_archivo_original' => 'candidatos.csv',
                'ruta_almacenamiento' => 'candidatos.csv',
            ])->id,
            'numero_cliente' => '1900',
            'nombre_cliente' => 'Cliente 1900',
            'lista_anterior' => 'PUBLICO GENERAL',
            'lista_nueva' => 'MAYOREO BRONCE',
            'tipo_cambio' => CambioListaImportacionCliente::TIPO_ASCENSO,
            'monto_nuevo' => 50,
        ]);

        Artisan::call('clientes:candidatos-ascenso-pg');

        $this->assertStringContainsString('1900', Artisan::output());
        $this->assertFalse($cliente->fresh()->es_menudeo);
        $this->assertSame($this->bronce->id, $cliente->fresh()->lista_actual_id);
    }

    private function cliente(
        string $numero,
        string $monto,
        ?CatalogoListaDescuento $lista = null,
        bool $inactivo = false,
        bool $menudeo = true,
        ?string $montoInicial = null,
    ): Cliente {
        return Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => ($lista ?? $this->pg)->id,
            'monto_venta_actual' => $montoInicial ?? $monto,
            'es_inactivo' => $inactivo,
            'es_menudeo' => $menudeo,
            'modalidad_origen' => $menudeo
                ? ClasificacionComercialCliente::ORIGEN_MIGRACION
                : null,
        ]);
    }

    private function solicitud(Cliente $cliente, int $estadoId, ?int $listaId): SolicitudTag
    {
        return SolicitudTag::create([
            'cliente_id' => $cliente->id,
            'vendedor_id' => User::factory()->create()->id,
            'catalogo_proceso_id' => $this->procesoLista->id,
            'catalogo_estado_solicitud_id' => $estadoId,
            'catalogo_lista_descuento_id' => $listaId,
            'convertir_menudeo_mayoreo' => true,
            'monto_cotizado' => 0,
        ]);
    }

    private function importar(string $csv): void
    {
        app(ImportarClientesWizerpService::class)->ejecutar(
            UploadedFile::fake()->createWithContent('modalidad.csv', $csv),
        );
    }

    private function revisar(string $csv): array
    {
        $respuesta = $this->actingAs($this->usuarioImportacion())->post(route('admin.clientes.importar.previsualizar'), [
            'archivo' => UploadedFile::fake()->createWithContent('revision.csv', $csv),
        ]);
        $respuesta->assertRedirect();
        $respuesta->assertSessionHas('previsualizacion_importacion');

        return session('previsualizacion_importacion');
    }

    private function confirmar(array $vista): void
    {
        $this->actingAs($this->usuarioImportacion())->post(route('admin.clientes.importar.confirmar'), [
            'importacion_id' => $vista['importacion_id'],
            'token' => $vista['token'],
        ])->assertRedirect();
    }

    private function usuarioImportacion(): User
    {
        if ($this->usuarioImportador) {
            return $this->usuarioImportador;
        }
        Permission::findOrCreate('clientes.ver', 'web');
        Permission::findOrCreate('clientes.carga_masiva', 'web');
        $rol = Role::findOrCreate('admin_modalidad', 'web');
        $rol->givePermissionTo(['clientes.ver', 'clientes.carga_masiva']);
        $this->usuarioImportador = User::factory()->create();
        $this->usuarioImportador->assignRole($rol);

        return $this->usuarioImportador;
    }
}
