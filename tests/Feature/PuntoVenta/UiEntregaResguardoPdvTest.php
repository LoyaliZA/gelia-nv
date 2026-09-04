<?php

namespace Tests\Feature\PuntoVenta;

use App\Models\Almacen;
use App\Models\ConfiguracionSistema;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Services\PuntoVenta\Resguardos\RegistrarEntregaResguardoPdvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UiEntregaResguardoPdvTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Sucursal $sucursal;

    private Almacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Role::findOrCreate('Super Admin', 'web');
        $this->activarModulo();
        $this->seedPermisos();

        $this->sucursal = Sucursal::factory()->create(['nombre' => 'Sucursal Norte']);
        $this->almacen = Almacen::query()->create([
            'codigo' => 'PISO-ENT-UI',
            'nombre' => 'Piso entrega UI',
            'sucursal_id' => $this->sucursal->id,
            'activo' => true,
        ]);

        $this->usuario = User::factory()->create();
        $this->usuario->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_ENTREGAR,
        ]);
        $this->usuario->concederAccesoSucursal($this->sucursal, esPrincipal: true);
    }

    public function test_formulario_entrega_renderiza_inertia_con_snapshot_y_bultos(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.entrega.create', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/Entrega', false)
                ->where('resguardo.id', $resguardo->id)
                ->where('resguardo.version', 1)
                ->where('resguardo.snapshot_folio', 'REM-ENT-UI')
                ->where('puede_entregar', true)
                ->where('motivo_no_entregable', null)
                ->has('resguardo.bultos', 1)
                ->has('resguardo.cantidad_bultos_en_custodia')
                ->has('catalogos.relaciones')
                ->where('catalogos.metodo_validacion', RegistrarEntregaResguardoPdvService::METODO_VALIDACION_FIRMA));
    }

    public function test_formulario_entrega_sin_permiso_entregar(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();
        $soloVer = User::factory()->create();
        $soloVer->givePermissionTo([
            PuntoVentaModulo::PERMISO_ACCEDER,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        ]);
        $soloVer->concederAccesoSucursal($this->sucursal, esPrincipal: true);

        $this->actingAs($soloVer)
            ->get(route('punto_venta.resguardos.entrega.create', $resguardo))
            ->assertForbidden();
    }

    public function test_formulario_entrega_ya_entregado_marca_no_disponible(): void
    {
        $resguardo = $this->crearResguardoEnCustodia([
            'estado' => ResguardoPdv::ESTADO_ENTREGADO,
            'entrega_completada_at' => now(),
        ]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.entrega.create', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('puede_entregar', false)
                ->where('resguardo.estado', ResguardoPdv::ESTADO_ENTREGADO));
    }

    public function test_formulario_entrega_bloqueada_por_incidencia(): void
    {
        $resguardo = $this->crearResguardoEnCustodia(['entrega_bloqueada' => true]);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.entrega.create', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('puede_entregar', false)
                ->where('motivo_no_entregable', 'La entrega está bloqueada por una incidencia o cancelación pendiente.'));
    }

    public function test_index_y_detalle_exponen_permiso_entregar(): void
    {
        $resguardo = $this->crearResguardoEnCustodia();

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permisos.entregar', true));

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.show', $resguardo))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permisos.entregar', true));
    }

    public function test_formulario_entrega_multiple_requiere_dos_resguardos_entregables(): void
    {
        $a = $this->crearResguardoEnCustodia();
        $b = $this->crearResguardoEnCustodia(['snapshot_folio' => 'REM-ENT-UI-B']);

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.entregas_multiples.create', ['ids' => [$a->id, $b->id]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PuntoVenta/Resguardos/EntregaMultiple', false)
                ->where('puede_entregar', true)
                ->has('resguardos.0.id')
                ->has('resguardos.1.id'));

        $this->actingAs($this->usuario)
            ->get(route('punto_venta.resguardos.entregas_multiples.create', ['ids' => [$a->id]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('puede_entregar', false));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function crearResguardoEnCustodia(array $overrides = []): ResguardoPdv
    {
        $resguardo = ResguardoPdv::factory()->create(array_merge([
            'sucursal_id' => $this->sucursal->id,
            'almacen_id' => $this->almacen->id,
            'estado' => ResguardoPdv::ESTADO_EN_CUSTODIA,
            'snapshot_folio' => 'REM-ENT-UI',
            'cantidad_bultos_esperada' => 1,
            'recepcion_fisica_at' => now()->subHour(),
            'entrega_bloqueada' => false,
            'version' => 1,
        ], $overrides));

        if (($overrides['estado'] ?? ResguardoPdv::ESTADO_EN_CUSTODIA) !== ResguardoPdv::ESTADO_ENTREGADO) {
            ResguardoPdvBulto::query()->create([
                'resguardo_id' => $resguardo->id,
                'pedido_bma_id' => $resguardo->pedido_bma_id,
                'folio' => 'CJA-'.$resguardo->id,
                'tipo' => ResguardoPdvBulto::TIPO_CAJA,
                'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO,
                'recepcion_at' => now()->subHour(),
                'recepcion_por_id' => $this->usuario->id,
                'version' => 1,
            ]);
        }

        return $resguardo;
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
