<?php

namespace Tests\Feature\ComisionesEmpaque;

use App\Models\Almacen;
use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\AsignacionComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteComisionEmpaque;
use App\Models\ComisionesEmpaque\CorteUnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\ParticipanteComisionEmpaque;
use App\Models\ComisionesEmpaque\PeriodoComisionEmpaque;
use App\Models\ComisionesEmpaque\RepartoComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UsuarioAreaComisionEmpaque;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\OperacionEmpaque;
use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaBultoEmpaque;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Services\ComisionesEmpaque\AlcanceAreaComisionEmpaque;
use App\Services\ComisionesEmpaque\CargarEmpaquesComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ComprobarFuenteCorteComisionEmpaque;
use App\Services\ComisionesEmpaque\ConfigurarComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConfirmarCorteComisionEmpaqueService;
use App\Services\ComisionesEmpaque\ConsultarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\ExportarComisionesEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarPeriodoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\GestionarUnidadComisionEmpaqueService;
use App\Services\ComisionesEmpaque\GuardarEquipoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\RegistrarPagoComisionEmpaqueService;
use App\Services\ComisionesEmpaque\SincronizarComisionesEmpaqueService;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Rap2hpoutre\FastExcel\FastExcel;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ComisionesEmpaqueTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        ConfiguracionComisionEmpaque::actual()->update(['fecha_inicio' => '2026-01-01']);
        foreach ([
            'gestion_interna.comisiones_empaque.ver',
            'gestion_interna.comisiones_empaque.ver_todas',
            'gestion_interna.comisiones_empaque.capturar',
            'gestion_interna.comisiones_empaque.clasificar',
            'gestion_interna.comisiones_empaque.confirmar_cortes',
            'gestion_interna.comisiones_empaque.registrar_pagos',
            'gestion_interna.comisiones_empaque.exportar',
            'gestion_interna.comisiones_empaque.configurar',
        ] as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
    }

    public function test_caja_de_cedis_comisiona_sin_usar_el_costo_de_envio(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-10 12:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 9, 'costo_envio' => 500]);
        $this->caja($pedido, 4);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $unidad = UnidadComisionEmpaque::query()->where('procedencia', 'cedis_caja')->first();
        $this->assertNotNull($unidad);
        $this->assertSame(AreaComisionEmpaque::query()->where('codigo', 'cedis')->value('id'), $unidad->area_id);
        $this->assertSame(4, $unidad->cantidad_comisionable);
        $this->assertSame(4000, $unidad->importe_centavos);
        $this->assertSame(1, UnidadComisionEmpaque::query()->count());
        $this->assertSame('500.00', (string) $pedido->fresh()->costo_envio);
    }

    public function test_tienda_sin_pesaje_queda_pendiente_de_clasificacion_y_no_entra_a_cedis(): void
    {
        $pedido = $this->pedido(['peso_real_kg' => null, 'origen_solicitud' => PedidoBma::ORIGEN_SOLICITUD_CALL_CENTER]);
        $this->cumplimiento($pedido, 3);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $unidad = UnidadComisionEmpaque::query()->first();
        $this->assertSame('tienda_cumplimiento', $unidad->procedencia);
        $this->assertSame('tienda', $unidad->area->codigo);
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_CLASIFICACION, $unidad->situacion);
        $this->assertNull($unidad->importe_centavos);
        $this->assertNull($pedido->fresh()->peso_real_kg);
    }

    public function test_la_bolsa_no_genera_comision_y_el_desglose_debe_cuadrar(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-10 12:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 6]);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->first();
        $tipo = DB::table('catalogo_tipos_caja_pedido')->value('id');

        $this->expectException(ValidationException::class);
        try {
            app(GestionarUnidadComisionEmpaqueService::class)->clasificar($unidad, [
                ['catalogo_tipo_caja_id' => $tipo, 'cantidad' => 3, 'es_bolsa' => false],
            ], $this->usuario);
        } finally {
            $unidad->refresh();
            $this->assertSame(UnidadComisionEmpaque::PENDIENTE_CLASIFICACION, $unidad->situacion);
        }
    }

    public function test_clasificacion_excluye_bolsas_y_cobra_la_tarifa_del_area(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-10 12:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 6]);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->first();
        $tipoA = DB::table('catalogo_tipos_caja_pedido')->value('id');
        $tipoB = DB::table('catalogo_tipos_caja_pedido')->where('id', '!=', $tipoA)->value('id') ?: $tipoA;

        app(GestionarUnidadComisionEmpaqueService::class)->clasificar($unidad, [
            ['catalogo_tipo_caja_id' => $tipoA, 'cantidad' => 3, 'es_bolsa' => false],
            ['catalogo_tipo_caja_id' => $tipoB, 'cantidad' => 2, 'es_bolsa' => false],
            ['catalogo_tipo_caja_id' => null, 'cantidad' => 1, 'es_bolsa' => true],
        ], $this->usuario);

        $unidad->refresh();
        $this->assertSame(5, $unidad->cantidad_comisionable);
        $this->assertSame(5000, $unidad->importe_centavos);
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $unidad->situacion);

        $soloBolsas = $this->pedido(['folio' => 'BOLSAS', 'empacado_at' => '2026-10-10 13:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 2]);
        $servicio = app(SincronizarComisionesEmpaqueService::class);
        $servicio->ejecutar($this->usuario);
        $unidadBolsas = UnidadComisionEmpaque::query()->where('pedido_bma_id', $soloBolsas->id)->first();
        app(GestionarUnidadComisionEmpaqueService::class)->clasificar($unidadBolsas, [
            ['catalogo_tipo_caja_id' => null, 'cantidad' => 2, 'es_bolsa' => true],
        ], $this->usuario);
        $servicio->ejecutar($this->usuario);
        $servicio->ejecutar($this->usuario);
        $unidadBolsas = $unidadBolsas->fresh();
        $this->assertSame(UnidadComisionEmpaque::EXCLUIDA, $unidadBolsas->situacion);
        $this->assertSame(0, $unidadBolsas->importe_centavos);
    }

    public function test_salida_de_participante_conserva_el_primer_corte_y_paga_solo_su_parte(): void
    {
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $personas = [];
        foreach (['A' => 40, 'B' => 25, 'C' => 20, 'D' => 15] as $nombre => $porcentaje) {
            $user = User::factory()->create(['name' => $nombre]);
            $personas[$nombre] = ParticipanteComisionEmpaque::query()->create([
                'nombre' => $nombre, 'user_id' => $user->id, 'activo' => true,
            ]);
        }
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [
            [$personas['A']->id, '40'], [$personas['B']->id, '25'], [$personas['C']->id, '20'], [$personas['D']->id, '15'],
        ]);

        $primero = $this->pedido(['folio' => 'P-1000', 'empacado_at' => '2026-10-10 10:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($primero, 100);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $corte = $this->confirmar($cedis->id, '2026-10-01', '2026-10-14');
        $d = AsignacionComisionEmpaque::query()->where('corte_id', $corte->id)->where('participante_id', $personas['D']->id)->first();
        $this->assertSame(15000, $d->importe_centavos);

        app(RegistrarPagoComisionEmpaqueService::class)->pagar(
            $personas['D']->id, '2026-10-14', '150.00',
            [['asignacion_id' => $d->id, 'importe' => '150.00']],
            'salida', 'transferencia', null, 'pago-d-1', $this->usuario
        );
        app(RegistrarPagoComisionEmpaqueService::class)->pagar(
            $personas['D']->id, '2026-10-14', '150.00',
            [['asignacion_id' => $d->id, 'importe' => '150.00']],
            'salida', 'transferencia', null, 'pago-d-1', $this->usuario
        );
        $this->assertSame(15000, $d->fresh()->pagado_centavos);

        $personas['D']->update(['activo' => false]);
        $this->reparto($cedis->id, '2026-10-15 00:00:00', [
            [$personas['A']->id, '50'], [$personas['B']->id, '30'], [$personas['C']->id, '20'],
        ]);

        $segundo = $this->pedido(['folio' => 'P-200', 'empacado_at' => '2026-10-16 10:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($segundo, 20);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $corte2 = $this->confirmar($cedis->id, '2026-10-15', '2026-10-30');

        $this->assertSame(40000, AsignacionComisionEmpaque::query()->where('corte_id', $corte->id)->where('participante_id', $personas['A']->id)->value('importe_centavos'));
        $this->assertSame(0, AsignacionComisionEmpaque::query()->where('corte_id', $corte->id)->where('participante_id', $personas['A']->id)->value('pagado_centavos'));
        $this->assertSame(10000, AsignacionComisionEmpaque::query()->where('corte_id', $corte2->id)->where('participante_id', $personas['A']->id)->value('importe_centavos'));
        $this->assertNull(AsignacionComisionEmpaque::query()->where('corte_id', $corte2->id)->where('participante_id', $personas['D']->id)->first());
        $this->assertSame('pagado', $this->estadoDe($personas['D']->id, $corte->id));
    }

    public function test_pago_parcial_no_permite_sobrepago_ni_doble_inclusion_de_corte(): void
    {
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'A', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);
        $pedido = $this->pedido(['empacado_at' => '2026-10-02 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $corte = $this->confirmar($cedis->id, '2026-10-01', '2026-10-15');
        $asignacion = AsignacionComisionEmpaque::query()->where('corte_id', $corte->id)->first();

        app(RegistrarPagoComisionEmpaqueService::class)->pagar($participante->id, '2026-10-15', '4.00', [
            ['asignacion_id' => $asignacion->id, 'importe' => '4.00'],
        ], null, null, null, 'parcial-1', $this->usuario);
        $this->assertSame(400, $asignacion->fresh()->pagado_centavos);

        $this->expectException(ValidationException::class);
        app(RegistrarPagoComisionEmpaqueService::class)->pagar($participante->id, '2026-10-15', '9.00', [
            ['asignacion_id' => $asignacion->id, 'importe' => '9.00'],
        ], null, null, null, 'parcial-2', $this->usuario);
    }

    public function test_dos_confirmaciones_no_incluyen_la_misma_unidad(): void
    {
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'A', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);
        $pedido = $this->pedido(['empacado_at' => '2026-10-03 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 2);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $this->confirmar($cedis->id, '2026-10-01', '2026-10-20');

        $this->expectException(ValidationException::class);
        $this->confirmar($cedis->id, '2026-10-01', '2026-10-20');
        $this->assertSame(1, CorteUnidadComisionEmpaque::query()->count());
    }

    public function test_consolidacion_no_suma_la_cabecera_ni_el_total_de_la_operacion(): void
    {
        $clienteId = $this->cliente();
        $principal = $this->pedido(['folio' => 'PRIN', 'cliente_id' => $clienteId, 'empacado_at' => '2026-10-04 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 3]);
        $miembro = $this->pedido(['folio' => 'MIEM', 'cliente_id' => $clienteId, 'empacado_at' => '2026-10-04 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 3]);
        $this->caja($principal, 3);
        $operacion = OperacionEmpaque::query()->create([
            'folio_operacion' => 'OP-1', 'cliente_id' => $clienteId, 'numero_cajas' => 6, 'estatus' => OperacionEmpaque::ESTATUS_EMPACADA,
        ]);
        OperacionEmpaqueMiembro::query()->create(['operacion_empaque_id' => $operacion->id, 'pedido_bma_id' => $principal->id, 'es_principal' => true, 'orden' => 0]);
        OperacionEmpaqueMiembro::query()->create(['operacion_empaque_id' => $operacion->id, 'pedido_bma_id' => $miembro->id, 'es_principal' => false, 'orden' => 1]);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertSame(1, UnidadComisionEmpaque::query()->count());
        $this->assertSame(3, UnidadComisionEmpaque::query()->value('cantidad_comisionable'));
        $this->assertSame('cedis_caja', UnidadComisionEmpaque::query()->value('procedencia'));
    }

    public function test_complemento_conserva_sus_bultos_sin_sumar_la_cabecera_del_principal(): void
    {
        $principal = $this->pedido(['folio' => 'PADRE', 'empacado_at' => '2026-10-05 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 2]);
        $this->caja($principal, 2);
        $complemento = $this->pedido([
            'folio' => 'HIJO', 'pedido_principal_id' => $principal->id,
            'empacado_at' => '2026-10-05 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 1,
        ]);
        PedidoBmaBultoEmpaque::query()->create([
            'pedido_bma_id' => $complemento->id, 'numero' => 1, 'empacado_por_id' => $this->usuario->id, 'empacado_at' => '2026-10-05 09:00:00',
        ]);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertSame(2, UnidadComisionEmpaque::query()->count());
        $this->assertTrue(UnidadComisionEmpaque::query()->where('procedencia', 'cedis_caja')->where('cantidad_comisionable', 2)->exists());
        $this->assertTrue(UnidadComisionEmpaque::query()->where('procedencia', 'cedis_bulto')->where('situacion', UnidadComisionEmpaque::PENDIENTE_CLASIFICACION)->exists());
    }

    public function test_cancelacion_queda_pendiente_y_el_corte_confirmado_no_se_reescribe(): void
    {
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'A', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);
        $pedido = $this->pedido(['empacado_at' => '2026-10-06 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $corte = $this->confirmar($cedis->id, '2026-10-01', '2026-10-10');
        $pedido->update(['cancelado_at' => now(), 'motivo_cancelacion' => 'cliente']);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $unidad = UnidadComisionEmpaque::query()->first();
        $this->assertSame($corte->id, $unidad->corte_id);
        $this->assertSame(1000, $corte->fresh()->total_centavos);
        $this->assertTrue($unidad->incidencias()->where('tipo', 'cancelacion')->where('estado', 'pendiente')->exists());
    }

    public function test_reempaque_despues_de_reversion_no_entra_solo_al_acumulado(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-07 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 1]);
        $bulto = PedidoBmaBultoEmpaque::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero' => 1, 'empacado_por_id' => $this->usuario->id, 'empacado_at' => $pedido->empacado_at,
        ]);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $pedido->update(['empacado_at' => null, 'empacado_por_id' => null]);
        $bulto->delete();
        PedidoBmaHistorialEstado::query()->create([
            'pedido_bma_id' => $pedido->id,
            'usuario_id' => $this->usuario->id,
            'accion' => AccionesHistorialPedidoBma::REVERTIR_EMPAQUE,
            'estatus_nuevo_id' => $pedido->catalogo_estatus_pedido_id,
            'comentarios' => 'Revertido',
        ]);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $this->assertSame(UnidadComisionEmpaque::REVERTIDA_REVISION, UnidadComisionEmpaque::query()->value('situacion'));

        $pedido->update(['empacado_at' => '2026-10-08 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 1]);
        PedidoBmaBultoEmpaque::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero' => 1, 'empacado_por_id' => $this->usuario->id, 'empacado_at' => '2026-10-08 09:00:00',
        ]);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertTrue(UnidadComisionEmpaque::query()->where('situacion', UnidadComisionEmpaque::REEMPAQUE_REVISION)->exists());
        $this->assertFalse(UnidadComisionEmpaque::query()->where('situacion', UnidadComisionEmpaque::ELEGIBLE)->exists());
    }

    public function test_no_importa_historicos_anteriores_a_la_fecha_de_inicio_y_marca_la_incorporacion_tardia(): void
    {
        ConfiguracionComisionEmpaque::actual()->update(['fecha_inicio' => '2026-10-01']);
        $viejo = $this->pedido(['folio' => 'VIEJO', 'empacado_at' => '2025-12-01 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($viejo, 2);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $this->assertSame(0, UnidadComisionEmpaque::query()->count());

        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'A', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);
        $pedido = $this->pedido(['empacado_at' => '2026-10-09 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $this->confirmar($cedis->id, '2026-10-01', '2026-10-31');

        $tarde = $this->pedido(['folio' => 'TARDE', 'empacado_at' => '2026-10-12 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($tarde, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->where('pedido_bma_id', $tarde->id)->first();
        $this->assertTrue($unidad->incorporacion_tardia);
        $this->assertNull($unidad->corte_id);
    }

    public function test_el_fin_del_periodo_deja_fuera_empaques_posteriores(): void
    {
        ConfiguracionComisionEmpaque::actual()->update([
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-15',
        ]);
        $dentro = $this->pedido(['folio' => 'DENTRO', 'empacado_at' => '2026-10-10 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $despues = $this->pedido(['folio' => 'DESPUES', 'empacado_at' => '2026-10-20 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($dentro, 1);
        $this->caja($despues, 1);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertTrue(UnidadComisionEmpaque::query()->where('pedido_bma_id', $dentro->id)->exists());
        $this->assertFalse(UnidadComisionEmpaque::query()->where('pedido_bma_id', $despues->id)->exists());
    }

    public function test_resguardo_empacado_en_cedis_y_en_tienda_cuenta_en_ambas(): void
    {
        $pedido = $this->pedido([
            'es_resguardo' => true,
            'empacado_at' => '2026-10-11 09:00:00',
            'empacado_por_id' => $this->usuario->id,
        ]);
        $this->caja($pedido, 1);
        $this->cumplimiento($pedido, 1);

        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertSame(2, UnidadComisionEmpaque::query()->count());
        $this->assertSame(2, UnidadComisionEmpaque::query()->where('excepcion_resguardo', true)->count());
        $this->assertEqualsCanonicalizing(['cedis', 'tienda'], UnidadComisionEmpaque::query()->with('area')->get()->map(fn ($u) => $u->area->codigo)->all());
    }

    public function test_registro_manual_no_entra_hasta_la_revision(): void
    {
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $otra = User::factory()->create();
        $unidad = app(GestionarUnidadComisionEmpaqueService::class)->registrarManual([
            'area_id' => $cedis->id,
            'empacador_user_id' => $otra->id,
            'clave_idempotencia' => 'cap-1',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 2,
            'cliente_nombre' => 'Cliente tienda',
            'folio_pedido' => 'PED-MANUAL',
            'notas' => 'Empaque en tienda',
        ], $this->usuario);

        $this->assertSame('cedis', $unidad->area->codigo);
        $this->assertSame($this->usuario->id, $unidad->empacador_user_id);
        $this->assertSame($this->usuario->id, $unidad->capturado_por_id);
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_REVISION, $unidad->situacion);
        $this->assertNull($unidad->importe_centavos);
        app(GestionarUnidadComisionEmpaqueService::class)->revisarManual($unidad, 'aceptar', 'Corresponde a tienda', $this->usuario);
        $aceptada = $unidad->fresh();
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $aceptada->situacion);
        $this->assertSame(2000, $aceptada->importe_centavos);
        $this->assertSame('aceptada', $aceptada->revision_estado);
        $this->assertNotNull($aceptada->revision_at);
        $this->assertNotNull($aceptada->incorporado_at);
    }

    public function test_sin_permiso_no_consulta_ni_exportar_confirma_un_corte(): void
    {
        $this->actingAs(User::factory()->create())->get(route('gestion_interna.comisiones_empaque.index'))->assertForbidden();

        $this->usuario->givePermissionTo('gestion_interna.comisiones_empaque.ver');
        $this->actingAs($this->usuario)->get(route('gestion_interna.comisiones_empaque.exportar.acumulado'))->assertForbidden();

        $this->usuario->givePermissionTo([
            'gestion_interna.comisiones_empaque.exportar',
            'gestion_interna.comisiones_empaque.confirmar_cortes',
        ]);
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        UsuarioAreaComisionEmpaque::query()->create(['user_id' => $this->usuario->id, 'area_id' => $cedis->id]);
        $borrador = CorteComisionEmpaque::query()->create([
            'area_id' => $cedis->id,
            'estado' => CorteComisionEmpaque::BORRADOR,
            'limite_desde' => '2026-10-01 00:00:00',
            'limite_hasta' => '2026-10-15 23:59:59',
            'cajas' => 0,
            'total_centavos' => 0,
            'simulacion' => ['unidades' => [], 'tramos' => []],
            'creado_por_id' => $this->usuario->id,
        ]);
        $this->actingAs($this->usuario)->get(route('gestion_interna.comisiones_empaque.exportar.corte', $borrador))->assertOk();
        $this->assertSame(CorteComisionEmpaque::BORRADOR, $borrador->fresh()->estado);
    }

    public function test_conservar_y_excluir_sobreviven_varias_sincronizaciones(): void
    {
        $servicio = app(SincronizarComisionesEmpaqueService::class);
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $pedido = $this->pedido(['empacado_at' => '2026-10-06 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 1);
        $servicio->ejecutar($this->usuario);
        $pedido->update(['cancelado_at' => '2026-10-07 10:00:00', 'motivo_cancelacion' => 'cliente']);
        $servicio->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->first();
        $gestionar->decidirCancelacion($unidad, 'conservar', 'El empaque ya se realizó', $this->usuario);
        $servicio->ejecutar($this->usuario);
        $servicio->ejecutar($this->usuario);
        $unidad = $unidad->fresh();

        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $unidad->situacion);
        $this->assertSame('conservar', $unidad->ancla_decision);
        $this->assertSame($this->usuario->id, $unidad->ancla_por_id);
        $this->assertNotNull($unidad->ancla_at);
        $this->assertSame(1, $unidad->incidencias()->where('tipo', 'cancelacion')->where('estado', 'resuelta')->count());
        $this->assertSame(0, $unidad->incidencias()->where('estado', 'pendiente')->count());

        $pedido->update(['cancelado_at' => null]);
        $otro = $this->pedido(['empacado_at' => '2026-10-08 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($otro, 1);
        $servicio->ejecutar($this->usuario);
        $excluida = UnidadComisionEmpaque::query()->where('pedido_bma_id', $otro->id)->first();
        $otro->update(['cancelado_at' => '2026-10-09 10:00:00', 'motivo_cancelacion' => 'cliente']);
        $servicio->ejecutar($this->usuario);
        $gestionar->decidirCancelacion($excluida->fresh(), 'excluir', 'No corresponde pagarla', $this->usuario);
        $servicio->ejecutar($this->usuario);
        $servicio->ejecutar($this->usuario);
        $excluida = $excluida->fresh();
        $this->assertSame(UnidadComisionEmpaque::EXCLUIDA, $excluida->situacion);
        $this->assertSame(0, $excluida->importe_centavos);
        $this->assertSame('excluir', $excluida->ancla_decision);
    }

    public function test_descartar_una_reversion_no_la_vuelve_elegible_al_sincronizar(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-07 09:00:00', 'empacado_por_id' => $this->usuario->id, 'numero_cajas' => 1]);
        PedidoBmaBultoEmpaque::query()->create([
            'pedido_bma_id' => $pedido->id, 'numero' => 1, 'empacado_por_id' => $this->usuario->id, 'empacado_at' => $pedido->empacado_at,
        ]);
        $servicio = app(SincronizarComisionesEmpaqueService::class);
        $servicio->ejecutar($this->usuario);
        $pedido->update(['empacado_at' => null]);
        PedidoBmaBultoEmpaque::query()->delete();
        $servicio->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->first();
        app(GestionarUnidadComisionEmpaqueService::class)->resolverRevision($unidad, 'descartar', 'Era una reversión, no un trabajo nuevo', $this->usuario);
        $servicio->ejecutar($this->usuario);
        $servicio->ejecutar($this->usuario);

        $unidad = $unidad->fresh();
        $this->assertSame(UnidadComisionEmpaque::EXCLUIDA, $unidad->situacion);
        $this->assertSame('descartar', $unidad->ancla_decision);
        $this->assertSame(0, $unidad->incidencias()->where('estado', 'pendiente')->count());
    }

    public function test_un_cambio_de_cantidad_abre_revision_nueva_sin_borrar_la_decision(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-06 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $caja = PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'cantidad' => 1,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ]);
        $servicio = app(SincronizarComisionesEmpaqueService::class);
        $servicio->ejecutar($this->usuario);
        $pedido->update(['cancelado_at' => '2026-10-07 08:00:00', 'motivo_cancelacion' => 'cliente']);
        $servicio->ejecutar($this->usuario);
        $unidad = UnidadComisionEmpaque::query()->first();
        app(GestionarUnidadComisionEmpaqueService::class)->decidirCancelacion($unidad, 'conservar', 'Se conserva este empaque', $this->usuario);
        $caja->update(['cantidad' => 3]);
        $servicio->ejecutar($this->usuario);
        $unidad = $unidad->fresh();

        $this->assertNotSame(UnidadComisionEmpaque::ELEGIBLE, $unidad->situacion);
        $this->assertSame('conservar', $unidad->ancla_decision);
        $this->assertSame(1, $unidad->incidencias()->where('tipo', 'cancelacion')->where('estado', 'resuelta')->count());
        $this->assertTrue($unidad->incidencias()->where('estado', 'pendiente')->exists());
    }

    public function test_la_clave_de_captura_no_duplica_y_rechaza_datos_distintos(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $datos = [
            'clave_idempotencia' => 'misma-clave',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 1,
            'cliente_nombre' => 'Cliente',
            'folio_pedido' => 'PED-1',
        ];
        $primera = $gestionar->registrarManual($datos, $this->usuario);
        $segunda = $gestionar->registrarManual($datos, $this->usuario);
        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame(1, UnidadComisionEmpaque::query()->count());

        try {
            $gestionar->registrarManual(array_merge($datos, ['cantidad' => 2]), $this->usuario);
            $this->fail('Debió rechazar la misma clave con datos distintos.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('clave_idempotencia', $e->errors());
        }
    }

    public function test_dos_manuales_equivalentes_bloquean_la_aceptacion_hasta_reconocer_trabajo_distinto(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $base = ['empacado_at' => '2026-10-12 10:00:00', 'cantidad' => 1, 'cliente_nombre' => 'Cliente', 'folio_pedido' => 'PED-DUP'];
        $gestionar->registrarManual($base + ['clave_idempotencia' => 'a'], $this->usuario);
        $segunda = $gestionar->registrarManual($base + ['clave_idempotencia' => 'b'], $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::POSIBLE_DUPLICADO, $segunda->fresh()->situacion);
        $this->assertNotEmpty($segunda->fresh()->duplicados_relacionados);

        try {
            $gestionar->revisarManual($segunda->fresh(), 'aceptar', 'Es el mismo', $this->usuario);
            $this->fail('No debió aceptar un posible duplicado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('decision', $e->errors());
        }

        $gestionar->revisarManual($segunda->fresh(), 'trabajo_distinto', 'Es el segundo empaque del día', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $segunda->fresh()->situacion);
        $this->assertNotNull($segunda->fresh()->ancla_motivo);
    }

    public function test_el_manual_aprobado_no_se_suma_con_el_empaque_sincronizado_de_la_misma_area(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-11 09:00:00', 'empacado_por_id' => $this->usuario->id, 'folio' => 'PED-TIENDA']);
        $pedido->update(['cliente_id' => $this->cliente()]);
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $manual = $gestionar->registrarManual([
            'clave_idempotencia' => 'manual-tienda',
            'empacado_at' => '2026-10-11 11:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        $gestionar->revisarManual($manual, 'aceptar', 'Empaque de tienda', $this->usuario);
        $this->cumplimiento($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $tienda = UnidadComisionEmpaque::query()->where('area_id', AreaComisionEmpaque::query()->where('codigo', 'tienda')->value('id'))->get();
        $this->assertCount(2, $tienda);
        $this->assertTrue($tienda->every(fn ($u) => $u->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO || $u->posible_duplicado));
        $this->assertSame(0, $tienda->where('situacion', UnidadComisionEmpaque::ELEGIBLE)->count());
    }

    public function test_dos_trabajos_del_mismo_pedido_en_dias_distintos_siguen_siendo_legitimos(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $gestionar->registrarManual([
            'clave_idempotencia' => 'dia-1', 'empacado_at' => '2026-10-11 10:00:00', 'cantidad' => 1,
            'cliente_nombre' => 'Cliente', 'folio_pedido' => 'PED-DIAS',
        ], $this->usuario);
        $segundo = $gestionar->registrarManual([
            'clave_idempotencia' => 'dia-2', 'empacado_at' => '2026-10-13 10:00:00', 'cantidad' => 1,
            'cliente_nombre' => 'Cliente', 'folio_pedido' => 'PED-DIAS',
        ], $this->usuario);

        $this->assertTrue($segundo->posible_duplicado);
        $criterio = collect($segundo->logistica['coincidencias'] ?? [])->first();
        $this->assertSame('antecedente', $criterio['criterio'] ?? null);
        try {
            $gestionar->revisarManual($segundo->fresh(), 'aceptar', 'Segundo día', $this->usuario);
            $this->fail('Una fecha distinta no basta para aceptar el registro.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('decision', $e->errors());
        }
        $gestionar->revisarManual($segundo->fresh(), 'trabajo_distinto', 'Es otro empaque, en otra fecha', $this->usuario);
        $reconocido = $segundo->fresh();
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $reconocido->situacion);
        $this->assertSame($this->usuario->id, $reconocido->ancla_por_id);
        $this->assertNotNull($reconocido->ancla_at);
    }

    public function test_manual_cancelado_se_conserva_solo_si_la_decision_cubre_ese_hecho(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $sincronizar = app(SincronizarComisionesEmpaqueService::class);
        $comprobar = app(ComprobarFuenteCorteComisionEmpaque::class);
        $pedido = $this->pedido(['folio' => 'PED-CAN-MAN', 'empacado_at' => null]);
        $manual = $gestionar->registrarManual([
            'clave_idempotencia' => 'manual-cancelado',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        $gestionar->revisarManual($manual, 'aceptar', 'Empaque de tienda ya hecho', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $manual->fresh()->situacion);

        $pedido->update(['cancelado_at' => '2026-10-12 09:00:00', 'motivo_cancelacion' => 'cliente']);
        $sincronizar->ejecutar($this->usuario);
        $manual = $manual->fresh();
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_DECISION, $manual->situacion);
        $this->assertNull($manual->importe_centavos);
        $this->assertTrue($manual->incidencias()->where('tipo', 'cancelacion')->where('estado', 'pendiente')->exists());
        $this->assertNotNull($comprobar->diferencia($manual));

        $gestionar->decidirCancelacion($manual, 'conservar', 'El empaque de tienda ya se realizó', $this->usuario);
        $sincronizar->ejecutar($this->usuario);
        $sincronizar->ejecutar($this->usuario);
        $manual = $manual->fresh();
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $manual->situacion);
        $this->assertSame('conservar', $manual->ancla_decision);
        $this->assertNotSame($manual->huella, $manual->ancla_huella);
        $this->assertNull($comprobar->diferencia($manual));
        $this->assertSame(1, $manual->incidencias()->where('tipo', 'cancelacion')->where('estado', 'resuelta')->count());
        $this->assertSame(0, $manual->incidencias()->where('estado', 'pendiente')->count());

        $pedido->update(['cancelado_at' => '2026-10-13 09:00:00']);
        $sincronizar->ejecutar($this->usuario);
        $manual = $manual->fresh();
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_DECISION, $manual->situacion);
        $this->assertSame('conservar', $manual->ancla_decision);
        $this->assertNotNull($comprobar->diferencia($manual));
        $this->assertSame(1, $manual->incidencias()->where('tipo', 'cancelacion')->where('estado', 'pendiente')->count());

        [$tienda] = $this->prepararTienda();
        $gestionar->decidirCancelacion($manual, 'conservar', 'Se revisó la cancelación nueva', $this->usuario);
        $corte = $this->confirmar($tienda->id, '2026-10-01', '2026-10-31');
        $pedido->update(['cancelado_at' => '2026-10-14 09:00:00']);
        $sincronizar->ejecutar($this->usuario);
        $manual = $manual->fresh();
        $this->assertSame($corte->id, $manual->corte_id);
        $this->assertSame(600, $corte->fresh()->total_centavos);
        $this->assertTrue($manual->incidencias()->where('tipo', 'cancelacion')->where('estado', 'pendiente')->exists());
        try {
            $gestionar->decidirCancelacion($manual, 'excluir', 'Ya no entra', $this->usuario);
            $this->fail('No debió reescribir un corte confirmado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('unidad', $e->errors());
        }
    }

    public function test_cancelacion_anterior_a_la_aceptacion_no_entra_sin_decision(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $pedido = $this->pedido(['folio' => 'PED-ANTES', 'empacado_at' => null]);
        $pedido->update(['cancelado_at' => '2026-10-10 08:00:00', 'motivo_cancelacion' => 'cliente']);
        $manual = $gestionar->registrarManual([
            'clave_idempotencia' => 'manual-antes',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $manual = $manual->fresh();
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_REVISION, $manual->situacion);
        $this->assertNotNull($manual->diferencia);
        $this->assertTrue($manual->incidencias()->where('tipo', 'cancelacion')->where('estado', 'pendiente')->exists());

        $gestionar->revisarManual($manual, 'aceptar', 'El registro sí corresponde', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_DECISION, $manual->fresh()->situacion);
        $this->assertNull($manual->fresh()->importe_centavos);

        $gestionar->decidirCancelacion($manual->fresh(), 'excluir', 'No se paga este empaque', $this->usuario);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $excluida = $manual->fresh();
        $this->assertSame(UnidadComisionEmpaque::EXCLUIDA, $excluida->situacion);
        $this->assertSame(0, $excluida->importe_centavos);
        $this->assertSame(0, $excluida->incidencias()->where('estado', 'pendiente')->count());
    }

    public function test_manual_sin_referencia_no_se_acepta_de_forma_normal(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $manual = $gestionar->registrarManual([
            'clave_idempotencia' => 'sin-ref',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 1,
            'cliente_nombre' => 'Mostrador',
        ], $this->usuario);

        $this->assertSame('insuficiente', $manual->logistica['referencia']);
        $this->assertStringNotContainsString($manual->clave_origen, (string) $manual->diferencia);
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_REVISION, $manual->situacion);
        try {
            $gestionar->revisarManual($manual, 'aceptar', 'Se ve bien', $this->usuario);
            $this->fail('No debió aceptar sin referencia verificable.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('decision', $e->errors());
        }
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_REVISION, $manual->fresh()->situacion);

        $gestionar->revisarManual($manual->fresh(), 'aceptar_sin_referencia', 'No hay folio ni remisión; se revisó en tienda', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $manual->fresh()->situacion);
    }

    public function test_el_folio_de_un_pedido_existente_vincula_la_captura(): void
    {
        $pedido = $this->pedido(['folio' => 'PED-VINCULO', 'folio_remision' => 'REM-9']);
        $manual = app(GestionarUnidadComisionEmpaqueService::class)->registrarManual([
            'clave_idempotencia' => 'vinculo',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 1,
            'folio_pedido' => 'PED-VINCULO',
        ], $this->usuario);

        $this->assertSame($pedido->id, $manual->pedido_bma_id);
        $this->assertSame('pedido', $manual->logistica['referencia']);
        $this->assertSame('REM-9', $manual->folio_remision);
    }

    public function test_la_consulta_del_pedido_informa_las_cajas_sin_montos_de_venta(): void
    {
        $this->usuario->givePermissionTo(['gestion_interna.comisiones_empaque.ver', 'gestion_interna.comisiones_empaque.ver_todas']);
        $pedido = $this->pedido(['folio' => 'PED-INFO', 'folio_remision' => 'REM-INFO', 'numero_cajas' => 2, 'costo_envio' => 500, 'total_mercancia' => 900]);
        $this->caja($pedido, 2);
        $unidad = app(GestionarUnidadComisionEmpaqueService::class)->registrarManual([
            'clave_idempotencia' => 'info-pedido',
            'empacado_at' => '2026-10-11 15:00:00',
            'cantidad' => 2,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);

        $respuesta = $this->actingAs($this->usuario)->getJson(route('gestion_interna.comisiones_empaque.pedido.informativo', $unidad));

        $respuesta->assertOk();
        $respuesta->assertJsonPath('vinculado', true);
        $respuesta->assertJsonPath('folio_pedido', 'PED-INFO');
        $respuesta->assertJsonPath('cajas_declaradas', 2);
        $respuesta->assertJsonPath('cajas.0.cantidad', 2);
        $respuesta->assertJsonMissingPath('costo_envio');
        $respuesta->assertJsonMissingPath('total_mercancia');
        $this->assertArrayNotHasKey('costo_envio', $respuesta->json('cajas.0'));
    }

    public function test_coincidencia_aparecida_antes_de_aceptar_bloquea_y_el_sincronizado_posterior_no_se_suma(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $pedido = $this->pedido(['folio' => 'PED-MEDIO', 'empacado_at' => '2026-10-11 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $primero = $gestionar->registrarManual([
            'clave_idempotencia' => 'medio-1',
            'empacado_at' => '2026-10-11 11:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        $gestionar->registrarManual([
            'clave_idempotencia' => 'medio-2',
            'empacado_at' => '2026-10-11 12:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);

        try {
            $gestionar->revisarManual($primero->fresh(), 'aceptar', 'Era el primero', $this->usuario);
            $this->fail('La coincidencia apareció antes de aceptar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('decision', $e->errors());
        }
        $this->assertSame(UnidadComisionEmpaque::POSIBLE_DUPLICADO, $primero->fresh()->situacion);
        $this->assertSame('conflicto', collect($primero->fresh()->logistica['coincidencias'])->first()['criterio']);

        $unico = $gestionar->registrarManual([
            'clave_idempotencia' => 'luego-sync',
            'empacado_at' => '2026-10-12 11:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        $gestionar->revisarManual($unico, 'trabajo_distinto', 'Este día sí es otro empaque', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::ELEGIBLE, $unico->fresh()->situacion);
        $this->cumplimiento($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $tiendaId = AreaComisionEmpaque::query()->where('codigo', 'tienda')->value('id');
        $tienda = UnidadComisionEmpaque::query()->where('area_id', $tiendaId)->where('pedido_bma_id', $pedido->id)->get();
        $this->assertTrue($tienda->contains(fn ($u) => $u->id === $unico->id && ($u->posible_duplicado || $u->situacion === UnidadComisionEmpaque::POSIBLE_DUPLICADO)));
        $this->assertSame(0, $tienda->where('situacion', UnidadComisionEmpaque::ELEGIBLE)->count());
    }

    public function test_un_corte_confirmado_no_absorbe_el_duplicado_posterior(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        [$tienda] = $this->prepararTienda();
        $pedido = $this->pedido(['folio' => 'PED-CORTE-DUP']);
        $manual = $gestionar->registrarManual([
            'clave_idempotencia' => 'corte-dup',
            'empacado_at' => '2026-10-11 11:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);
        $gestionar->revisarManual($manual, 'aceptar', 'Listo', $this->usuario);
        $corte = $this->confirmar($tienda->id, '2026-10-01', '2026-10-31');
        $gestionar->registrarManual([
            'clave_idempotencia' => 'corte-dup-2',
            'empacado_at' => '2026-10-11 16:00:00',
            'cantidad' => 1,
            'pedido_bma_id' => $pedido->id,
        ], $this->usuario);

        $manual = $manual->fresh();
        $this->assertSame($corte->id, $manual->corte_id);
        $this->assertSame(600, $manual->importe_centavos);
        $this->assertSame(600, $corte->fresh()->total_centavos);
        $this->assertTrue($manual->incidencias()->where('tipo', 'duplicado')->where('estado', 'pendiente')->exists());
        $this->assertSame(1, UnidadComisionEmpaque::query()->where('corte_id', $corte->id)->count());
    }

    public function test_fecha_incorrecta_sigue_visible_como_antecedente(): void
    {
        $gestionar = app(GestionarUnidadComisionEmpaqueService::class);
        $base = ['cantidad' => 1, 'cliente_nombre' => 'Cliente', 'folio_pedido' => 'PED-FECHA-MAL'];
        $gestionar->registrarManual($base + ['clave_idempotencia' => 'fecha-real', 'empacado_at' => '2026-10-11 10:00:00'], $this->usuario);
        $erronea = $gestionar->registrarManual($base + ['clave_idempotencia' => 'fecha-mal', 'empacado_at' => '2026-10-18 10:00:00'], $this->usuario);
        $coincidencias = collect($erronea->logistica['coincidencias'] ?? []);

        $this->assertTrue($erronea->posible_duplicado);
        $this->assertSame('antecedente', $coincidencias->first()['criterio'] ?? null);
        $this->assertStringContainsString('2026-10-11', (string) ($coincidencias->first()['empacado_at'] ?? ''));
        $gestionar->revisarManual($erronea, 'rechazar', 'La fecha estaba mal y es el mismo trabajo', $this->usuario);
        $this->assertSame(UnidadComisionEmpaque::RECHAZADA, $erronea->fresh()->situacion);
    }

    public function test_confirmar_sin_actualizar_rechaza_una_cancelacion_en_la_fuente(): void
    {
        $pedido = $this->pedido(['empacado_at' => '2026-10-06 09:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 1);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'A', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($cedis->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);
        $servicio = app(ConfirmarCorteComisionEmpaqueService::class);
        $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
        $calculo = $servicio->calcular($cedis->id, DineroComisionEmpaque::inicioDia('2026-10-01', $zona), DineroComisionEmpaque::finDia('2026-10-10', $zona), false);
        $pedido->update(['cancelado_at' => now(), 'motivo_cancelacion' => 'cliente']);

        try {
            $servicio->confirmar($cedis->id, DineroComisionEmpaque::inicioDia('2026-10-01', $zona), DineroComisionEmpaque::finDia('2026-10-10', $zona), false, $calculo['hash'], $this->usuario);
            $this->fail('No debió confirmar con la fuente cancelada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cambió', $e->errors()['corte'][0]);
        }
        $this->assertSame(0, CorteComisionEmpaque::query()->where('estado', CorteComisionEmpaque::CONFIRMADO)->count());
    }

    public function test_sin_areas_asignadas_no_hay_acceso_y_tienda_no_abre_cedis(): void
    {
        $this->assertSame([], app(AlcanceAreaComisionEmpaque::class)->ids($this->usuario));
        $tienda = AreaComisionEmpaque::query()->where('codigo', 'tienda')->first();
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        UsuarioAreaComisionEmpaque::query()->create(['user_id' => $this->usuario->id, 'area_id' => $tienda->id]);
        $ids = app(AlcanceAreaComisionEmpaque::class)->ids($this->usuario->fresh());
        $this->assertSame([$tienda->id], $ids);

        $this->usuario->givePermissionTo('gestion_interna.comisiones_empaque.ver');
        $this->actingAs($this->usuario)->get(route('gestion_interna.comisiones_empaque.index', ['area_id' => $cedis->id]))->assertForbidden();
        $this->actingAs($this->usuario)->get(route('gestion_interna.comisiones_empaque.index', ['area_id' => $tienda->id]))->assertOk();

        $global = User::factory()->create();
        $global->givePermissionTo(['gestion_interna.comisiones_empaque.ver', 'gestion_interna.comisiones_empaque.ver_todas']);
        $this->assertNull(app(AlcanceAreaComisionEmpaque::class)->ids($global));
    }

    /** @return array{0: AreaComisionEmpaque, 1: ParticipanteComisionEmpaque} */
    private function prepararTienda(): array
    {
        $tienda = AreaComisionEmpaque::query()->where('codigo', 'tienda')->first();
        $user = User::factory()->create();
        $participante = ParticipanteComisionEmpaque::query()->create(['nombre' => 'Tienda', 'user_id' => $user->id, 'activo' => true]);
        $this->reparto($tienda->id, '2026-10-01 00:00:00', [[$participante->id, '100']]);

        return [$tienda, $participante];
    }

    public function test_equipo_permanente_reparte_fondo_comun_y_conserva_el_corte_al_salir_una_persona(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $usuarios = collect(range(1, 4))->map(fn ($i) => User::factory()->create(['name' => 'Persona '.$i]));
        $equipos = app(GuardarEquipoComisionEmpaqueService::class);
        $equipo1 = $equipos->guardar($area->id, $usuarios->map(fn ($u, $i) => ['user_id' => $u->id, 'porcentaje' => ['50', '30', '10', '10'][$i]])->all(), 'Equipo inicial', $this->usuario);
        $periodos = app(GestionarPeriodoComisionEmpaqueService::class);
        $datos = ['nombre' => 'Octubre', 'area_id' => $area->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-15'];
        $primero = $periodos->guardar($datos, $this->usuario);
        $datos['hasta'] = '2026-10-13';
        $datos['motivo'] = 'Salida de la cuarta persona';
        $periodos->guardar($datos, $this->usuario, $primero);
        // Todas las cajas las empaca la misma persona: el fondo sigue repartiéndose entre cuatro.
        foreach ([['2026-10-13 23:59:59', 100], ['2026-10-14 00:00:00', 90]] as [$fecha, $cantidad]) {
            $pedido = $this->pedido(['empacado_at' => $fecha, 'empacado_por_id' => $usuarios[0]->id]);
            $this->caja($pedido, $cantidad);
        }
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $corte1 = $this->confirmar($area->id, '2026-10-01', '2026-10-13');
        $this->assertSame([50000, 30000, 10000, 10000], $corte1->asignaciones->pluck('importe_centavos')->all());
        $equipo2 = $equipos->guardar($area->id, $usuarios->take(3)->values()->map(fn ($u, $i) => ['user_id' => $u->id, 'porcentaje' => ['60', '30', '10'][$i]])->all(), 'Salida de la cuarta persona', $this->usuario);
        $segundo = $periodos->guardar(['nombre' => 'Continuación', 'area_id' => $area->id, 'desde' => '2026-10-14', 'hasta' => '2026-10-15'], $this->usuario);
        $this->assertSame($equipo2->id, RepartoComisionEmpaque::permanente($area->id)->id);
        $corte2 = $this->confirmar($area->id, '2026-10-14', '2026-10-15');
        $this->assertSame([54000, 27000, 9000], $corte2->asignaciones->pluck('importe_centavos')->all());
        $this->assertSame([50000, 30000, 10000, 10000], $corte1->fresh()->asignaciones->pluck('importe_centavos')->all());
        $this->assertSame(10000, $corte1->fresh()->asignaciones->last()->pendienteCentavos());
        $this->assertNotSame($equipo1->id, $equipo2->id);
        $this->assertSame('2026-10-14', $primero->fresh()->hasta->toDateString());
        $this->assertDatabaseHas('comisiones_empaque_auditorias', ['entidad' => 'equipo', 'entidad_id' => $equipo2->id]);
        try {
            $periodos->guardar($datos, $this->usuario, $primero);
            $this->fail('No se puede modificar un periodo cerrado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('periodo', $e->errors());
        }
        $this->assertSame($corte2->id, $segundo->corte()->id);
    }

    public function test_equipo_persiste_entre_periodos_y_el_cambio_invalida_un_calculo_anterior(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $users = [User::factory()->create(), User::factory()->create()];
        $service = app(GuardarEquipoComisionEmpaqueService::class);
        $equipo = $service->guardar($area->id, [['user_id' => $users[0]->id, 'porcentaje' => '70'], ['user_id' => $users[1]->id, 'porcentaje' => '30']], 'Equipo habitual', $this->usuario);
        $periodos = app(GestionarPeriodoComisionEmpaqueService::class);
        foreach ([['2026-10-01', '2026-10-13'], ['2026-10-14', '2026-10-15']] as [$desde, $hasta]) {
            $periodos->guardar(['area_id' => $area->id, 'nombre' => 'Periodo', 'desde' => $desde, 'hasta' => $hasta], $this->usuario);
        }
        $this->assertSame($equipo->id, RepartoComisionEmpaque::permanente($area->id)->id);
        $this->assertSame(1, RepartoComisionEmpaque::where('permanente', true)->count());
        $pedido = $this->pedido(['empacado_at' => '2026-10-10 10:00:00', 'empacado_por_id' => $users[1]->id]);
        $this->caja($pedido, 100);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $cortes = app(ConfirmarCorteComisionEmpaqueService::class);
        $desde = DineroComisionEmpaque::inicioDia('2026-10-01', config('app.timezone'));
        $hasta = DineroComisionEmpaque::finDia('2026-10-13', config('app.timezone'));
        $anterior = $cortes->calcular($area->id, $desde, $hasta, false);
        $service->guardar($area->id, [['user_id' => $users[0]->id, 'porcentaje' => '80'], ['user_id' => $users[1]->id, 'porcentaje' => '20']], 'Ajuste de porcentajes', $this->usuario);
        $actual = $cortes->calcular($area->id, $desde, $hasta, false);
        $this->assertNotSame($anterior['hash'], $actual['hash']);
        $this->expectException(ValidationException::class);
        $cortes->confirmar($area->id, $desde, $hasta, false, $anterior['hash'], $this->usuario);
    }

    public function test_periodos_solo_definen_fechas_y_rechazan_solapes_y_cortes_cruzados(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $service = app(GestionarPeriodoComisionEmpaqueService::class);
        $datos = ['area_id' => $area->id, 'nombre' => 'Periodo', 'desde' => '2026-10-01', 'hasta' => '2026-10-13'];
        $service->guardar($datos, $this->usuario);
        try {
            $service->guardar(array_replace($datos, ['desde' => '2026-10-13', 'hasta' => '2026-10-15']), $this->usuario);
            $this->fail('El día 13 ya está incluido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('desde', $e->errors());
        }
        $service->guardar(array_replace($datos, ['desde' => '2026-10-14', 'hasta' => '2026-10-15']), $this->usuario);
        $this->assertSame(0, RepartoComisionEmpaque::count());
        $calculo = app(ConfirmarCorteComisionEmpaqueService::class)->calcular($area->id, DineroComisionEmpaque::inicioDia('2026-10-01', config('app.timezone')), DineroComisionEmpaque::finDia('2026-10-15', config('app.timezone')), false);
        $this->assertNotEmpty($calculo['errores']);
    }

    public function test_equipo_valida_100_y_no_repite_usuarios_ni_modifica_otras_areas(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $tienda = AreaComisionEmpaque::where('codigo', 'tienda')->firstOrFail();
        $u = User::factory()->create();
        $service = app(GuardarEquipoComisionEmpaqueService::class);
        $original = $service->guardar($tienda->id, [['user_id' => $u->id, 'porcentaje' => '100']], 'Equipo tienda', $this->usuario);
        foreach ([[['user_id' => $u->id, 'porcentaje' => '99']], [['user_id' => $u->id, 'porcentaje' => '50'], ['user_id' => $u->id, 'porcentaje' => '50']]] as $lineas) {
            try {
                $service->guardar($area->id, $lineas, 'Prueba incorrecta', $this->usuario);
                $this->fail('El reparto inválido debe rechazarse.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lineas', $e->errors());
            }
        }
        $this->assertNull(RepartoComisionEmpaque::permanente($area->id));
        $this->assertSame($original->id, RepartoComisionEmpaque::permanente($tienda->id)->id);
        $this->assertSame(1, ParticipanteComisionEmpaque::count());
    }

    public function test_pantalla_recibe_equipo_y_tarifas_desde_el_acumulado(): void
    {
        $this->usuario->givePermissionTo(['gestion_interna.comisiones_empaque.ver', 'gestion_interna.comisiones_empaque.ver_todas', 'gestion_interna.comisiones_empaque.configurar']);
        ParticipanteComisionEmpaque::create(['nombre' => 'Equipo visible', 'user_id' => $this->usuario->id, 'activo' => true]);
        $this->actingAs($this->usuario)->get(route('gestion_interna.comisiones_empaque.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('GestionInterna/ComisionesEmpaque/Index', false)->has('participantes', 1)->where('participantes.0.nombre', 'Equipo visible')->has('tarifas')->has('alcances')->has('periodos')->has('equipo'));
    }

    public function test_periodos_y_equipo_respetan_area_y_los_porcentajes_no_se_capturan_en_periodos(): void
    {
        $this->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class]);
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $this->usuario->givePermissionTo(['gestion_interna.comisiones_empaque.ver', 'gestion_interna.comisiones_empaque.configurar']);
        $datos = ['nombre' => 'Periodo', 'area_id' => $area->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-13'];
        $this->actingAs($this->usuario)->post(route('gestion_interna.comisiones_empaque.periodos.store'), $datos)->assertForbidden();
        $equipo = ['area_id' => $area->id, 'reparto_id' => 0, 'motivo' => 'Equipo inicial', 'lineas' => [['user_id' => $this->usuario->id, 'porcentaje' => '100']]];
        $this->put(route('gestion_interna.comisiones_empaque.equipo'), $equipo)->assertForbidden();
        UsuarioAreaComisionEmpaque::create(['user_id' => $this->usuario->id, 'area_id' => $area->id]);
        $this->put(route('gestion_interna.comisiones_empaque.equipo'), $equipo)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('gestion_interna.comisiones_empaque.periodos.store'), $datos)->assertRedirect()->assertSessionHasNoErrors();
        $periodo = PeriodoComisionEmpaque::firstOrFail();
        $this->put(route('gestion_interna.comisiones_empaque.periodos.update', $periodo->id), array_replace($datos, ['hasta' => '2026-10-12']))->assertSessionHasErrors('motivo');
        $this->assertSame('2026-10-14', $periodo->fresh()->hasta->toDateString());
        $this->post(route('gestion_interna.comisiones_empaque.periodos.store'), array_replace($datos, ['desde' => '2026-10-14', 'hasta' => '2026-10-15', 'lineas' => $equipo['lineas']]))->assertSessionHasErrors('lineas');
    }

    public function test_migracion_separa_periodos_y_conserva_asignaciones_y_equipo_anterior(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $persona = ParticipanteComisionEmpaque::create(['nombre' => 'Persona histórica', 'user_id' => $this->usuario->id, 'activo' => true]);
        $legado = $this->reparto($area->id, '2026-10-01 00:00:00', [[$persona->id, '100']]);
        $legado->update(['nombre' => 'Periodo anterior', 'vigente_hasta' => '2026-10-14 00:00:00']);
        $pedido = $this->pedido(['empacado_at' => '2026-10-10 10:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($pedido, 10);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $corte = $this->confirmar($area->id, '2026-10-01', '2026-10-13');
        $migracion = require database_path('migrations/2026_10_20_130000_separar_periodos_y_equipo_comisiones_empaque.php');
        $migracion->down();
        $migracion->up();
        $nuevo = PeriodoComisionEmpaque::firstOrFail();
        $this->assertSame('Periodo anterior', $nuevo->nombre);
        $this->assertSame($corte->id, $nuevo->corte()->id);
        $this->assertSame(10000, $corte->fresh()->asignaciones->first()->importe_centavos);
        $permanente = RepartoComisionEmpaque::permanente($area->id);
        $this->assertNotSame($legado->id, $permanente->id);
        $this->assertSame($persona->id, $permanente->lineas->first()->participante_id);
        $this->assertSame(10000, $permanente->lineas->first()->puntos_base);
    }

    public function test_reporte_y_resumen_comparten_area_fechas_y_filtros_de_revision(): void
    {
        $this->usuario->givePermissionTo('gestion_interna.comisiones_empaque.ver_todas');
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $p1 = $this->pedido(['empacado_at' => '2026-10-13 12:00:00', 'empacado_por_id' => $this->usuario->id]);
        $p2 = $this->pedido(['empacado_at' => '2026-10-14 12:00:00', 'empacado_por_id' => $this->usuario->id]);
        $this->caja($p1, 10);
        $this->caja($p2, 20);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);
        $consultar = app(ConsultarComisionesEmpaqueService::class);
        $request = new Request(['area_id' => $area->id, 'desde' => '2026-10-13', 'hasta' => '2026-10-13']);
        $resumen = $consultar->resumen($this->usuario, $request);
        $this->assertSame(10, $resumen['cajas_elegibles']);
        $this->assertSame('100.00', $resumen['importe_por_cortar']);
        $this->assertSame(1, $resumen['total_registros']);
        $request->merge(['vista' => 'pendientes']);
        $this->assertSame(0, $consultar->filtrar($request, $this->usuario)->count());
        $request->merge(['vista' => 'revisados']);
        $this->assertSame(1, $consultar->filtrar($request, $this->usuario)->count());
    }

    public function test_equipo_rechaza_un_guardado_sobre_una_version_que_ya_cambio(): void
    {
        $area = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $service = app(GuardarEquipoComisionEmpaqueService::class);
        $datos = [['user_id' => $this->usuario->id, 'porcentaje' => '100']];
        $primero = $service->guardar($area->id, $datos, 'Equipo inicial', $this->usuario, 0);
        try {
            $service->guardar($area->id, $datos, 'Guardado atrasado', $this->usuario, 0);
            $this->fail('La versión anterior debe rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('equipo', $e->errors());
        }
        $this->assertSame($primero->id, RepartoComisionEmpaque::permanente($area->id)->id);
    }

    public function test_saldos_y_exportacion_separan_el_pago_de_cedis_del_de_tienda(): void
    {
        $this->usuario->givePermissionTo('gestion_interna.comisiones_empaque.ver_todas');
        $persona = ParticipanteComisionEmpaque::create(['nombre' => 'Persona compartida', 'user_id' => $this->usuario->id, 'activo' => true]);
        $cedis = AreaComisionEmpaque::where('codigo', 'cedis')->firstOrFail();
        $tienda = AreaComisionEmpaque::where('codigo', 'tienda')->firstOrFail();
        $asignaciones = [];
        foreach ([[$cedis, 1000], [$tienda, 2000]] as [$area, $importe]) {
            $corte = CorteComisionEmpaque::create(['folio' => 'CE-AREA-'.$area->id, 'area_id' => $area->id, 'estado' => CorteComisionEmpaque::CONFIRMADO, 'limite_desde' => '2026-10-01', 'limite_hasta' => '2026-10-13 23:59:59', 'cajas' => 1, 'total_centavos' => $importe, 'creado_por_id' => $this->usuario->id]);
            $asignaciones[] = AsignacionComisionEmpaque::create(['corte_id' => $corte->id, 'participante_id' => $persona->id, 'puntos_base' => 10000, 'importe_centavos' => $importe, 'pagado_centavos' => 0]);
        }
        app(RegistrarPagoComisionEmpaqueService::class)->pagar($persona->id, '2026-10-20', '30.00', [
            ['asignacion_id' => $asignaciones[0]->id, 'importe' => '10.00'],
            ['asignacion_id' => $asignaciones[1]->id, 'importe' => '20.00'],
        ], 'Pago de ambas áreas', 'transferencia', null, 'pago-areas-1', $this->usuario);
        $saldos = app(ConsultarComisionesEmpaqueService::class)->saldos($this->usuario, $cedis->id);
        $this->assertSame('10.00', $saldos[0]['pagado']);
        $this->assertCount(1, $saldos[0]['asignaciones']);
        $response = app(ExportarComisionesEmpaqueService::class)->pagos($this->usuario, $cedis->id);
        ob_start();
        $response->sendContent();
        $contenido = ob_get_clean();
        $temporal = tempnam(sys_get_temp_dir(), 'ce-export-');
        $archivo = $temporal.'.xlsx';
        try {
            file_put_contents($archivo, $contenido);
            $filas = (new FastExcel)->import($archivo);
            $this->assertCount(2, $filas);
            $this->assertSame('10.00', (string) $filas->last()['Pagado']);
            $this->assertSame('CEDIS', $filas->first()['Área']);
        } finally {
            @unlink($archivo);
            @unlink($temporal);
        }
    }

    private function confirmar(int $areaId, string $desde, string $hasta): CorteComisionEmpaque
    {
        $servicio = app(ConfirmarCorteComisionEmpaqueService::class);
        $zona = ConfiguracionComisionEmpaque::actual()->zona_horaria;
        $inicio = DineroComisionEmpaque::inicioDia($desde, $zona);
        $fin = DineroComisionEmpaque::finDia($hasta, $zona);
        $calculo = $servicio->calcular($areaId, $inicio, $fin, false);

        return $servicio->confirmar($areaId, $inicio, $fin, false, $calculo['hash'], $this->usuario);
    }

    private function reparto(int $areaId, string $desde, array $lineas): RepartoComisionEmpaque
    {
        return app(ConfigurarComisionEmpaqueService::class)->guardarReparto(
            $areaId,
            $desde,
            array_map(fn ($l) => ['participante_id' => $l[0], 'porcentaje' => $l[1]], $lineas),
            $this->usuario
        );
    }

    private function estadoDe(int $participanteId, int $corteId): string
    {
        $asignacion = AsignacionComisionEmpaque::query()->where('corte_id', $corteId)->where('participante_id', $participanteId)->first();

        return $asignacion->pendienteCentavos() === 0 ? 'pagado' : 'pendiente';
    }

    public function test_la_constancia_pagada_fuera_no_entra_al_fondo_ni_se_duplica_al_sincronizar(): void
    {
        $this->usuario->givePermissionTo('gestion_interna.comisiones_empaque.ver_todas');
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->first();
        $constancia = app(GestionarUnidadComisionEmpaqueService::class)->registrarFila([
            'clave_idempotencia' => 'hist-1',
            'area_id' => $cedis->id,
            'empacado_at' => '2026-10-10 12:00:00',
            'cantidad' => 2,
            'cliente_nombre' => 'Cliente histórico',
            'folio_remision' => 'REM-HIST',
            'paqueteria' => 'Estafeta',
            'logistica' => 'sin',
            'empacador_nombre' => 'Lucía',
            'liquidacion' => 'pagado_fuera',
            'origen_registro' => 'carga',
        ], $this->usuario);

        $this->assertSame(UnidadComisionEmpaque::LIQUIDADA_FUERA, $constancia->situacion);
        $this->assertSame(UnidadComisionEmpaque::LIQUIDACION_PAGADO_FUERA, $constancia->liquidacion);
        $this->assertSame(2000, $constancia->importe_centavos);
        $this->assertSame('Lucía', $constancia->empacador_nombre);

        $pedido = $this->pedido([
            'empacado_at' => '2026-10-10 12:00:00',
            'empacado_por_id' => $this->usuario->id,
            'folio_remision' => 'REM-HIST',
            'numero_cajas' => 2,
        ]);
        $this->caja($pedido, 2);
        app(SincronizarComisionesEmpaqueService::class)->ejecutar($this->usuario);

        $this->assertSame(1, UnidadComisionEmpaque::query()->where('folio_remision', 'REM-HIST')->count());
        $resumen = app(ConsultarComisionesEmpaqueService::class)->resumen($this->usuario, new Request(['area_id' => $cedis->id]));
        $this->assertSame('0.00', $resumen['importe_por_cortar']);
        $this->assertSame('20.00', $resumen['importe_constancia']);

        $carga = app(CargarEmpaquesComisionEmpaqueService::class);
        $invalida = "fecha,cliente,cajas,remision,paqueteria,logistica,empaco,area,liquidacion\n2026-10-03,Cliente nuevo,1,REM-NUEVA,Estafeta,sin,Lucía,tienda,pendiente\nsin-fecha,Roto,0,,Estafeta,sin,Ana,cedis,pendiente\n";
        $this->assertNotEmpty($carga->interpretar($invalida, $this->usuario)['errores']);
        try {
            $carga->guardar($invalida, $this->usuario);
            $this->fail('Una fila inválida no debe guardar ninguna.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('texto', $e->errors());
        }
        $this->assertSame(1, UnidadComisionEmpaque::query()->count());

        $valida = "fecha,cliente,cajas,remision,paqueteria,logistica,empaco,area,liquidacion\n2026-10-03,Cliente nuevo,1,REM-NUEVA,Estafeta,sin,Lucía,tienda,pendiente\n2026-10-04,Cliente viejo,2,REM-VIEJA,Estafeta,con,Ana,cedis,pagado fuera\n";
        $visto = $carga->interpretar($valida, $this->usuario);
        $this->assertSame([], $visto['errores'], json_encode($visto['errores'], JSON_UNESCAPED_UNICODE));
        $resultado = $carga->guardar($valida, $this->usuario);
        $this->assertSame(2, $resultado['nuevas']);
        $pendiente = UnidadComisionEmpaque::query()->where('folio_remision', 'REM-NUEVA')->first();
        $this->assertSame(UnidadComisionEmpaque::PENDIENTE_REVISION, $pendiente->situacion);
        $this->assertNull($pendiente->importe_centavos);
        $repeticion = $carga->interpretar($valida, $this->usuario);
        $this->assertSame([], $repeticion['errores'], json_encode($repeticion['errores'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(['nuevas' => 0, 'repetidas' => 2], $carga->guardar($valida, $this->usuario));
        $this->assertSame(3, UnidadComisionEmpaque::query()->count());
    }

    private function pedido(array $overrides = []): PedidoBma
    {
        $estatus = CatalogoEstatusPedido::query()->where('fase_ciclo', CatalogoEstatusPedido::FASE_EN_CEDIS)->first()
            ?: CatalogoEstatusPedido::query()->first();

        return PedidoBma::query()->create(array_merge([
            'folio' => 'PED-'.uniqid(),
            'fecha' => '2026-10-01',
            'vendedor_id' => $this->usuario->id,
            'catalogo_estatus_pedido_id' => $estatus->id,
            'numero_cajas' => 1,
            'total_mercancia' => 100,
            'costo_envio' => 0,
        ], $overrides));
    }

    private function caja(PedidoBma $pedido, int $cantidad): void
    {
        PedidoBmaCaja::query()->create([
            'pedido_bma_id' => $pedido->id,
            'uuid_operativo' => (string) Str::uuid(),
            'catalogo_tipo_caja_id' => DB::table('catalogo_tipos_caja_pedido')->value('id'),
            'cantidad' => $cantidad,
            'orden' => 1,
            'estado_operativo' => PedidoBmaCaja::ESTADO_ACTIVA,
            'estatus_recoleccion' => PedidoBmaCaja::ESTATUS_PENDIENTE,
        ]);
    }

    private function cumplimiento(PedidoBma $pedido, int $bultos): PedidoBmaCumplimientoFisico
    {
        $almacen = Almacen::query()->create([
            'codigo' => 'T-'.uniqid(),
            'nombre' => 'Tienda prueba',
            'activo' => true,
            'permite_salida_pedidos' => true,
        ]);
        $modalidad = CatalogoModalidadPreparacionPedido::query()->firstOrCreate(
            ['codigo' => CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO],
            ['nombre' => 'Envío municipio', 'activo' => true]
        );
        $tarea = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $almacen->id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
            'solicitada_por_id' => $this->usuario->id,
            'solicitada_at' => now(),
            'version' => 1,
        ]);

        return PedidoBmaCumplimientoFisico::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'estado' => PedidoBmaCumplimientoFisico::ESTADO_EMPACADA,
            'cantidad' => $bultos,
            'bultos_salida' => $bultos,
            'empacado_at' => '2026-10-11 11:00:00',
            'empacado_por_id' => $this->usuario->id,
            'version' => 1,
        ]);
    }

    private function cliente(): int
    {
        $lista = DB::table('catalogo_listas_descuento')->insertGetId([
            'nombre' => 'Lista '.uniqid(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('clientes')->insertGetId([
            'numero_cliente' => substr(uniqid(), -8),
            'nombre' => 'Cliente prueba',
            'lista_actual_id' => $lista,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
