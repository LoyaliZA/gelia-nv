<?php

namespace Tests\Feature\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\DocumentoVenta;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use App\Models\User;
use App\Services\Escalonamiento\AbrirPeriodoEscalonamiento;
use App\Services\Escalonamiento\ImportarDocumentosEscalonamiento;
use App\Services\Escalonamiento\ResolverIncidenciaEscalonamiento;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResolverIncidenciaEscalonamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_una_incidencia_no_responde_404(): void
    {
        $user = $this->operador();
        [$cliente, $bronce, $plata] = $this->clienteConListas();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $this->resumen($periodo->id, $cliente->id, $bronce->id, $plata->id, '6000.00');
        $incidencia = $this->divergencia($periodo->id, $cliente->id);

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs($user)
            ->post(route('escalonamiento.incidencias.resolver', $incidencia), [
                'accion' => 'alinear_lista_operativa',
            ])
            ->assertRedirect(route('escalonamiento.index', [
                'tab' => 'incidencias',
                'periodo_id' => $periodo->id,
            ]));

        $cliente->refresh();
        $this->assertSame($plata->id, $cliente->lista_actual_id);
        $this->assertSame('6000.00', (string) $cliente->monto_venta_actual);
        $this->assertSame('resuelta', $incidencia->fresh()->estado);
    }

    public function test_la_lista_protegida_no_se_alinea_en_lote(): void
    {
        $user = $this->operador();
        [$libre, $bronce, $plata] = $this->clienteConListas('9810');
        $protegido = Cliente::create([
            'numero_cliente' => '9811',
            'nombre' => 'Cliente protegido',
            'lista_actual_id' => $bronce->id,
            'monto_venta_actual' => 10,
            'lista_bloqueada' => true,
        ]);
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $this->resumen($periodo->id, $libre->id, $bronce->id, $plata->id, '6000.00');
        $this->resumen($periodo->id, $protegido->id, $bronce->id, $plata->id, '6000.00');
        $this->divergencia($periodo->id, $libre->id);
        $bloqueada = $this->divergencia($periodo->id, $protegido->id);

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs($user)
            ->post(route('escalonamiento.incidencias.resolver_lote'), [
                'periodo_id' => $periodo->id,
                'codigo' => 'divergencia_lista_operativa',
                'accion' => 'alinear_lista_operativa',
            ])
            ->assertRedirect();

        $this->assertSame($plata->id, $libre->fresh()->lista_actual_id);
        $this->assertSame($bronce->id, $protegido->fresh()->lista_actual_id);
        $this->assertSame('abierta', $bloqueada->fresh()->estado);
    }

    public function test_crear_cliente_y_registrar_documentos_desde_la_incidencia(): void
    {
        Storage::fake('local');
        $this->listaPublicoGeneral();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);
        $previa = $importar->previsualizar($periodo, $this->csv([
            ['remision', 'F-1', '', '', '9777', 'Cliente Nuevo', 'MXN', '1500.00', '2026-10-04', 'activo', ''],
        ]), null, 'remision');
        $importar->confirmar($previa->id, null);

        $incidencia = EscalonamientoIncidencia::query()->where('codigo', 'cliente_no_identificado')->first();
        $this->assertNotNull($incidencia);
        $this->assertSame('9777', $incidencia->contexto['numero_cliente']);
        $this->assertCount(1, $incidencia->contexto['documentos']);
        $this->assertNull(DocumentoVenta::query()->where('folio', 'F-1')->first());

        app(ResolverIncidenciaEscalonamiento::class)->resolver($incidencia, 'crear_cliente_y_registrar', null);

        $cliente = Cliente::query()->where('numero_cliente', '9777')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('Cliente Nuevo', $cliente->nombre);
        $this->assertNotNull(DocumentoVenta::query()->where('folio', 'F-1')->first());
        $this->assertSame('resuelta', $incidencia->fresh()->estado);
    }

    public function test_la_previsualizacion_puede_crear_el_cliente_y_dejar_el_documento_para_confirmar(): void
    {
        Storage::fake('local');
        $this->listaPublicoGeneral();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);
        $previa = $importar->previsualizar($periodo, $this->csv([
            ['remision', 'F-2', '', '', '9778', 'Cliente Preview', 'MXN', '800.00', '2026-10-05', 'activo', ''],
        ]), null, 'remision');

        $this->assertSame('incidencia', $previa->filas->first()->resultado);

        app(ResolverIncidenciaEscalonamiento::class)->resolverPrevisualizacion($previa, 'crear_cliente_y_registrar', null);

        $this->assertNotNull(Cliente::query()->where('numero_cliente', '9778')->first());
        $this->assertNull(DocumentoVenta::query()->where('folio', 'F-2')->first());
        $this->assertSame('alta', $previa->filas()->first()->resultado);

        $importar->confirmar($previa->id, null);
        $this->assertNotNull(DocumentoVenta::query()->where('folio', 'F-2')->first());
    }

    public function test_una_incidencia_antigua_toma_el_numero_desde_el_motivo(): void
    {
        $this->listaPublicoGeneral();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $incidencia = EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'gravedad' => 'bloquea',
            'codigo' => 'cliente_no_identificado',
            'motivo' => 'No hay un cliente con el número 9666. El nombre no asigna el documento.',
            'estado' => 'abierta',
        ]);

        $acciones = array_column(
            app(ResolverIncidenciaEscalonamiento::class)->accionesPara($incidencia),
            'id',
        );
        $this->assertContains('crear_cliente', $acciones);

        app(ResolverIncidenciaEscalonamiento::class)->resolver($incidencia, 'crear_cliente', null);

        $this->assertNotNull(Cliente::query()->where('numero_cliente', '9666')->first());
        $this->assertSame('resuelta', $incidencia->fresh()->estado);
    }

    public function test_un_cliente_solo_por_nombre_se_crea_con_el_numero_indicado(): void
    {
        $this->listaPublicoGeneral();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $incidencia = EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodo->id,
            'gravedad' => 'bloquea',
            'codigo' => 'cliente_no_identificado',
            'motivo' => 'No hay un cliente con el nombre Cliente Solo Nombre. El nombre no asigna el documento.',
            'estado' => 'abierta',
        ]);

        app(ResolverIncidenciaEscalonamiento::class)->resolver($incidencia, 'crear_cliente', null, [
            'numero_cliente' => '9555',
        ]);

        $cliente = Cliente::query()->where('numero_cliente', '9555')->first();
        $this->assertNotNull($cliente);
        $this->assertSame('Cliente Solo Nombre', $cliente->nombre);
    }

    public function test_solo_agregar_a_la_base_no_registra_el_documento_al_confirmar(): void
    {
        Storage::fake('local');
        $this->listaPublicoGeneral();
        $periodo = app(AbrirPeriodoEscalonamiento::class)->abrir(2026, 10);
        $importar = app(ImportarDocumentosEscalonamiento::class);
        $previa = $importar->previsualizar($periodo, $this->csv([
            ['remision', 'F-3', '', '', '9779', 'Cliente Omitido', 'MXN', '400.00', '2026-10-05', 'activo', ''],
        ]), null, 'remision');

        app(ResolverIncidenciaEscalonamiento::class)->resolverPrevisualizacion($previa, 'crear_cliente', null);
        $importar->confirmar($previa->id, null);

        $this->assertNotNull(Cliente::query()->where('numero_cliente', '9779')->first());
        $this->assertNull(DocumentoVenta::query()->where('folio', 'F-3')->first());
        $this->assertSame('omitida', $previa->filas()->first()->resultado);
    }

    private function operador(): User
    {
        $user = User::factory()->create();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['escalonamiento.ver', 'escalonamiento.operar'] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    /**
     * @return array{0: Cliente, 1: CatalogoListaDescuento, 2: CatalogoListaDescuento}
     */
    private function clienteConListas(string $numero = '9801'): array
    {
        $bronce = CatalogoListaDescuento::create([
            'nombre' => 'BRONCE '.$numero,
            'monto_requerido' => 1000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $plata = CatalogoListaDescuento::create([
            'nombre' => 'PLATA '.$numero,
            'monto_requerido' => 5000,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
        $cliente = Cliente::create([
            'numero_cliente' => $numero,
            'nombre' => 'Cliente '.$numero,
            'lista_actual_id' => $bronce->id,
            'monto_venta_actual' => 10,
        ]);

        return [$cliente, $bronce, $plata];
    }

    private function listaPublicoGeneral(): CatalogoListaDescuento
    {
        return CatalogoListaDescuento::create([
            'nombre' => 'Público General',
            'monto_requerido' => 0,
            'activo' => true,
            'participa_escalonamiento' => true,
        ]);
    }

    private function resumen(int $periodoId, int $clienteId, int $baseId, int $vigenteId, string $acumulado): void
    {
        EscalonamientoResumenCliente::create([
            'escalonamiento_periodo_id' => $periodoId,
            'cliente_id' => $clienteId,
            'acumulado' => $acumulado,
            'lista_base_id' => $baseId,
            'clasificacion_mes_id' => $vigenteId,
            'clasificacion_mes_max_id' => $vigenteId,
            'lista_vigente_id' => $vigenteId,
        ]);
    }

    private function divergencia(int $periodoId, int $clienteId): EscalonamientoIncidencia
    {
        return EscalonamientoIncidencia::create([
            'escalonamiento_periodo_id' => $periodoId,
            'cliente_id' => $clienteId,
            'gravedad' => 'aviso',
            'codigo' => 'divergencia_lista_operativa',
            'motivo' => 'Lista operativa distinta de la vigente.',
            'estado' => 'abierta',
        ]);
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function csv(array $filas): UploadedFile
    {
        $lineas = ['tipo,folio,serie,sucursal,numero_cliente,nombre,moneda,total,fecha,estado,remision_original'];
        foreach ($filas as $fila) {
            $lineas[] = implode(',', $fila);
        }

        return UploadedFile::fake()->createWithContent('remisiones.csv', implode("\n", $lineas));
    }
}
