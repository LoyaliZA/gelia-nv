<?php

namespace Tests\Unit\Clientes\Direcciones;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteDireccion;
use App\Services\Clientes\Direcciones\DetectorDireccionDuplicada;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Services\Clientes\Direcciones\NormalizadorDireccion;
use App\Services\Clientes\Direcciones\ValidadorIdentidadCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class GestionDireccionesPase1Test extends TestCase
{
    use RefreshDatabase;

    private function lista(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::firstOrCreate(
            ['nombre' => 'PUBLICO GENERAL'],
            ['monto_requerido' => 0, 'activo' => true]
        );
    }

    private function crearCliente(array $extra = []): Cliente
    {
        return Cliente::query()->create(array_merge([
            'numero_cliente' => '04950',
            'nombre' => 'Cliente Prueba',
            'lista_actual_id' => $this->lista()->id,
            'monto_venta_actual' => 0,
            'telefono' => '5512345678',
            'correo_electronico' => 'cliente@example.com',
            'direccion_contacto' => 'Av. Reforma 100',
            'colonia_contacto' => 'Centro',
            'municipio_contacto' => 'CDMX',
            'estado_contacto' => 'CDMX',
            'pais_contacto' => 'México',
            'cp_contacto' => '06000',
        ], $extra));
    }

    public function test_cliente_tiene_muchas_direcciones(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $d1 = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana',
            'calle' => 'Calle 1',
            'numero_exterior' => '10',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
        ], ['verificar' => true]);

        $d2 = $gestion->crearDireccionAdicional($cliente->id, [
            'nombre_destinatario' => 'Luis',
            'calle' => 'Calle 2',
            'numero_exterior' => '20',
            'colonia' => 'Roma',
            'codigo_postal' => '06700',
        ], ['verificar' => true]);

        $this->assertSame(1, $d1->numero_direccion);
        $this->assertSame(2, $d2->numero_direccion);
        $this->assertTrue($d1->es_principal);
        $this->assertFalse($d2->es_principal);
        $this->assertCount(2, $cliente->fresh()->direcciones);
    }

    public function test_versionado_no_sobrescribe_historial(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $original = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana',
            'calle' => 'Calle Vieja',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
        ], ['verificar' => true]);

        $nueva = $gestion->crearNuevaVersion($original->id, [
            'nombre_destinatario' => 'Ana',
            'calle' => 'Calle Nueva',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
        ], ['origen' => 'test']);

        $original = $original->fresh();

        $this->assertFalse($original->esta_activa);
        $this->assertTrue($nueva->esta_activa);
        $this->assertSame(2, $nueva->version);
        $this->assertSame($original->id, $nueva->direccion_anterior_id);
        $this->assertSame('Calle Vieja', $original->calle);
        $this->assertSame('Calle Nueva', $nueva->calle);
    }

    public function test_principal_unica_y_dual_write_contacto(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $d1 = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana',
            'telefono_destinatario' => '5599999999',
            'calle' => 'Calle Principal',
            'numero_exterior' => '5',
            'colonia' => 'Del Valle',
            'municipio' => 'Benito Juárez',
            'estado' => 'CDMX',
            'pais' => 'México',
            'codigo_postal' => '03100',
        ], ['verificar' => true]);

        $cliente = $cliente->fresh();
        $this->assertStringContainsString('Calle Principal', (string) $cliente->direccion_contacto);
        $this->assertSame('03100', $cliente->cp_contacto);
        $this->assertSame('5599999999', $cliente->telefono);

        $d2 = $gestion->crearDireccionAdicional($cliente->id, [
            'nombre_destinatario' => 'Luis',
            'calle' => 'Calle Secundaria',
            'colonia' => 'Roma',
            'codigo_postal' => '06700',
        ], ['verificar' => true]);

        $gestion->marcarComoPrincipal($d2->id);

        $this->assertFalse($d1->fresh()->es_principal);
        $this->assertTrue($d2->fresh()->es_principal);
        $this->assertSame(1, ClienteDireccion::query()->where('cliente_id', $cliente->id)->where('es_principal', true)->count());
    }

    public function test_normalizacion_y_duplicados(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);
        $normalizador = app(NormalizadorDireccion::class);

        $datos = $normalizador->ejecutar([
            'nombre_destinatario' => '  Ana   Pérez  ',
            'calle' => '  Av. Juárez  ',
            'numero_exterior' => '10',
            'colonia' => 'Centro',
            'codigo_postal' => '6000',
        ]);

        $this->assertSame('Ana Pérez', $datos['nombre_destinatario']);
        $this->assertSame('06000', $datos['codigo_postal']);

        $gestion->crearPrimeraDireccion($cliente->id, $datos, ['verificar' => true]);

        $dupes = app(DetectorDireccionDuplicada::class)->ejecutar($cliente->id, [
            'nombre_destinatario' => 'Ana Pérez',
            'calle' => 'Av. Juárez',
            'numero_exterior' => '10',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
        ]);

        $this->assertCount(1, $dupes);
    }

    public function test_numero_cliente_como_cadena_con_ceros(): void
    {
        $this->crearCliente(['numero_cliente' => '04950']);
        $validador = app(ValidadorIdentidadCliente::class);

        $exacto = $validador->buscarPorNumeroExacto('04950');
        $this->assertNotNull($exacto);
        $this->assertSame('04950', $exacto->numero_cliente);

        $sinCeros = $validador->buscarPorNumeroExacto('4950');
        $this->assertNull($sinCeros);
    }

    public function test_obtener_para_snapshot_y_listado(): void
    {
        $cliente = $this->crearCliente();
        $gestion = app(GestionDireccionesClienteService::class);

        $d = $gestion->crearPrimeraDireccion($cliente->id, [
            'nombre_destinatario' => 'Ana',
            'calle' => 'Calle 1',
            'colonia' => 'Centro',
            'codigo_postal' => '06000',
            'estado' => 'CDMX',
            'pais' => 'México',
        ], ['verificar' => true]);

        $lista = $gestion->listarActivasVerificadasPorCliente($cliente->id);
        $this->assertCount(1, $lista);
        $this->assertSame($d->id, $lista->first()['id']);

        $snap = $gestion->obtenerParaSnapshot($cliente->id, $d->id);
        $this->assertSame($d->id, $snap['cliente_direccion_id']);
        $this->assertSame('Ana', $snap['nombre_destinatario']);
        $this->assertArrayNotHasKey('rfc', $snap);
    }

    public function test_backfill_dry_run_no_escribe_e_idempotente(): void
    {
        $this->crearCliente();

        Artisan::call('direcciones:migrar-legado', ['--dry-run' => true]);
        $this->assertSame(0, ClienteDireccion::query()->count());

        Artisan::call('direcciones:migrar-legado');
        $this->assertSame(1, ClienteDireccion::query()->count());

        Artisan::call('direcciones:migrar-legado');
        $this->assertSame(1, ClienteDireccion::query()->count());
        $this->assertSame(ClienteDireccion::ORIGEN_LEGACY, ClienteDireccion::query()->first()->origen);
    }

    public function test_omitir_cliente_sin_direccion_contacto(): void
    {
        $this->crearCliente([
            'direccion_contacto' => null,
            'colonia_contacto' => null,
            'cp_contacto' => null,
            'municipio_contacto' => null,
            'estado_contacto' => null,
            'pais_contacto' => null,
        ]);

        Artisan::call('direcciones:migrar-legado');
        $this->assertSame(0, ClienteDireccion::query()->count());
    }
}
