<?php

namespace Tests\Feature\Facturas;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Departamento;
use App\Models\EnlaceDatosFiscales;
use App\Models\SolicitudFactura;
use App\Models\SolicitudFacturaPdf;
use App\Models\SolicitudFacturaVoucher;
use App\Models\User;
use App\Notifications\AlertaFactura;
use App\Support\Facturas\CamposIncorrectosFactura;
use App\Support\Facturas\FacturaStorage;
use App\Support\Facturas\LimitesAdjuntosFactura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ErroresPorCampoFacturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.form_public_url' => 'https://form.neobash.site']);

        foreach (['Pendiente', 'Respondida', 'Incorrecta', 'Borrador'] as $nombre) {
            CatalogoEstadoSolicitud::updateOrCreate(
                ['nombre' => $nombre],
                ['descripcion' => $nombre, 'activo' => true]
            );
        }
        CatalogoEstadoSolicitud::reiniciarCache();

        foreach ([
            'facturas.crear',
            'facturas.responder',
            'facturas.reportar_error',
            'facturas.ver_listado',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
    }

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function departamento(): Departamento
    {
        return Departamento::firstOrCreate(
            ['nombre' => 'Ventas Test'],
            ['activo' => true, 'logo_key_claro' => 'aromas_logo_negro', 'logo_key_oscuro' => 'aromas_logo_blanco']
        );
    }

    private function vendedor(): User
    {
        $user = User::factory()->create(['name' => 'Vendedora']);
        $user->givePermissionTo(['facturas.crear', 'facturas.ver_listado']);
        $user->departamentos()->syncWithoutDetaching([$this->departamento()->id]);

        return $user;
    }

    private function encargada(): User
    {
        $user = User::factory()->create(['name' => 'Encargada']);
        $user->givePermissionTo(['facturas.responder', 'facturas.reportar_error', 'facturas.ver_listado']);
        $user->departamentos()->syncWithoutDetaching([$this->departamento()->id]);

        return $user;
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'numero_cliente' => '04950',
            'nombre' => 'Cliente Test',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
        ]);
    }

    private function datosFiscales(): array
    {
        return [
            'rfc' => 'XAXX010101000',
            'codigo_postal' => '06000',
            'regimen_fiscal' => '626',
            'correo_electronico' => 'fiscal@example.com',
            'uso_factura' => 'G03',
            'nombre_razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'telefono' => '5512345678',
        ];
    }

    private function solicitudPendiente(?User $vendedor = null, array $extra = []): SolicitudFactura
    {
        $vendedor ??= $this->vendedor();
        $cliente = $this->cliente();

        $factura = SolicitudFactura::create(array_merge([
            'folio' => 'FAC-'.uniqid(),
            'vendedor_id' => $vendedor->id,
            'departamento_id' => $this->departamento()->id,
            'cliente_id' => $cliente->id,
            'destinatario_tipo' => SolicitudFactura::DESTINATARIO_CLIENTE,
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
            'razon_social' => 'EMPRESA PRUEBA SA DE CV',
            'datos_fiscales' => $this->datosFiscales(),
        ], $extra));

        SolicitudFacturaVoucher::create([
            'solicitud_factura_id' => $factura->id,
            'path' => 'facturas/vouchers/test.jpg',
            'nombre_original' => 'voucher.jpg',
            'mime' => 'image/jpeg',
            'orden' => 1,
        ]);

        return $factura->fresh(['vouchers']);
    }

    public function test_reportar_error_con_campos_incorrectos_persiste_y_notifica(): void
    {
        Notification::fake();
        Storage::fake(FacturaStorage::storeDisk());

        $vendedor = $this->vendedor();
        $encargada = $this->encargada();
        $factura = $this->solicitudPendiente($vendedor);

        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        $this->actingAs($encargada)
            ->put(route('facturas.actualizar_estado', $factura), [
                'catalogo_estado_solicitud_id' => $idIncorrecta,
                'motivo' => 'RFC y razón social incorrectos',
                'campos_incorrectos' => ['rfc', CamposIncorrectosFactura::RAZON_SOCIAL],
            ])
            ->assertRedirect();

        $factura->refresh();
        $this->assertSame('Incorrecta', $factura->estado->nombre);
        $this->assertSame(['rfc', CamposIncorrectosFactura::RAZON_SOCIAL], $factura->campos_incorrectos);

        Notification::assertSentTo($vendedor, AlertaFactura::class, fn ($n) => $n->tipoAlerta === 'rechazada');
    }

    public function test_reportar_error_con_generar_enlace_fiscal_crea_enlace(): void
    {
        Storage::fake(FacturaStorage::storeDisk());

        $factura = $this->solicitudPendiente();
        $encargada = $this->encargada();
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');

        $this->actingAs($encargada)
            ->put(route('facturas.actualizar_estado', $factura), [
                'catalogo_estado_solicitud_id' => $idIncorrecta,
                'motivo' => 'Corregir RFC',
                'campos_incorrectos' => ['rfc', 'correo_electronico'],
                'generar_enlace_fiscal' => true,
            ])
            ->assertRedirect();

        $factura->refresh();
        $this->assertNotNull($factura->formulario_enviado_at);
        $this->assertContains('rfc', $factura->campos_fiscales_solicitados ?? []);
        $this->assertDatabaseCount('enlaces_datos_fiscales', 1);
    }

    public function test_encargada_corrige_manual_y_sigue_pendiente(): void
    {
        Notification::fake();

        $factura = $this->solicitudPendiente(null, [
            'campos_incorrectos' => ['rfc'],
        ]);
        $encargada = $this->encargada();

        $this->actingAs($encargada)
            ->put(route('facturas.corregir', $factura), [
                'datos_fiscales' => ['rfc' => 'NEWRFC123456'],
                'campos_corregidos' => ['rfc'],
            ])
            ->assertRedirect();

        $factura->refresh();
        $this->assertSame('Pendiente', $factura->estado->nombre);
        $this->assertNull($factura->campos_incorrectos);
        $this->assertSame('NEWRFC123456', $factura->datos_fiscales['rfc']);
    }

    public function test_vendedora_repara_con_datos_fiscales_parciales_y_queda_pendiente(): void
    {
        $vendedor = $this->vendedor();
        $factura = $this->solicitudPendiente($vendedor, [
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Incorrecta'),
            'campos_incorrectos' => ['rfc'],
            'motivo_respuesta' => 'RFC mal',
        ]);

        $this->actingAs($vendedor)
            ->put(route('facturas.reparar', $factura), [
                'razon_social' => $factura->razon_social,
                'numero_cliente' => $this->cliente()->numero_cliente,
                'vouchers_conservar' => [$factura->vouchers->first()->id],
                'datos_fiscales' => ['rfc' => 'CORREGIDO123'],
            ])
            ->assertRedirect();

        $factura->refresh();
        $this->assertSame('Pendiente', $factura->estado->nombre);
        $this->assertNull($factura->campos_incorrectos);
        $this->assertSame('CORREGIDO123', $factura->datos_fiscales['rfc']);
    }

    public function test_regenerar_enlace_fiscal_permitido_en_incorrecta_para_dueno(): void
    {
        $vendedor = $this->vendedor();
        $factura = $this->solicitudPendiente($vendedor, [
            'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Incorrecta'),
            'campos_incorrectos' => ['rfc'],
        ]);

        $this->actingAs($vendedor)
            ->postJson(route('facturas.enlace_fiscal', $factura), [
                'campos_fiscales' => ['rfc'],
            ])
            ->assertOk()
            ->assertJsonStructure(['url']);

        $this->assertDatabaseCount('enlaces_datos_fiscales', 1);
    }

    public function test_emitir_con_tres_pdfs_persiste_y_sirve_por_indice(): void
    {
        Storage::fake(FacturaStorage::storeDisk());

        $encargada = $this->encargada();
        $factura = $this->solicitudPendiente();
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

        $pdfs = [
            UploadedFile::fake()->create('f1.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('f2.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('f3.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($encargada)
            ->put(route('facturas.actualizar_estado', $factura), [
                'catalogo_estado_solicitud_id' => $idRespondida,
                'factura_pdfs' => $pdfs,
            ])
            ->assertRedirect();

        $factura->refresh();
        $this->assertCount(3, $factura->pdfsEmitidos);

        foreach ($factura->pdfsEmitidos as $pdf) {
            Storage::disk(FacturaStorage::storeDisk())->put($pdf->path, '%PDF-1.4 fake');
        }

        $user = $this->encargada();
        $this->actingAs($user)
            ->get(route('facturas.archivo', ['factura' => $factura->id, 'tipo' => 'pdf', 'indice' => 2]))
            ->assertOk();
    }

    public function test_rechaza_sexto_pdf_y_archivo_mayor_a_cinco_mb(): void
    {
        $encargada = $this->encargada();
        $factura = $this->solicitudPendiente();
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

        $seisPdfs = array_fill(0, 6, UploadedFile::fake()->create('f.pdf', 100, 'application/pdf'));

        $this->actingAs($encargada)
            ->put(route('facturas.actualizar_estado', $factura), [
                'catalogo_estado_solicitud_id' => $idRespondida,
                'factura_pdfs' => $seisPdfs,
            ])
            ->assertSessionHasErrors('factura_pdfs');

        $grande = UploadedFile::fake()->create('grande.pdf', LimitesAdjuntosFactura::MAX_KB_POR_ARCHIVO + 1, 'application/pdf');

        $this->actingAs($encargada)
            ->put(route('facturas.actualizar_estado', $factura), [
                'catalogo_estado_solicitud_id' => $idRespondida,
                'factura_pdfs' => [$grande],
            ])
            ->assertSessionHasErrors('factura_pdfs.0');
    }

    public function test_migracion_legacy_pdf_queda_en_tabla_pdfs(): void
    {
        Storage::fake(FacturaStorage::storeDisk());

        $vendedor = $this->vendedor();
        $factura = $this->solicitudPendiente($vendedor);

        $path = 'facturas/emitidas/legacy.pdf';
        Storage::disk(FacturaStorage::storeDisk())->put($path, '%PDF legacy');

        SolicitudFacturaPdf::create([
            'solicitud_factura_id' => $factura->id,
            'path' => $path,
            'nombre_original' => 'legacy.pdf',
            'mime' => 'application/pdf',
            'orden' => 1,
        ]);

        $factura->refresh();
        $this->assertTrue($factura->tiene_pdf_emitido);
        $this->assertCount(1, $factura->pdfsEmitidos);
        $this->assertSame('legacy.pdf', $factura->pdfsEmitidos->first()->nombre_original);
    }
}
