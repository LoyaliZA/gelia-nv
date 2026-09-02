<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $gerente;

    private Sucursal $sucursal;

    private Sucursal $otraSucursal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->otraSucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Sur']);

        $this->operador = User::factory()->create(['username' => 'operador_pdv']);
        $this->operador->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->operador->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->gerente = User::factory()->create(['username' => 'gerente_pdv']);
        $this->gerente->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CORREGIR,
        ]);
        $this->gerente->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_auditoria_expone_secuencia_operativa_completa(): void
    {
        $base = Carbon::parse('2026-08-01 10:00:00');
        Carbon::setTestNow($base);

        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
            'estado' => ResguardoPdv::ESTADO_DEVUELTO,
            'version' => 4,
        ]);

        $recepcion = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => $base->copy()->addHour(),
            'snapshot_json' => [
                'cantidad_recibida' => 1,
                'cantidad_esperada' => 1,
                'recepcion_completa' => true,
            ],
            'idempotency_key' => 'aud-rec-'.$resguardo->id,
        ]);

        $incidencia = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_FALTANTE,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => $base->copy()->addHours(2),
            'snapshot_json' => [
                'incidencia_id' => 10,
                'descripcion' => 'Faltaba un bulto',
            ],
            'idempotency_key' => 'aud-inc-'.$resguardo->id,
        ]);

        $resolucion = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_ENTREGA_AUTORIZADA,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->gerente->id,
            'ocurrido_at' => $base->copy()->addHours(3),
            'snapshot_json' => [
                'incidencia_id' => 10,
                'motivo_autorizacion' => 'Autorizado por gerencia',
            ],
            'idempotency_key' => 'aud-res-'.$resguardo->id,
        ]);

        $entrega = ResguardoPdvEntrega::query()->create([
            'resguardo_id' => $resguardo->id,
            'relacion' => ResguardoPdvEntrega::RELACION_TITULAR,
            'nombre_quien_retira' => 'Cliente titular',
            'entregado_por_id' => $this->operador->id,
            'entregado_at' => $base->copy()->addHours(4),
            'snapshot_json' => [
                'integracion_cp' => [
                    'estado' => 'completada',
                    'completada_at' => $base->copy()->addHours(5)->toIso8601String(),
                ],
            ],
            'idempotency_key' => 'aud-ent-'.$resguardo->id,
            'version' => 1,
        ]);

        $entregaEvento = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_ENTREGADO,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => $base->copy()->addHours(4),
            'snapshot_json' => [
                'entrega_id' => $entrega->id,
                'receptor' => ['nombre' => 'Cliente titular', 'relacion' => 'titular'],
            ],
            'idempotency_key' => 'aud-evt-ent-'.$resguardo->id,
        ]);

        $devolucion = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_DEVUELTO,
            'actor_id' => $this->gerente->id,
            'ocurrido_at' => $base->copy()->addHours(6),
            'snapshot_json' => [
                'motivo' => 'Cancelación del pedido',
                'integracion_cp' => [
                    'estado' => 'completada',
                    'completada_at' => $base->copy()->addHours(7)->toIso8601String(),
                ],
            ],
            'idempotency_key' => 'aud-dev-'.$resguardo->id,
        ]);

        $response = $this->actingAs($this->operador)
            ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
            ->assertOk()
            ->assertJsonStructure([
                'timeline',
                'filtros',
                'total',
                'catalogos' => ['eventos', 'categorias'],
            ]);

        $tipos = collect($response->json('timeline'))
            ->where('origen', 'evento')
            ->pluck('tipo_evento')
            ->all();

        $this->assertSame([
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            ResguardoPdvEvento::TIPO_INCIDENCIA_FALTANTE,
            ResguardoPdvEvento::TIPO_INCIDENCIA_ENTREGA_AUTORIZADA,
            ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
            ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA,
        ], $tipos);

        $this->assertTrue(
            collect($response->json('timeline'))->contains(
                fn (array $item) => $item['origen'] === 'integracion_cp'
                    && str_contains($item['tipo_etiqueta'], 'Entrega')
            )
        );

        $this->assertNotNull($recepcion->id);
        $this->assertNotNull($incidencia->id);
        $this->assertNotNull($resolucion->id);
        $this->assertNotNull($entregaEvento->id);
        $this->assertNotNull($devolucion->id);
    }

    public function test_orden_estable_por_ocurrido_at_e_id(): void
    {
        $marca = Carbon::parse('2026-08-10 12:00:00');

        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        $primero = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => $marca,
            'idempotency_key' => 'ord-1-'.$resguardo->id,
        ]);

        $segundo = ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => $marca,
            'idempotency_key' => 'ord-2-'.$resguardo->id,
        ]);

        $ids = collect(
            $this->actingAs($this->operador)
                ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
                ->json('timeline')
        )->where('origen', 'evento')->pluck('evento_id')->all();

        $this->assertSame([$primero->id, $segundo->id], $ids);
    }

    public function test_metadata_incompleta_historica_se_expone_sin_romper_consulta(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->gerente->id,
            'ocurrido_at' => now(),
            'snapshot_json' => ['motivo' => 'Corrección parcial'],
            'idempotency_key' => 'meta-inc-'.$resguardo->id,
        ]);

        $item = $this->actingAs($this->gerente)
            ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
            ->assertOk()
            ->json('timeline.0');

        $this->assertSame('Corrección administrativa', $item['tipo_etiqueta']);
        $this->assertNotEmpty($item['metadata_legible']);
        $this->assertArrayHasKey('metadata_original', $item);
    }

    public function test_oculta_datos_sensibles_sin_permiso_corregir(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_ENTREGA_TERCERO,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_ENTREGADO,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now(),
            'snapshot_json' => [
                'receptor' => ['nombre' => 'Persona sensible', 'relacion' => 'tercero'],
                'integracion_cp' => [
                    'estado' => 'pendiente',
                    'ultimo_error' => 'Detalle técnico interno',
                    'idempotency_key' => 'secreto',
                ],
            ],
            'idempotency_key' => 'sens-'.$resguardo->id,
        ]);

        $sinCorregir = User::factory()->create();
        $sinCorregir->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $sinCorregir->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $item = $this->actingAs($sinCorregir)
            ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
            ->assertOk()
            ->json('timeline.0');

        $this->assertSame('[restringido]', $item['metadata_original']['receptor']['nombre'] ?? null);
        $this->assertSame('[restringido]', $item['metadata_original']['integracion_cp']['ultimo_error'] ?? null);
    }

    public function test_auditoria_otra_sucursal_devuelve_404(): void
    {
        $ajeno = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->otraSucursal->id,
        ]);

        $this->actingAs($this->operador)
            ->getJson(route('punto_venta.resguardos.auditoria', $ajeno))
            ->assertNotFound();
    }

    public function test_sin_permiso_ver_auditoria_se_niega(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        $usuario = User::factory()->create();
        $usuario->givePermissionTo(PuntoVentaModulo::PERMISO_ACCEDER);
        $usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($usuario)
            ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
            ->assertForbidden();
    }

    public function test_consulta_no_altera_eventos(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_ESPERADA_CREADA,
            'estado_anterior' => null,
            'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now(),
            'idempotency_key' => 'ro-'.$resguardo->id,
        ]);

        $conteoAntes = ResguardoPdvEvento::query()->count();
        $updatedAntes = ResguardoPdvEvento::query()->first()->updated_at;

        $this->actingAs($this->operador)
            ->getJson(route('punto_venta.resguardos.auditoria', $resguardo))
            ->assertOk();

        $this->assertSame($conteoAntes, ResguardoPdvEvento::query()->count());
        $this->assertTrue($updatedAntes->equalTo(ResguardoPdvEvento::query()->first()->updated_at));
    }

    public function test_filtro_por_tipo_evento(): void
    {
        $resguardo = ResguardoPdv::factory()->create([
            'sucursal_id' => $this->sucursal->id,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            'estado_anterior' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now()->subHour(),
            'idempotency_key' => 'f1-'.$resguardo->id,
        ]);

        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_DANO,
            'estado_anterior' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'estado_nuevo' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'actor_id' => $this->operador->id,
            'ocurrido_at' => now(),
            'snapshot_json' => ['incidencia_id' => 1],
            'idempotency_key' => 'f2-'.$resguardo->id,
        ]);

        $timeline = $this->actingAs($this->operador)
            ->getJson(route('punto_venta.resguardos.auditoria', [
                'resguardo' => $resguardo,
                'tipo_evento' => ResguardoPdvEvento::TIPO_INCIDENCIA_DANO,
            ]))
            ->assertOk()
            ->json('timeline');

        $this->assertCount(1, $timeline);
        $this->assertSame(ResguardoPdvEvento::TIPO_INCIDENCIA_DANO, $timeline[0]['tipo_evento']);
    }

    private function activarModulo(): void
    {
        ConfiguracionSistema::query()->updateOrCreate(
            ['clave' => PuntoVentaModulo::CLAVE_FLAG],
            ['valor' => true, 'tipo' => 'boolean']
        );
    }

    private function seedPermisos(): void
    {
        foreach (PuntoVentaModulo::permisosIniciales() as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }
}
